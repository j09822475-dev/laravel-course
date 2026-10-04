<?php

namespace App\Http\Controllers;

use App\Models\Trainee;
use App\Services\ProgressReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Публичная страница прогресса по ссылке-токену.
 *
 * Делиться можно только осознанно: пока кандидат не включил доступ, страницы
 * не существует. Ссылка не содержит идентификаторов и не раскрывает ни почту,
 * ни тексты ответов — только агрегированный прогресс.
 */
class ShareController extends Controller
{
    public function show(string $token, ProgressReport $report): Response
    {
        $owner = Trainee::where('share_token', $token)->firstOrFail();

        return response()->view('share.show', [
            'owner' => $owner,
            'readiness' => $report->readiness($owner),
            'summary' => $report->summary($owner),
            'stats' => $report->topicStats($owner)->filter(fn (array $row) => $row['answered'] > 0)->values(),
            'sessions' => $report->recentSessions($owner, 5),
        ])
            // Секрет лежит в адресе страницы: закрываем её от поисковиков
            // и не отдаём адрес сторонним сайтам в заголовке Referer.
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Trainee $trainee): RedirectResponse
    {
        if (! $trainee->isShared()) {
            $trainee->startSharing();
        }

        return redirect()->route('progress')->with('status', 'Ссылка на прогресс создана.');
    }

    /** Перевыпуск токена обрывает доступ по старой ссылке. */
    public function update(Trainee $trainee): RedirectResponse
    {
        $trainee->startSharing();

        return redirect()->route('progress')->with('status', 'Ссылка обновлена — старая больше не работает.');
    }

    public function destroy(Trainee $trainee): RedirectResponse
    {
        $trainee->stopSharing();

        return redirect()->route('progress')->with('status', 'Доступ по ссылке закрыт.');
    }
}
