<?php

namespace App\Http\Middleware;

use App\Services\Temperature\PendingTemperatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A warehouse user with pending temperature readings can only record them or sign out. It applies to every web
 * route; Livewire actions are guarded component by component (see BlockComponentsUntilTemperatureIsRecorded).
 */
class EnsureTemperatureRecorded
{
    /**
     * Routes that stay open. The Livewire update route is guarded by the component hook instead, because the
     * capture modal itself works through it.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['temperatures.capture', 'logout', 'home', 'welcome.user', 'app-version', '*livewire.update'];

    public function __construct(private PendingTemperatures $pending) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs(...self::ALLOWED_ROUTES) || $this->pending->for($user)->isEmpty()) {
            return $next($request);
        }

        abort_unless($request->isMethod('GET') && ! $request->expectsJson(), 423, 'Registra la temperatura de tus cámaras para continuar.');

        return redirect()->route('temperatures.capture');
    }
}
