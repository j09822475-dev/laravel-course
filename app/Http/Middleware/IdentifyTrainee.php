<?php

namespace App\Http\Middleware;

use App\Models\Trainee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Приложение не требует регистрации: профиль кандидата привязан к сессии браузера.
 * Middleware гарантирует, что в каждом запросе доступен текущий Trainee.
 */
class IdentifyTrainee
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->session()->get('trainee_uuid');

        $trainee = $uuid ? Trainee::firstWhere('uuid', $uuid) : null;

        if (! $trainee) {
            $trainee = Trainee::create(['name' => 'Кандидат']);
            $request->session()->put('trainee_uuid', $trainee->uuid);
        }

        $request->attributes->set('trainee', $trainee);

        app()->instance(Trainee::class, $trainee);
        view()->share('trainee', $trainee);

        return $next($request);
    }
}
