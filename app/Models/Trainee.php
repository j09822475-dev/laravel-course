<?php

namespace App\Models;

use App\Enums\Difficulty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trainee extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['uuid', 'name', 'target_level'];

    /** Значения по умолчанию нужны и в модели: новый профиль используется сразу после create(). */
    protected $attributes = [
        'name' => 'Кандидат',
        'target_level' => 'middle',
    ];

    protected function casts(): array
    {
        return ['target_level' => Difficulty::class];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(InterviewSession::class);
    }

    public function reviewCards(): HasMany
    {
        return $this->hasMany(ReviewCard::class);
    }
}
