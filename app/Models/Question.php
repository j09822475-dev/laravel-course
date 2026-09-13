<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
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
        'explanation', 'options', 'correct_option', 'follow_ups', 'checklist',
        'red_flags', 'tags', 'estimated_seconds',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'options' => 'array',
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
        return $this->type->isAutoGraded() && $this->correct_option !== null;
    }
}
