<?php

namespace App\Http\Resources;

use App\Enums\Language;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Question */
class QuestionResource extends JsonResource
{
    public function __construct(Question $question, protected Language $language = Language::Ru)
    {
        parent::__construct($question);
    }

    public function toArray(Request $request): array
    {
        $text = $this->in($this->language);

        return [
            'id' => $this->external_id,
            'type' => $this->type->value,
            'difficulty' => $this->difficulty->value,
            'topic' => ['slug' => $this->topic->slug, 'name' => $this->topic->name, 'area' => $this->topic->area],
            'language' => $text->shownIn()->value,
            'prompt' => $text->prompt,
            'options' => $text->options,
            'estimated_seconds' => $this->estimated_seconds,
            'technique' => $this->type->technique(),
            // Эталон не отдаём вместе с вопросом: иначе клиент покажет его до ответа.
            'auto_graded' => $this->isAutoGraded(),
        ];
    }

    /** Полный текст с эталоном — только после ответа. */
    public function withAnswer(): array
    {
        $text = $this->resource->in($this->language);

        return $this->toArray(request()) + [
            'answer' => $text->answer,
            'explanation' => $text->explanation,
            'follow_ups' => $text->followUps,
            'checklist' => $this->resource->checklist,
            'red_flags' => $this->resource->red_flags,
        ];
    }
}
