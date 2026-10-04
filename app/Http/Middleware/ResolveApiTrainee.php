<?php

namespace App\Http\Middleware;

use App\Models\Trainee;
use App\Services\ProgressSync;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * В API профиль прогресса всегда берётся из аккаунта: у мобильного клиента
 * нет cookie-сессии, поэтому гостевой режим здесь не поддерживается.
 */
class ResolveApiTrainee
{
    public function __construct(protected ProgressSync $sync) {}

    public function handle(Request $request, Closure $next): Response
    {
        $trainee = $this->sync->attach($request->user());

        $request->attributes->set('trainee', $trainee);
        app()->instance(Trainee::class, $trainee);

        return $next($request);
    }
}
