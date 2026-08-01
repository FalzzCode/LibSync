<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthDiagnosticCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_client_confirms_a_google_client_when_google_returns_invalid_grant(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect', 'https://libsync.test/auth/google/callback');
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->artisan('library:diagnose-oauth --verify-client')
            ->expectsOutputToContain('Client ID dan Secret diterima Google')
            ->assertExitCode(0);
    }

    public function test_verify_client_reports_a_rejected_google_client(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'wrong-client-secret');
        config()->set('services.google.redirect', 'https://libsync.test/auth/google/callback');
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->artisan('library:diagnose-oauth --verify-client')
            ->expectsOutputToContain('Google menolak Client ID atau Client Secret')
            ->assertExitCode(1);
    }
}
