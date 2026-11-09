<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_includes_pwa_manifest_and_service_worker_registration(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('rel="manifest" href="'.asset('manifest.webmanifest').'"', false)
            ->assertSee(asset('js/register-service-worker.js'), false);
    }

    public function test_pwa_manifest_service_worker_and_offline_page_are_available(): void
    {
        $manifestPath = public_path('manifest.webmanifest');
        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/login', $manifest['start_url']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        $this->assertFileExists(public_path('service-worker.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('js/register-service-worker.js'));
    }
}
