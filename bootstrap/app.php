<?php

use App\Http\Middleware\CheckRole;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The hosting edge terminates TLS before PHP receives the request.
        // Trust forwarded scheme/host so generated URLs stay on the HTTPS
        // custom domain.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The Google provider returns to this app through a browser redirect.
        // If an infrastructure failure happens before the controller can catch
        // it (for example while a session is being read), avoid showing
        // Laravel's generic production 500 page. The exception is still sent
        // to the provider log stream with a safe, actionable label.
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if (! $request->is('auth/google*') || $exception instanceof DecryptException) {
                return null;
            }

            Log::error('Unhandled Google OAuth request failure.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'path' => $request->path(),
            ]);

            return response()->view('errors.oauth-unavailable', status: 503);
        });

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
