<?php

namespace App\Http\Controllers;

use App\Services\Temperature\PendingTemperatures;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TemperatureCaptureController extends Controller
{
    public function __invoke(Request $request, PendingTemperatures $pending): View|RedirectResponse
    {
        return $pending->for($request->user())->isEmpty()
            ? redirect()->route('dashboard')
            : view('temperatures.capture');
    }
}
