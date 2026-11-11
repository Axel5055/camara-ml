<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesReports
{
    /**
     * Livewire boot hook: runs on the first load and on every later request of the component.
     */
    public function bootAuthorizesReports(): void
    {
        abort_unless(Gate::allows('view-reports'), 403);
    }
}
