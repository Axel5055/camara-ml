<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresSuperAdmin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Auditoría')]
class AuditLogs extends Component
{
    use RequiresSuperAdmin, WithPagination;

    public string $event = '';

    public ?int $userId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('event', 'userId', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    /**
     * Audit entries matching every active filter.
     *
     * @return Builder<AuditLog>
     */
    private function filteredLogs(): Builder
    {
        return AuditLog::query()
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', 'like', "%{$this->event}%"))
            ->when($this->userId, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->where('created_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->where('created_at', '<=', $this->dateTo.' 23:59:59'));
    }

    public function render(): View
    {
        $summary = $this->filteredLogs()->toBase()
            ->selectRaw('count(*) as total, count(distinct user_id) as users, count(distinct event) as events, coalesce(sum(created_at >= ?), 0) as today', [now()->startOfDay()])
            ->first();

        return view('livewire.admin.audit-logs', [
            'logs' => $this->filteredLogs()
                ->with('user:id,name')
                ->orderByDesc('id')
                ->paginate(30),
            'totals' => [
                'total' => (int) $summary->total,
                'today' => (int) $summary->today,
                'users' => (int) $summary->users,
                'events' => (int) $summary->events,
            ],
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'hasFilters' => $this->event !== '' || $this->userId || $this->dateFrom !== '' || $this->dateTo !== '',
        ]);
    }
}
