<?php

namespace Tests\Feature;

use App\Enums\Language;
use App\Enums\QuestionType;
use App\Models\InterviewSession;
use App\Models\Question;
use App\Models\QuestionTranslation;
use App\Models\Topic;
use App\Models\Trainee;
use Database\Seeders\QuestionBankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Три способа тренировать английский: отдельная тема, переключатель языка
 * у существующих вопросов и сценарий интервью на английском.
 */
class EnglishTrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_falls_back_to_russian_without_translation(): void
    {
        $question = Question::factory()->create(['prompt' => 'Что такое middleware?']);

        $text = $question->in(Language::En);

        $this->assertSame('Что такое middleware?', $text->prompt);
        $this->assertFalse($text->translated);
        $this->assertTrue($text->needsTranslation());
        $this->assertSame(Language::Ru, $text->shownIn());
    }

    public function test_translation_replaces_prompt_answer_and_options(): void
    {
        $question = Question::factory()->quiz(1)->create([
            'prompt' => 'Что вернёт find()?',
            'answer' => 'null',
        ]);

        QuestionTranslation::create([
            'question_id' => $question->id,
            'locale' => 'en',
            'prompt' => 'What does find() return?',
            'answer' => 'null',
            'options' => ['Option A', 'Option B', 'Option C'],
        ]);

        $text = $question->refresh()->in(Language::En);

        $this->assertSame('What does find() return?', $text->prompt);
        $this->assertSame(['Option A', 'Option B', 'Option C'], $text->options);
        $this->assertTrue($text->translated);
    }

    public function test_question_bank_page_switches_language(): void
    {
        $question = Question::factory()->create(['prompt' => 'Что такое очередь?']);

        QuestionTranslation::create([
            'question_id' => $question->id,
            'locale' => 'en',
            'prompt' => 'What is a queue?',
            'answer' => 'A queue holds jobs.',
        ]);

        $this->get('/questions')->assertOk()->assertSee('Что такое очередь?');
        $this->get('/questions?lang=en')->assertOk()->assertSee('What is a queue?');

        $this->get(route('questions.show', [$question, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('A queue holds jobs.');
    }

    public function test_session_can_be_started_in_english(): void
    {
        $topic = Topic::factory()->create(['area' => 'backend']);
        $questions = Question::factory()->count(12)->for($topic)->quiz()->create();

        foreach ($questions as $question) {
            QuestionTranslation::create([
                'question_id' => $question->id,
                'locale' => 'en',
                'prompt' => 'English prompt for '.$question->external_id,
                'answer' => 'English answer',
                'options' => ['First', 'Second', 'Third'],
            ]);
        }

        $this->get('/');
        $this->post('/sessions', ['mode' => 'quiz', 'language' => 'en'])->assertRedirect();

        $session = InterviewSession::sole();

        $this->assertSame(Language::En, $session->language());

        $this->get(route('sessions.show', $session))
            ->assertOk()
            ->assertSee('English prompt for')
            ->assertSee('First');
    }

    public function test_english_questions_are_skipped_when_translation_is_missing(): void
    {
        $topic = Topic::factory()->create(['area' => 'backend']);

        $translated = Question::factory()->for($topic)->quiz()->create(['prompt' => 'Оригинал с переводом']);
        Question::factory()->count(5)->for($topic)->quiz()->create(['prompt' => 'Оригинал без перевода']);

        QuestionTranslation::create([
            'question_id' => $translated->id,
            'locale' => 'en',
            'prompt' => 'Only translated question',
            'answer' => 'Answer',
            'options' => ['A', 'B', 'C'],
        ]);

        $this->get('/');
        $this->post('/sessions', ['mode' => 'quiz', 'language' => 'en']);

        $session = InterviewSession::sole();

        $this->assertSame([$translated->id], $session->items->pluck('question_id')->unique()->all());
    }

    public function test_english_interview_mode_follows_the_language_scenario(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $this->get('/');
        $this->post('/sessions', ['mode' => 'english_interview'])->assertRedirect();

        $session = InterviewSession::sole();

        $this->assertSame(Language::En, $session->language());
        $this->assertSame(
            ['warm_up', 'intro', 'tech', 'drill', 'wrap_up'],
            $session->items->pluck('phase')->unique()->values()->all(),
        );

        $byPhase = $session->items->groupBy('phase');

        $this->assertSame(QuestionType::Shadowing, $byPhase['warm_up']->first()->question->type);
        $this->assertSame(QuestionType::Cloze, $byPhase['drill']->first()->question->type);

        // Технические вопросы берутся из обычных тем, но показываются по-английски.
        $this->assertNotContains('language', $byPhase['tech']->map(fn ($item) => $item->question->topic->area)->all());
    }

    public function test_language_questions_never_leak_into_a_regular_screening(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $this->get('/');
        $this->post('/sessions', ['mode' => 'screening']);

        $areas = InterviewSession::sole()->items->map(fn ($item) => $item->question->topic->area);

        $this->assertNotContains('language', $areas->all());
    }

    public function test_cloze_answer_is_graded_automatically(): void
    {
        $topic = Topic::factory()->create(['area' => 'language', 'slug' => 'english-interview']);

        $question = Question::factory()->for($topic)->create([
            'type' => QuestionType::Cloze,
            'prompt' => 'I was ___ for the payment module.',
            'answer' => 'responsible',
            'accepted' => ['responsible'],
        ]);

        $session = $this->startDrillWith($question);

        $this->post(route('sessions.answer', $session), ['answer' => ' Responsible. ', 'seconds' => 12]);

        $item = $session->items()->first();

        $this->assertTrue($item->is_correct);
        $this->assertSame(3, $item->self_rating);
    }

    public function test_wrong_cloze_answer_is_counted_as_failure(): void
    {
        $topic = Topic::factory()->create(['area' => 'language', 'slug' => 'english-interview']);

        $question = Question::factory()->for($topic)->create([
            'type' => QuestionType::Cloze,
            'prompt' => 'I was ___ for the payment module.',
            'answer' => 'responsible',
            'accepted' => ['responsible'],
        ]);

        $session = $this->startDrillWith($question);

        $this->post(route('sessions.answer', $session), ['answer' => 'responsable', 'seconds' => 12]);

        $item = $session->items()->first();

        $this->assertFalse($item->is_correct);
        $this->assertSame(0, $item->self_rating);
    }

    public function test_texts_are_rendered_without_template_indentation(): void
    {
        $topic = Topic::factory()->create(['area' => 'language']);

        $question = Question::factory()->for($topic)->create([
            'type' => QuestionType::Shadowing,
            'prompt' => 'Shadowing drill',
            'answer' => 'I am a full stack developer.',
            'explanation' => 'Приём: шэдоуинг.',
        ]);

        $session = $this->startDrillWith($question);

        // Блоки эталонов выводятся с сохранением переводов строк (pre-wrap),
        // поэтому отступы шаблона не должны попадать в текст.
        $this->get(route('sessions.show', $session))
            ->assertOk()
            ->assertSee('>Приём: шэдоуинг.<', false)
            ->assertSee('>I am a full stack developer.<', false);
    }

    /** Вручную собранная сессия из одного вопроса — чтобы не зависеть от подбора. */
    protected function startDrillWith(Question $question): InterviewSession
    {
        $this->get('/');

        $trainee = Trainee::sole();

        $session = $trainee->sessions()->create([
            'mode' => 'drill',
            'title' => 'Тест',
            'status' => 'in_progress',
            'config' => ['language' => 'en'],
            'started_at' => now(),
        ]);

        $session->items()->create([
            'question_id' => $question->id,
            'position' => 1,
            'phase' => 'drill',
        ]);

        return $session;
    }
}
