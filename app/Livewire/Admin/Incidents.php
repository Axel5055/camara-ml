<?php

namespace App\Livewire\Admin;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Models\AuditLog;
use App\Models\Incident;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Incidencias')]
class Incidents extends Component
{
    use InteractsWithChambers, WithPagination;

    /** open | resolved | all */
    public string $status = 'open';

    public string $search = '';

    /** all | an IncidentType value */
    public string $type = 'all';

    #[Locked]
    public ?int $resolvingId = null;

    public string $resolution = '';

    /**
     * Livewire boot hook: runs on the first load and on every later request of the component.
     */
    public function boot(): void
    {
        abort_unless(Gate::allows('view-incidents'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type');
        $this->status = 'open';
        $this->resetPage();
    }

    /**
     * Incidents matching the search and type (the status filter is applied separately so the cards stay complete).
     *
     * @return Builder<Incident>
     */
    private function searchedIncidents(): Builder
    {
        $search = trim($this->search);
        $visible = $this->visibleChamberIds();

        return Incident::query()
            ->when($visible !== null, fn (Builder $query) => $query->whereHas('transfer', fn (Builder $transfer) => $transfer
                ->whereIn('origin_chamber_id', $visible)->orWhereIn('dest_chamber_id', $visible)))
            ->when(IncidentType::tryFrom($this->type), fn (Builder $query, IncidentType $type) => $query->where('type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('description', 'like', "%{$search}%")
                ->orWhereHas('transfer', fn (Builder $transfer) => $transfer->where('reference', 'like', "%{$search}%"))
                ->orWhereHas('box', fn (Builder $box) => $box->where('barcode', 'like', '%'.strtoupper($search).'%'))));
    }

    public function askResolve(int $id): void
    {
        Gate::authorize('resolve', Incident::query()->with('transfer')->findOrFail($id));

        $this->resolvingId = $id;
        $this->resolution = '';
        $this->resetValidation();
        Flux::modal('resolve-incident')->show();
    }

    public function resolve(): void
    {
        $incident = Incident::query()->with('transfer')->findOrFail($this->resolvingId);
        Gate::authorize('resolve', $incident);

        $this->validate(['resolution' => ['required', 'string', 'min:5', 'max:2000']]);

        if ($incident->status === IncidentStatus::Resolved) {
            return;
        }

        $incident->update([
            'status' => IncidentStatus::Resolved,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
            'resolution' => $this->resolution,
        ]);

        AuditLog::record(Auth::user(), 'incident.resolved', $incident, ['status' => 'open'], ['status' => 'resolved', 'resolution' => $this->resolution]);

        Flux::modal('resolve-incident')->close();
        Flux::toast(text: 'Incidencia resuelta.', variant: 'success');
    }

    public function render(): View
    {
        $byStatus = $this->searchedIncidents()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $resolving = $this->resolvingId === null ? null : Incident::query()
            ->with(['transfer.originChamber.branch', 'transfer.destChamber.branch', 'box'])
            ->find($this->resolvingId);

        return view('livewire.admin.incidents', [
            'resolving' => $resolving !== null && Gate::allows('resolve', $resolving) ? $resolving : null,
            'isSuperAdmin' => Auth::user()->isSuperAdmin(),
            'incidents' => $this->searchedIncidents()
                ->with(['transfer:id,reference,origin_chamber_id,dest_chamber_id', 'transfer.originChamber', 'transfer.destChamber', 'box:id,barcode', 'resolver:id,name'])
                ->when(IncidentStatus::tryFrom($this->status), fn (Builder $query, IncidentStatus $status) => $query->where('status', $status))
                ->orderByRaw("status = 'open' desc")->orderByDesc('id')
                ->paginate(20),
            'totals' => [
                'open' => (int) ($byStatus[IncidentStatus::Open->value] ?? 0),
                'resolved' => (int) ($byStatus[IncidentStatus::Resolved->value] ?? 0),
                'missing' => $this->searchedIncidents()->where('status', IncidentStatus::Open)->where('type', IncidentType::Missing)->count(),
                'weight' => $this->searchedIncidents()->where('status', IncidentStatus::Open)->where('type', IncidentType::WeightDifference)->count(),
            ],
            'types' => IncidentType::cases(),
            'hasFilters' => trim($this->search) !== '' || $this->type !== 'all' || $this->status !== 'open',
        ]);
    }
}
