<?php

namespace App\Models;

use App\Enums\Language;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainee_id', 'mode', 'title', 'status', 'config', 'score',
        'total_seconds', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => SessionMode::class,
            'status' => SessionStatus::class,
            'config' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SessionItem::class)->orderBy('position');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::Completed->value);
    }

    public function currentItem(): ?SessionItem
    {
        return $this->items()->whereNull('answered_at')->first();
    }

    public function answeredCount(): int
    {
        return $this->items()->whereNotNull('answered_at')->count();
    }

    public function isCompleted(): bool
    {
        return $this->status === SessionStatus::Completed;
    }

    /** Язык, на котором проходит сессия. */
    public function language(): Language
    {
        return Language::tryFrom($this->config['language'] ?? '') ?? $this->mode->language();
    }
}
