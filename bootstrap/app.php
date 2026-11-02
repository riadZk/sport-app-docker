<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = $e->getMessage() ?: 'Resource not found.';

                // Clean up model not found messages
                // "No query results for model [App\Models\Game] 99999." → "Game not found."
                if (str_contains($message, 'No query results for model')) {
                    $fqcn = trim(str_replace(['No query results for model [', ']'], '', $message));
                    $fqcn = explode(' ', $fqcn)[0]; // drop the trailing id
                    $model = class_basename($fqcn);
                    $message = $model !== '' ? "{$model} not found." : 'Resource not found.';
                }

                return response()->json([
                    'status' => 404,
                    'message' => $message,
                    'results' => null,
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthenticated.',
                    'results' => null,
                ], 401);
            }
        });
    })
    ->create();
