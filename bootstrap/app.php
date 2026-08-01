<?php

use App\Http\Middleware\CheckRole;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway terminates TLS at its edge proxy. Trust the forwarded
        // scheme/host so generated asset and route URLs stay HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A browser can keep an encrypted session cookie from before APP_KEY
        // or session settings changed. Clear only that stale cookie instead
        // of returning a production 500 during the Google callback.
        $exceptions->render(function (DecryptException $exception, Request $request) {
            if (! $request->is('login') && ! $request->is('auth/google*') && ! $request->is('aktivasi-siswa')) {
                return null;
            }

            Log::notice('Discarded an unreadable guest session cookie.', [
                'path' => $request->path(),
                'exception' => $exception::class,
            ]);

            return redirect()->route('login')
                ->withoutCookie((string) config('session.cookie'), config('session.path', '/'), config('session.domain'))
                ->withErrors(['email' => 'Sesi browser telah diperbarui. Silakan lanjutkan dengan Google sekali lagi.']);
        });
    })->create();
