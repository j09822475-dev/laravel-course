<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['question_id', 'locale', 'prompt', 'answer', 'explanation', 'options', 'follow_ups'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'follow_ups' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
