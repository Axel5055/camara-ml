<?php

namespace App\Livewire\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait SavesAuditedModels
{
    /**
     * Fill and save a catalog model, leaving the before/after values in the audit trail.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function saveAudited(Model $model, array $attributes, string $event): void
    {
        $model->fill($attributes);

        $created = ! $model->exists;
        $old = $created ? null : array_intersect_key($model->getOriginal(), $model->getDirty());

        $model->save();

        AuditLog::record(
            Auth::user(),
            $event.($created ? '.created' : '.updated'),
            $model,
            $old,
            $created ? $model->getAttributes() : $model->getChanges(),
        );
    }
}
