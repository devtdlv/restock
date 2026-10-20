<?php

namespace App\Livewire\AuditLogs;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditPresenter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Audit Logs - StockPilot')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $entityType = '';

    #[Url]
    public string $userId = '';

    public ?int $selectedAuditId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function updatingEntityType(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function viewDetails(int $auditId): void
    {
        abort_unless(auth()->check(), 403);

        AuditLog::query()->findOrFail($auditId);

        $this->selectedAuditId = $auditId;
    }

    public function closeDetails(): void
    {
        $this->selectedAuditId = null;
    }

    public function render(AuditPresenter $presenter, \App\Support\AppDisplay $display)
    {
        $audits = AuditLog::query()
            ->with('user')
            ->when($this->action !== '', function ($query) {
                $query->where('action', $this->action);
            })
            ->when($this->entityType !== '', function ($query) {
                $query->where('entity_type', $this->entityType);
            })
            ->when($this->userId !== '', function ($query) {
                $query->where('user_id', (int) $this->userId);
            })
            ->when($this->search !== '', function ($query) {
                $term = trim($this->search);
                $like = '%'.Str::lower($term).'%';

                $query->where(function ($inner) use ($term, $like) {
                    if (ctype_digit($term)) {
                        $inner->where('entity_id', (int) $term);
                    }

                    $inner->orWhereHas('user', function ($userQuery) use ($like) {
                        $userQuery->where(function ($userInner) use ($like) {
                            $userInner->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                        });
                    });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(app(\App\Services\Settings\SettingsService::class)->defaultPerPage());

        $selectedAudit = null;
        $selectedChanges = [];

        if ($this->selectedAuditId !== null) {
            $selectedAudit = AuditLog::query()
                ->with('user')
                ->find($this->selectedAuditId);

            if ($selectedAudit) {
                $selectedChanges = $presenter->changedFields($selectedAudit);
            } else {
                $this->selectedAuditId = null;
            }
        }

        $filterUserIds = AuditLog::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->limit(100)
            ->pluck('user_id');

        return view('livewire.audit-logs.index', [
            'audits' => $audits,
            'actions' => AuditAction::cases(),
            'entityTypes' => AuditPresenter::entityTypeOptions(),
            'filterUsers' => User::query()
                ->whereIn('id', $filterUserIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'presenter' => $presenter,
            'display' => $display,
            'selectedAudit' => $selectedAudit,
            'selectedChanges' => $selectedChanges,
        ])->layoutData(['header' => 'Audit Logs']);
    }
}
