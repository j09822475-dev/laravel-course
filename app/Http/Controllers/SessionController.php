<?php

namespace App\Http\Controllers;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\InterviewSession;
use App\Models\Trainee;
use App\Services\InterviewBuilder;
use App\Services\ProgressReport;
use App\Services\SessionGrader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(
        protected InterviewBuilder $builder,
        protected SessionGrader $grader,
    ) {}

    /** Запуск новой тренировки. */
    public function store(Request $request, Trainee $trainee): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:screening,tech_interview,quiz,drill,english_interview'],
            'level' => ['nullable', 'in:junior,middle,senior'],
            'language' => ['nullable', 'in:ru,en'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['integer', 'exists:topics,id'],
            'size' => ['nullable', 'integer', 'min:3', 'max:30'],
        ]);

        try {
            $session = $this->builder->create($trainee, SessionMode::from($data['mode']), [
                'level' => $data['level'] ?? null,
                'language' => $data['language'] ?? null,
                'topics' => $data['topics'] ?? [],
                'size' => $data['size'] ?? 10,
            ]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['mode' => $e->getMessage()]);
        }

        return redirect()->route('sessions.show', $session);
    }

    /** Текущий вопрос сессии либо итоговый отчёт. */
    public function show(InterviewSession $session, Trainee $trainee): View|RedirectResponse
    {
        $this->authorizeSession($session, $trainee);

        if ($session->isCompleted()) {
            return redirect()->route('sessions.report', $session);
        }

        $item = $session->currentItem();

        if (! $item) {
            $this->grader->complete($session);

            return redirect()->route('sessions.report', $session);
        }

        $item->load('question.topic', 'question.translations');

        return view('sessions.show', [
            'session' => $session,
            'item' => $item,
            'text' => $item->question->in($session->language()),
            'answered' => $session->answeredCount(),
            'total' => $session->items()->count(),
        ]);
    }

    /** Приём ответа на текущий вопрос. */
    public function answer(Request $request, InterviewSession $session, Trainee $trainee): RedirectResponse
    {
        $this->authorizeSession($session, $trainee);

        $item = $session->currentItem();

        if (! $item) {
            return redirect()->route('sessions.show', $session);
        }

        $data = $request->validate([
            'answer' => ['nullable', 'string', 'max:5000'],
            'option' => ['nullable', 'integer', 'min:0', 'max:9'],
            'rating' => ['nullable', 'integer', 'min:0', 'max:3'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        if (! $item->question->isAutoGraded() && ! isset($data['rating'])) {
            return back()->withErrors(['rating' => 'Оцените свой ответ, чтобы двигаться дальше.'])->withInput();
        }

        $this->grader->answer($item, $data, $trainee);

        if (! $this->grader->hasNext($session)) {
            $this->grader->complete($session);

            return redirect()->route('sessions.report', $session);
        }

        return redirect()->route('sessions.show', $session);
    }

    /** Итоговый отчёт по сессии. */
    public function report(InterviewSession $session, Trainee $trainee, ProgressReport $report): View
    {
        $this->authorizeSession($session, $trainee);

        if (! $session->isCompleted() && ! $this->grader->hasNext($session)) {
            $this->grader->complete($session);
        }

        return view('sessions.report', [
            'session' => $session->load('items.question.topic', 'items.question.translations'),
            'advice' => $report->advice($session),
        ]);
    }

    /** Прервать сессию. */
    public function destroy(InterviewSession $session, Trainee $trainee): RedirectResponse
    {
        $this->authorizeSession($session, $trainee);

        $session->update(['status' => SessionStatus::Abandoned, 'completed_at' => now()]);

        return redirect()->route('dashboard')->with('status', 'Сессия прервана.');
    }

    protected function authorizeSession(InterviewSession $session, Trainee $trainee): void
    {
        abort_unless($session->trainee_id === $trainee->id, 403);
    }
}
