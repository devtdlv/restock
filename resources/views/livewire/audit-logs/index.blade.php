<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Audit Logs</h1>
            <p class="mt-1 text-sm text-slate-500">
                Read-only history of catalog create, update, and delete events. Inventory quantity changes are recorded under
                <a href="{{ route('stock-movements.index') }}" class="font-medium text-brand-700 hover:text-brand-800" wire:navigate>Stock Movements</a>.
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-4 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <label for="audit-search" class="sr-only">Search audit logs</label>
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="audit-search"
                    type="search"
                    placeholder="Entity ID or user…"
                />
            </div>

            <div>
                <label for="audit-action" class="sr-only">Filter by action</label>
                <select
                    wire:model.live="action"
                    id="audit-action"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All actions</option>
                    @foreach ($actions as $auditAction)
                        <option value="{{ $auditAction->value }}">{{ $auditAction->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="audit-entity-type" class="sr-only">Filter by entity type</label>
                <select
                    wire:model.live="entityType"
                    id="audit-entity-type"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All entity types</option>
                    @foreach ($entityTypes as $type => $label)
                        <option value="{{ $type }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="audit-user" class="sr-only">Filter by user</label>
                <select
                    wire:model.live="userId"
                    id="audit-user"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All users</option>
                    @foreach ($filterUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($selectedAudit)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" wire:key="audit-details-{{ $selectedAudit->id }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Audit details</h2>
                    <p class="mt-1 text-sm text-slate-500">Read-only snapshot. Audit records cannot be edited or deleted.</p>
                </div>
                <x-secondary-button wire:click="closeDetails">Close</x-secondary-button>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Action</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $selectedAudit->action->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Entity</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $presenter->entityLabel($selectedAudit) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Entity type</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ \App\Support\AuditPresenter::entityTypeLabel($selectedAudit->entity_type) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Entity ID</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $selectedAudit->entity_id }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Responsible user</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $selectedAudit->user?->name ?? 'System / unavailable' }}
                        @if ($selectedAudit->user)
                            <span class="text-slate-500">({{ $selectedAudit->user->email }})</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Timestamp</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $selectedAudit->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                    </dd>
                </div>
            </dl>

            @if ($selectedAudit->action === \App\Enums\AuditAction::Updated)
                <div class="mt-8">
                    <h3 class="text-sm font-semibold text-slate-900">Changed fields</h3>
                    @if (count($selectedChanges) === 0)
                        <p class="mt-2 text-sm text-slate-500">No field differences were recorded for this update.</p>
                    @else
                        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">Field</th>
                                        <th class="px-4 py-3 font-medium">Previous</th>
                                        <th class="px-4 py-3 font-medium">New</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($selectedChanges as $change)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-slate-900">{{ $change['label'] }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $presenter->formatValue($change['before']) }}</td>
                                            <td class="px-4 py-3 text-slate-900">{{ $presenter->formatValue($change['after']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Previous values</h3>
                    @php($beforeValues = $presenter->safeBefore($selectedAudit))
                    @if ($beforeValues === [])
                        <p class="mt-2 text-sm text-slate-500">None</p>
                    @else
                        <dl class="mt-3 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            @foreach ($beforeValues as $field => $value)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ \App\Support\AuditPresenter::fieldLabel($field) }}</dt>
                                    <dd class="mt-0.5 break-words text-sm text-slate-800">{{ $presenter->formatValue($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">New values</h3>
                    @php($afterValues = $presenter->safeAfter($selectedAudit))
                    @if ($afterValues === [])
                        <p class="mt-2 text-sm text-slate-500">None</p>
                    @else
                        <dl class="mt-3 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            @foreach ($afterValues as $field => $value)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ \App\Support\AuditPresenter::fieldLabel($field) }}</dt>
                                    <dd class="mt-0.5 break-words text-sm text-slate-800">{{ $presenter->formatValue($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($audits->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">
                    {{ ($search !== '' || $action !== '' || $entityType !== '' || $userId !== '') ? 'No audit logs match your filters' : 'No audit logs yet' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    Catalog changes made through Products and Categories appear here. Stock quantity changes stay in Stock Movements.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                            <th class="px-4 py-3 font-medium">Entity</th>
                            <th class="px-4 py-3 font-medium">Summary</th>
                            <th class="px-4 py-3 font-medium text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($audits as $audit)
                            <tr wire:key="audit-{{ $audit->id }}" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ $audit->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $audit->user?->name ?? 'System / unavailable' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700">
                                        {{ $audit->action->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ \App\Support\AuditPresenter::entityTypeLabel($audit->entity_type) }}</div>
                                    @if ($presenter->entityName($audit))
                                        <div class="text-sm text-slate-700">{{ $presenter->entityName($audit) }}</div>
                                    @endif
                                    <div class="text-xs text-slate-500">ID {{ $audit->entity_id }}</div>
                                </td>
                                <td class="max-w-md px-4 py-3 text-slate-600">
                                    {{ $presenter->changeSummary($audit) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="viewDetails({{ $audit->id }})"
                                        class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
                                    >
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($audits->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $audits->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
