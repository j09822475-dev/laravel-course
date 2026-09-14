<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Доверие заголовкам X-Forwarded-* за обратным прокси (ngrok, cloudflared,
 * балансировщик). Без этого приложение считает соединение http и строит
 * ссылки на http://localhost, из-за чего ломаются ассеты и редиректы.
 *
 * Список прокси берётся из конфигурации (переменная TRUSTED_PROXIES):
 * «*» — доверять любому прокси (туннель с плавающим адресом),
 * список IP через запятую — доверять только им,
 * пусто — не доверять никому (поведение по умолчанию).
 */
class TrustProxies extends Middleware
{
    public function __construct()
    {
        $proxies = config('app.trusted_proxies');

        if (blank($proxies)) {
            return;
        }

        $this->proxies = $proxies === '*'
            ? '*'
            : array_values(array_filter(array_map(trim(...), explode(',', (string) $proxies))));
    }
}
