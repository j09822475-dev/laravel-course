<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainee_id', 'question_id', 'ease_factor', 'interval_days',
        'repetitions', 'lapses', 'last_rating', 'due_on', 'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'ease_factor' => 'float',
            'due_on' => 'date',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function scopeDue(Builder $query, ?\DateTimeInterface $on = null): Builder
    {
        return $query->whereDate('due_on', '<=', ($on ?? now())->format('Y-m-d'));
    }
}
