<?php

namespace App\Services;

use App\Models\ReviewCard;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Связывает гостевой прогресс с аккаунтом.
 *
 * Гость тренируется без регистрации, поэтому при входе его результаты нельзя
 * терять: профиль без аккаунта либо присваивается пользователю целиком, либо
 * вливается в уже существующий профиль.
 */
class ProgressSync
{
    /**
     * Профиль пользователя: присваивает гостевой, если у аккаунта его ещё нет,
     * иначе переносит в него прогресс гостя.
     */
    public function attach(User $user, ?Trainee $guest = null): Trainee
    {
        $existing = $user->trainee()->first();

        if (! $existing) {
            if ($guest && $guest->isGuest()) {
                $guest->forceFill([
                    'user_id' => $user->id,
                    // Гость тренировался под именем по умолчанию — подставляем имя из аккаунта,
                    // иначе оно попадёт в публичную ссылку на прогресс.
                    'name' => $this->displayName($guest, $user),
                ])->save();

                return $guest->refresh();
            }

            return Trainee::create([
                'user_id' => $user->id,
                'name' => $user->name,
            ]);
        }

        if ($guest && $guest->isGuest() && $guest->isNot($existing)) {
            $this->merge($guest, $existing);
        }

        return $existing->refresh();
    }

    /**
     * Переносит сессии и карточки повторения из одного профиля в другой.
     *
     * Карточки по одному вопросу объединяются: берётся более поздний результат,
     * потому что он точнее отражает текущее состояние памяти кандидата.
     */
    public function merge(Trainee $source, Trainee $target): void
    {
        if ($source->is($target)) {
            return;
        }

        DB::transaction(function () use ($source, $target) {
            $source->sessions()->update(['trainee_id' => $target->id]);

            $existing = $target->reviewCards()->get()->keyBy('question_id');

            foreach ($source->reviewCards()->get() as $card) {
                $rival = $existing->get($card->question_id);

                if (! $rival) {
                    $card->forceFill(['trainee_id' => $target->id])->save();

                    continue;
                }

                if ($this->isFresher($card, $rival)) {
                    $rival->forceFill($card->only([
                        'ease_factor', 'interval_days', 'repetitions', 'lapses',
                        'last_rating', 'due_on', 'last_reviewed_at',
                    ]))->save();
                }

                $card->delete();
            }

            $source->delete();
        });
    }

    /** Своё имя кандидата важнее имени аккаунта — его меняют осознанно. */
    protected function displayName(Trainee $trainee, User $user): string
    {
        return in_array($trainee->name, ['', 'Кандидат'], strict: true) ? $user->name : $trainee->name;
    }

    protected function isFresher(ReviewCard $card, ReviewCard $rival): bool
    {
        if (! $card->last_reviewed_at) {
            return false;
        }

        return ! $rival->last_reviewed_at || $card->last_reviewed_at->gt($rival->last_reviewed_at);
    }
}
