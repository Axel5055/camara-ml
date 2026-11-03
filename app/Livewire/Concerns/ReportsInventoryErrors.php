<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InventoryException;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;

trait ReportsInventoryErrors
{
    /**
     * Run a business operation; business-rule failures are shown on the "operation" error bag key.
     */
    protected function attempt(Closure $operation): bool
    {
        $this->resetErrorBag('operation');

        try {
            $operation();

            return true;
        } catch (InventoryException $exception) {
            $this->addError('operation', $exception->getMessage());
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        }

        return false;
    }
}
