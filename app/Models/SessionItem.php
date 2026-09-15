<?php

namespace App\Models;

use App\Enums\SelfRating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'interview_session_id', 'question_id', 'position', 'phase', 'answer_text',
        'selected_option', 'self_rating', 'is_correct', 'seconds_spent', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterviewSession::class, 'interview_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function rating(): ?SelfRating
    {
        return $this->self_rating === null ? null : SelfRating::from($this->self_rating);
    }

    public function phaseLabel(): string
    {
        return match ($this->phase) {
            'warm_up' => 'Разогрев речи',
            'intro' => 'Знакомство',
            'screening' => 'Скрининг',
            'tech' => 'Техническая секция',
            'live_coding' => 'Живое кодирование',
            'drill' => 'Отработка фраз',
            'wrap_up' => 'Вопросы кандидата',
            default => $this->phase,
        };
    }
}
