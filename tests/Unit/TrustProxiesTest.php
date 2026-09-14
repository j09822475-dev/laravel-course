<?php

namespace Tests\Unit;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * За туннелем (ngrok, cloudflared) приложение обязано читать X-Forwarded-*,
 * иначе ссылки и ассеты уедут на http://localhost и страница сломается.
 */
class TrustProxiesTest extends TestCase
{
    /** Прогоняет запрос с заголовками прокси через middleware. */
    protected function forward(array $headers, string $remoteAddress = '10.0.0.7'): Request
    {
        $request = Request::create('http://localhost/', server: ['REMOTE_ADDR' => $remoteAddress]);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        (new TrustProxies)->handle($request, fn (Request $passed) => $passed);

        return $request;
    }

    public function test_forwarded_headers_are_ignored_by_default(): void
    {
        config(['app.trusted_proxies' => null]);

        $request = $this->forward([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'attacker.example',
        ]);

        $this->assertFalse($request->isSecure());
        $this->assertSame('localhost', $request->getHost());
    }

    public function test_wildcard_trusts_any_proxy(): void
    {
        config(['app.trusted_proxies' => '*']);

        $request = $this->forward([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'trainer.ngrok-free.app',
        ]);

        $this->assertTrue($request->isSecure());
        $this->assertSame('trainer.ngrok-free.app', $request->getHost());
        $this->assertSame('https://trainer.ngrok-free.app', $request->fullUrl());
    }

    public function test_only_listed_proxies_are_trusted(): void
    {
        config(['app.trusted_proxies' => '10.0.0.7, 10.0.0.8']);

        $trusted = $this->forward(['X-Forwarded-Proto' => 'https'], remoteAddress: '10.0.0.8');
        $this->assertTrue($trusted->isSecure());

        $stranger = $this->forward(['X-Forwarded-Proto' => 'https'], remoteAddress: '203.0.113.5');
        $this->assertFalse($stranger->isSecure());
    }

    public function test_client_ip_comes_from_forwarded_for_behind_trusted_proxy(): void
    {
        config(['app.trusted_proxies' => '*']);

        $request = $this->forward(['X-Forwarded-For' => '203.0.113.9']);

        $this->assertSame('203.0.113.9', $request->ip());
    }
}
