<?php

namespace App\Http\Resources;

use App\Models\InterviewSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InterviewSession */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode->value,
            'title' => $this->title,
            'status' => $this->status->value,
            'language' => $this->language()->value,
            'score' => $this->score,
            'total_seconds' => $this->total_seconds,
            'answered' => $this->answeredCount(),
            'total' => $this->items()->count(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
