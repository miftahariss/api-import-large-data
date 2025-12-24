<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware for API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions and return JSON
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $response = [
                    'message' => $e->getMessage() ?: 'An error occurred',
                ];
                
                $statusCode = 500;

                // Authentication Exception
                if ($e instanceof AuthenticationException) {
                    $response['message'] = 'Unauthenticated. Please provide a valid token.';
                    $statusCode = 401;
                }
                // Validation Exception
                elseif ($e instanceof ValidationException) {
                    $response['message'] = 'Validation failed';
                    $response['errors'] = $e->errors();
                    $statusCode = 422;
                }
                // Not Found Exception
                elseif ($e instanceof NotFoundHttpException) {
                    $response['message'] = 'Resource not found';
                    $statusCode = 404;
                }
                // Method Not Allowed
                elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                    $response['message'] = 'Method not allowed';
                    $statusCode = 405;
                }
                // Other HTTP Exceptions
                elseif (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                }

                // Add debug info in development
                if (config('app.debug')) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ];
                }

                return response()->json($response, $statusCode);
            }
        });
    })->create();
