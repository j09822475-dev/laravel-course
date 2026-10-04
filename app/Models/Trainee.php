<?php

namespace App\Models;

use App\Enums\Difficulty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Trainee extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Заполняются из запроса только эти поля. Привязка к аккаунту и токен
     * доступа ставятся кодом через forceFill: случайный update($request->all())
     * в будущем контроллере иначе отдал бы чужой профиль или открыл доступ.
     */
    protected $fillable = ['name', 'target_level'];

    /** Значения по умолчанию нужны и в модели: новый профиль используется сразу после create(). */
    protected $attributes = [
        'name' => 'Кандидат',
        'target_level' => 'middle',
    ];

    protected function casts(): array
    {
        return [
            'target_level' => Difficulty::class,
            'shared_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Гостевой профиль живёт только в сессии браузера и может быть присвоен аккаунту. */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function isShared(): bool
    {
        return filled($this->share_token);
    }

    public function shareUrl(): ?string
    {
        return $this->isShared() ? route('share.show', $this->share_token) : null;
    }

    /** Включает публичную страницу прогресса, выдавая новый непредсказуемый адрес. */
    public function startSharing(): string
    {
        $this->forceFill([
            'share_token' => Str::random(32),
            'shared_at' => now(),
        ])->save();

        return $this->share_token;
    }

    public function stopSharing(): void
    {
        $this->forceFill(['share_token' => null, 'shared_at' => null])->save();
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
