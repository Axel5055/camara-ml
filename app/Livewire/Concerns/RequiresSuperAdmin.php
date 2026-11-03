<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait RequiresSuperAdmin
{
    /**
     * Livewire boot hook: runs on the first load and on every later request of the component.
     */
    public function bootRequiresSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }
}
