<?php

use App\Exceptions\Order\OrderException;
use App\Exceptions\Payment\PaymentException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Numeric syntax to stay consistent with the throttle:6,1 already
        // used on auth/register and auth/login — no named limiter needed.
        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Chaque sous-classe d'OrderException porte son propre code HTTP
        // (voir OrderException::httpStatus()) — jamais une exception PHP
        // brute ni une trace exposée au client.
        $exceptions->render(function (OrderException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus());
        });

        // Même politique que OrderException — voir PaymentException::httpStatus().
        $exceptions->render(function (PaymentException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus());
        });
    })->create();
