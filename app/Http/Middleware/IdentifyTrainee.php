<?php

namespace App\Http\Middleware;

use App\Models\Trainee;
use App\Services\ProgressSync;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Приложение не требует регистрации: гость тренируется с профилем в сессии браузера.
 * У вошедшего пользователя профиль берётся из аккаунта — тогда прогресс виден
 * с любого устройства. Middleware гарантирует, что текущий Trainee есть всегда.
 */
class IdentifyTrainee
{
    public function __construct(protected ProgressSync $sync) {}

    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->session()->get('trainee_uuid');

        $trainee = $uuid ? Trainee::firstWhere('uuid', $uuid) : null;

        if ($user = $request->user()) {
            // Гостевой прогресс не теряется: он присваивается аккаунту при первом входе.
            $trainee = $this->sync->attach($user, $trainee?->isGuest() ? $trainee : null);
        } elseif (! $trainee || ! $trainee->isGuest()) {
            // Профиль аккаунта не должен достаться гостю после выхода из системы.
            $trainee = Trainee::create(['name' => 'Кандидат']);
        }

        $request->session()->put('trainee_uuid', $trainee->uuid);

        $request->attributes->set('trainee', $trainee);

        app()->instance(Trainee::class, $trainee);
        view()->share('trainee', $trainee);

        return $next($request);
    }
}
