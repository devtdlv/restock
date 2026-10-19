<?php

namespace App\Livewire\Reports;

use App\Enums\PurchaseOrderStatus;
use App\Models\Supplier;
use App\Services\Reports\PurchasingReportService;
use App\Services\Settings\SettingsService;
use App\Support\CsvExporter;
use App\Support\AppDisplay;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Purchasing Report - StockPilot')]
class Purchasing extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $supplierId = '';

    #[Url]
    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierId(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function export(PurchasingReportService $reportService): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $filename = 'purchasing-report-'.now()->format('Ymd-His').'.csv';

        return CsvExporter::download(
            $filename,
            ['PO number', 'Supplier', 'Status', 'Order date', 'Expected delivery', 'Ordered total', 'Received qty', 'Outstanding qty'],
            $reportService->exportRows(
                $this->dateFrom !== '' ? $this->dateFrom : null,
                $this->dateTo !== '' ? $this->dateTo : null,
                $this->supplierId !== '' ? (int) $this->supplierId : null,
                $this->status !== '' ? $this->status : null,
                $this->search,
            ),
        );
    }

    public function render(PurchasingReportService $reportService, SettingsService $settings, AppDisplay $display)
    {
        $dateFrom = $this->dateFrom !== '' ? $this->dateFrom : null;
        $dateTo = $this->dateTo !== '' ? $this->dateTo : null;
        $supplierId = $this->supplierId !== '' ? (int) $this->supplierId : null;
        $status = $this->status !== '' ? $this->status : null;
        $summary = $reportService->summary($dateFrom, $dateTo, $supplierId, $status, $this->search);

        return view('livewire.reports.purchasing', [
            'summary' => $summary,
            'committedOrderedValueDisplay' => $display->money($summary['committed_ordered_value']),
            'orders' => $reportService->ordersPaginated(
                $dateFrom,
                $dateTo,
                $supplierId,
                $status,
                $this->search,
                $settings->defaultPerPage(),
            ),
            'supplierSummary' => $reportService->supplierSummary($dateFrom, $dateTo, $supplierId, $status, $this->search),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => PurchaseOrderStatus::cases(),
            'display' => $display,
        ])->layoutData(['header' => 'Purchasing Report']);
    }
}
