<div class="space-y-6">
    <x-page-header title="Audit Logs">
        <x-slot:description>
            Read-only history of catalog create, update, and delete events. Inventory quantity changes are recorded under
            <a href="{{ route('stock-movements.index') }}" class="font-medium text-brand-700 hover:text-brand-800" wire:navigate>Stock Movements</a>.
        </x-slot:description>
    </x-page-header>

    <x-panel>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <x-input-label for="audit-search" value="Search" />
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="audit-search"
                    type="search"
                    placeholder="Entity ID or user…"
                />
            </div>

            <div>
                <x-input-label for="audit-action" value="Action" />
                <x-select-input wire:model.live="action" id="audit-action">
                    <option value="">All actions</option>
                    @foreach ($actions as $auditAction)
                        <option value="{{ $auditAction->value }}">{{ $auditAction->label() }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <x-input-label for="audit-entity-type" value="Entity type" />
                <x-select-input wire:model.live="entityType" id="audit-entity-type">
                    <option value="">All entity types</option>
                    @foreach ($entityTypes as $type => $label)
                        <option value="{{ $type }}">{{ $label }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <x-input-label for="audit-user" value="User" />
                <x-select-input wire:model.live="userId" id="audit-user">
                    <option value="">All users</option>
                    @foreach ($filterUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </x-select-input>
            </div>
        </div>
    </x-panel>

    @if ($selectedAudit)
        <x-form-panel
            title="Audit details"
            subtitle="Read-only snapshot. Audit records cannot be edited or deleted."
            title-id="audit-details-title"
            wire:key="audit-details-{{ $selectedAudit->id }}"
        >
            <div class="mb-5 flex justify-end">
                <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="closeDetails">Close</x-secondary-button>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Action</dt>
                    <dd class="mt-1">
                        <x-badge :variant="$selectedAudit->action->badgeVariant()">{{ $selectedAudit->action->label() }}</x-badge>
                    </dd>
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
                    <dd class="mt-1 text-sm tabular-nums text-slate-900">{{ $selectedAudit->entity_id }}</dd>
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
                    <dd class="mt-1 text-sm tabular-nums text-slate-900">
                        {{ $display->dateTime($selectedAudit->created_at) }}
                    </dd>
                </div>
            </dl>

            @if ($selectedAudit->action === \App\Enums\AuditAction::Updated)
                <div class="mt-8">
                    <h3 class="text-sm font-semibold text-slate-900">Changed fields</h3>
                    @if (count($selectedChanges) === 0)
                        <p class="mt-2 text-sm text-slate-500">No field differences were recorded for this update.</p>
                    @else
                        <div class="mt-3 overflow-hidden rounded-lg border border-slate-200">
                            <x-table caption="Changed audit fields">
                                <x-table.header>
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Field</th>
                                        <th scope="col" class="px-4 py-3">Previous</th>
                                        <th scope="col" class="px-4 py-3">New</th>
                                    </tr>
                                </x-table.header>
                                <x-table.body>
                                    @foreach ($selectedChanges as $change)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-slate-900">{{ $change['label'] }}</td>
                                            <td class="break-words px-4 py-3 text-slate-600">{{ $presenter->formatValue($change['before']) }}</td>
                                            <td class="break-words px-4 py-3 text-slate-900">{{ $presenter->formatValue($change['after']) }}</td>
                                        </tr>
                                    @endforeach
                                </x-table.body>
                            </x-table>
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
        </x-form-panel>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($audits->isEmpty())
            <x-empty-state
                :title="($search !== '' || $action !== '' || $entityType !== '' || $userId !== '') ? 'No audit logs match your filters' : 'No audit logs yet'"
                :description="($search !== '' || $action !== '' || $entityType !== '' || $userId !== '')
                    ? 'Try a different search or clear one of the filters.'
                    : 'Catalog changes made through Products and Categories appear here. Stock quantity changes stay in Stock Movements.'"
            />
        @else
            <x-table caption="Audit log history">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Date</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">User</th>
                        <th scope="col" class="px-4 py-3">Action</th>
                        <th scope="col" class="px-4 py-3">Entity</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Summary</th>
                        <th scope="col" class="px-4 py-3 text-right">Details</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($audits as $audit)
                        <tr wire:key="audit-{{ $audit->id }}" class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600">
                                {{ $display->dateTime($audit->created_at) }}
                                <div class="mt-0.5 text-xs text-slate-500 sm:hidden">
                                    {{ $audit->user?->name ?? 'System / unavailable' }}
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 text-slate-700 sm:table-cell">
                                {{ $audit->user?->name ?? 'System / unavailable' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$audit->action->badgeVariant()">{{ $audit->action->label() }}</x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ \App\Support\AuditPresenter::entityTypeLabel($audit->entity_type) }}</div>
                                @if ($presenter->entityName($audit))
                                    <div class="truncate text-sm text-slate-700">{{ $presenter->entityName($audit) }}</div>
                                @endif
                                <div class="text-xs tabular-nums text-slate-500">ID {{ $audit->entity_id }}</div>
                                <div class="mt-1 max-w-xs truncate text-xs text-slate-500 lg:hidden">
                                    {{ $presenter->changeSummary($audit) }}
                                </div>
                            </td>
                            <td class="hidden max-w-md px-4 py-3 text-slate-600 lg:table-cell">
                                {{ $presenter->changeSummary($audit) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="viewDetails({{ $audit->id }})"
                                    class="min-h-11 rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
                                >
                                    View Details
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </x-table.body>
            </x-table>

            @if ($audits->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $audits->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
