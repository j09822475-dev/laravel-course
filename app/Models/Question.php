<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\Language;
use App\Enums\QuestionType;
use App\Support\QuestionText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id', 'external_id', 'type', 'difficulty', 'prompt', 'answer',
        'explanation', 'options', 'correct_option', 'accepted', 'follow_ups',
        'checklist', 'red_flags', 'tags', 'estimated_seconds',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'options' => 'array',
            'accepted' => 'array',
            'follow_ups' => 'array',
            'checklist' => 'array',
            'red_flags' => 'array',
            'tags' => 'array',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function sessionItems(): HasMany
    {
        return $this->hasMany(SessionItem::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class);
    }

    /** Текст вопроса на нужном языке с откатом на русский оригинал. */
    public function in(Language|string $language): QuestionText
    {
        $language = $language instanceof Language ? $language : Language::from($language);

        $translation = $language === Language::Ru
            ? null
            : $this->translations->firstWhere('locale', $language->value);

        if (! $translation) {
            return new QuestionText(
                language: $language,
                prompt: $this->prompt,
                answer: $this->answer,
                explanation: $this->explanation,
                options: $this->options,
                followUps: $this->follow_ups,
                translated: $language === Language::Ru,
            );
        }

        return new QuestionText(
            language: $language,
            prompt: $translation->prompt,
            answer: $translation->answer,
            explanation: $translation->explanation ?? $this->explanation,
            options: $translation->options ?? $this->options,
            followUps: $translation->follow_ups ?? $this->follow_ups,
            translated: true,
        );
    }

    /** Есть ли перевод на указанный язык. */
    public function scopeTranslatedInto(Builder $query, Language|string $language): Builder
    {
        $locale = $language instanceof Language ? $language->value : $language;

        if ($locale === Language::Ru->value) {
            return $query;
        }

        return $query->whereHas('translations', fn (Builder $q) => $q->where('locale', $locale));
    }

    public function scopeOfType(Builder $query, QuestionType|string $type): Builder
    {
        return $query->where('type', $type instanceof QuestionType ? $type->value : $type);
    }

    public function scopeOfDifficulty(Builder $query, array $levels): Builder
    {
        return $query->whereIn('difficulty', array_map(
            fn (Difficulty|string $level) => $level instanceof Difficulty ? $level->value : $level,
            $levels,
        ));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace('%', '\%', $term).'%';

        return $query->where(fn (Builder $q) => $q->where('prompt', 'like', $like)->orWhere('answer', 'like', $like));
    }

    public function isAutoGraded(): bool
    {
        if ($this->type === QuestionType::Cloze) {
            return filled($this->accepted) || filled($this->answer);
        }

        return $this->type->isAutoGraded() && $this->correct_option !== null;
    }

    /** Вопрос с вариантами ответа, где нужно выбрать один. */
    public function hasOptions(): bool
    {
        return $this->type === QuestionType::Quiz && filled($this->options);
    }
}
