<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Issue #207: the like/chat fetch clients (public/js/alerts_show.js,
        // public/js/experiences_show.js, resources/views/comments/_item.blade.php)
        // all branch on `data.redirect` to bounce a stale-session click to the
        // login page. That contract was never reachable: /like and
        // /like/unlike sit in the `auth` group, so a guest POST is stopped by
        // the Authenticate middleware BEFORE LikeController runs — the
        // controller's own `redirect` 401 branch was dead code, and Laravel's
        // default AuthenticationException render returns
        // {"message":"Unauthenticated."} with no `redirect`/`success`, so the
        // three handlers fell through to the generic "API error" alert. Render
        // JSON auth failures into the shape the clients already consume (a
        // 401 still, so assertUnauthorized and any status branching hold);
        // non-JSON requests return null and keep the middleware's default
        // redirect-to-login behaviour untouched.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.',
                    'redirect' => route('login'),
                ], 401);
            }

            return null;
        });
    })->create();
