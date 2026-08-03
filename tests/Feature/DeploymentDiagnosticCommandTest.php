<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentDiagnosticCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_production_preflight_reports_local_values_without_failing(): void
    {
        config()->set('app.env', 'testing');
        config()->set('app.debug', true);
        config()->set('auth.local_login_enabled', true);
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect', 'http://127.0.0.1:8000/auth/google/callback');
        config()->set('session.driver', 'array');
        config()->set('session.secure', false);

        $this->artisan('library:deploy-check')
            ->expectsOutputToContain('Konfigurasi wajib tidak memiliki kegagalan')
            ->assertExitCode(0);
    }

    public function test_production_preflight_fails_when_required_values_are_missing(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);
        config()->set('app.key', 'base64:invalid');
        config()->set('app.url', 'https://perpustakaan.sekolah.sch.id');
        config()->set('auth.local_login_enabled', true);
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect', 'https://perpustakaan.sekolah.sch.id/auth/google/callback');
        config()->set('session.driver', 'array');
        config()->set('session.secure', false);

        $this->artisan('library:deploy-check')
            ->expectsOutputToContain('Belum siap produksi')
            ->assertExitCode(1);
    }
}
