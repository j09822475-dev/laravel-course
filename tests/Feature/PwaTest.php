<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Android предлагает установку приложения только при полном наборе условий:
 * манифест с иконками, service worker и офлайн-ответ. Тест держит их на месте.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_describes_an_installable_app(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display'], 'Без standalone Android не считает приложение устанавливаемым');
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        $this->assertSame('/', $manifest['scope']);

        $sizes = array_column($manifest['icons'], 'sizes');

        $this->assertContains('192x192', $sizes, 'Нужна иконка 192×192');
        $this->assertContains('512x512', $sizes, 'Нужна иконка 512×512');
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'), 'Нужна maskable-иконка под маску Android');

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), "Иконка {$icon['src']} отсутствует");
        }
    }

    /** Эти файлы отдаёт веб-сервер напрямую, поэтому проверяем их наличие на диске. */
    public function test_service_worker_and_icons_are_published(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertSame('image/png', mime_content_type(public_path('icons/icon-192.png')));
    }

    public function test_layout_links_the_manifest_and_theme_colour(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('name="theme-color"', false)
            ->assertSee('apple-touch-icon', false);
    }

    public function test_offline_page_exists_for_the_service_worker_fallback(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertSee('Нет подключения');
    }

    public function test_service_worker_never_caches_answers(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString('/offline', $worker);
    }
}
