<?php

namespace Tests\Unit;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\Question;
use App\Models\Topic;
use App\Models\Trainee;
use App\Services\ProgressReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressReportTest extends TestCase
{
    use RefreshDatabase;

    protected ProgressReport $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->report = new ProgressReport;
    }

    /** Создаёт завершённую сессию с заданными самооценками. */
    protected function sessionWithRatings(Trainee $trainee, Topic $topic, array $ratings): void
    {
        $session = $trainee->sessions()->create([
            'mode' => SessionMode::Drill,
            'title' => 'Тест',
            'status' => SessionStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
            'score' => 50,
        ]);

        foreach ($ratings as $index => $rating) {
            $session->items()->create([
                'question_id' => Question::factory()->for($topic)->create()->id,
                'position' => $index + 1,
                'phase' => 'tech',
                'self_rating' => $rating,
                'seconds_spent' => 30,
                'answered_at' => now(),
            ]);
        }
    }

    public function test_readiness_is_zero_without_answers(): void
    {
        $this->assertSame(0, $this->report->readiness(Trainee::factory()->create()));
    }

    public function test_mastery_reflects_average_self_rating(): void
    {
        $trainee = Trainee::factory()->create();
        $topic = Topic::factory()->create();

        $this->sessionWithRatings($trainee, $topic, [3, 3, 0, 0]);

        $row = $this->report->topicStats($trainee)->firstWhere('topic.id', $topic->id);

        $this->assertSame(4, $row['answered']);
        $this->assertSame(2, $row['failed']);
        $this->assertEqualsWithDelta(0.5, $row['mastery'], 0.01);
    }

    public function test_confident_answers_give_higher_readiness_than_failures(): void
    {
        $strong = Trainee::factory()->create();
        $weak = Trainee::factory()->create();
        $topic = Topic::factory()->create();

        $this->sessionWithRatings($strong, $topic, [3, 3, 3]);
        $this->sessionWithRatings($weak, $topic, [0, 0, 1]);

        $this->assertGreaterThan(
            $this->report->readiness($weak),
            $this->report->readiness($strong),
        );
    }

    public function test_weak_spots_list_worst_topics_only(): void
    {
        $trainee = Trainee::factory()->create();
        $strongTopic = Topic::factory()->create(['name' => 'Сильная тема']);
        $weakTopic = Topic::factory()->create(['name' => 'Слабая тема']);

        $this->sessionWithRatings($trainee, $strongTopic, [3, 3]);
        $this->sessionWithRatings($trainee, $weakTopic, [0, 1]);

        $weak = $this->report->weakSpots($trainee);

        $this->assertCount(1, $weak);
        $this->assertSame('Слабая тема', $weak->first()['topic']->name);
    }

    public function test_summary_counts_completed_sessions(): void
    {
        $trainee = Trainee::factory()->create();
        $topic = Topic::factory()->create();

        $this->sessionWithRatings($trainee, $topic, [2, 2]);
        $this->sessionWithRatings($trainee, $topic, [3, 3]);

        $summary = $this->report->summary($trainee);

        $this->assertSame(2, $summary['sessions']);
        $this->assertSame(4, $summary['answered']);
        $this->assertSame(50, $summary['average_score']);
    }
}
