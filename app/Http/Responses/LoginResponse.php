<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * After signing in, show the welcome animation and let it continue to the page the user was heading to.
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $request->session()->put('welcome_destination', $request->session()->pull('url.intended', url(config('fortify.home'))));

        return redirect()->route('welcome.user');
    }
}
