<?php

namespace App\Http\Controllers\Api;

use App\Enums\SessionMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionItemResource;
use App\Http\Resources\SessionResource;
use App\Models\InterviewSession;
use App\Models\Trainee;
use App\Services\InterviewBuilder;
use App\Services\ProgressReport;
use App\Services\SessionGrader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Полный цикл тренировки для мобильного клиента: старт, вопрос, ответ, отчёт. */
class SessionApiController extends Controller
{
    public function __construct(
        protected InterviewBuilder $builder,
        protected SessionGrader $grader,
    ) {}

    public function index(Trainee $trainee): JsonResponse
    {
        return response()->json([
            'data' => SessionResource::collection($trainee->sessions()->latest()->limit(20)->get()),
        ]);
    }

    public function store(Request $request, Trainee $trainee): JsonResponse
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
            $session = $this->builder->create($trainee, SessionMode::from($data['mode']), $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->state($session), 201);
    }

    public function show(InterviewSession $session, Trainee $trainee): JsonResponse
    {
        $this->authorizeSession($session, $trainee);

        return response()->json($this->state($session));
    }

    public function answer(Request $request, InterviewSession $session, Trainee $trainee): JsonResponse
    {
        $this->authorizeSession($session, $trainee);

        $item = $session->currentItem();

        if (! $item) {
            return response()->json(['message' => 'В сессии не осталось вопросов.'], 422);
        }

        $data = $request->validate([
            'answer' => ['nullable', 'string', 'max:5000'],
            'option' => ['nullable', 'integer', 'min:0', 'max:9'],
            'rating' => ['nullable', 'integer', 'min:0', 'max:3'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        if (! $item->question->isAutoGraded() && ! isset($data['rating'])) {
            return response()->json([
                'message' => 'Оцените свой ответ.',
                'errors' => ['rating' => ['Нужна самооценка от 0 до 3.']],
            ], 422);
        }

        $graded = $this->grader->answer($item, $data, $trainee);

        if (! $this->grader->hasNext($session)) {
            $this->grader->complete($session);
        }

        return response()->json([
            'graded' => new SessionItemResource($graded->fresh(['question.topic', 'question.translations']), withAnswer: true),
        ] + $this->state($session->fresh()));
    }

    public function report(InterviewSession $session, Trainee $trainee, ProgressReport $report): JsonResponse
    {
        $this->authorizeSession($session, $trainee);

        $session->load('items.question.topic', 'items.question.translations');

        return response()->json([
            'session' => new SessionResource($session),
            'advice' => $report->advice($session),
            'items' => SessionItemResource::collection(
                $session->items->map(fn ($item) => new SessionItemResource($item, withAnswer: true)),
            ),
        ]);
    }

    /** Текущее состояние: сессия и вопрос, на котором кандидат остановился. */
    protected function state(InterviewSession $session): array
    {
        $item = $session->currentItem();

        return [
            'session' => new SessionResource($session),
            'current' => $item
                ? new SessionItemResource($item->load('question.topic', 'question.translations'))
                : null,
        ];
    }

    protected function authorizeSession(InterviewSession $session, Trainee $trainee): void
    {
        abort_unless($session->trainee_id === $trainee->id, 403);
    }
}
