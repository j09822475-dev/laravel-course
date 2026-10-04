<?php

namespace App\Http\Resources;

use App\Models\SessionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SessionItem */
class SessionItemResource extends JsonResource
{
    public function __construct(SessionItem $item, protected bool $withAnswer = false)
    {
        parent::__construct($item);
    }

    public function toArray(Request $request): array
    {
        $question = new QuestionResource($this->question, $this->session->language());

        return [
            'position' => $this->position,
            'phase' => $this->phase,
            'phase_label' => $this->phaseLabel(),
            'answered_at' => $this->answered_at?->toIso8601String(),
            'self_rating' => $this->self_rating,
            'is_correct' => $this->is_correct,
            'seconds_spent' => $this->seconds_spent,
            'answer_text' => $this->when($this->withAnswer, $this->answer_text),
            'question' => $this->withAnswer ? $question->withAnswer() : $question->toArray($request),
        ];
    }
}
