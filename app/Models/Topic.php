<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'area', 'description', 'position'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function areaLabel(): string
    {
        return match ($this->area) {
            'backend' => 'Backend',
            'frontend' => 'Frontend',
            'database' => 'Базы данных',
            'architecture' => 'Архитектура',
            'devops' => 'DevOps',
            'soft' => 'Коммуникация',
            'language' => 'Английский',
            default => $this->area,
        };
    }
}
