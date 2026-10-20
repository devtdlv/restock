<?php

namespace App\Livewire\Reports;

use App\Services\Reports\InventoryReportService;
use App\Services\Settings\SettingsService;
use App\Support\AppDisplay;
use App\Support\CsvExporter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Low Stock Report - StockPilot')]
class LowStock extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'active';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function export(InventoryReportService $inventoryReports): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $filename = 'low-stock-report-'.now()->format('Ymd-His').'.csv';

        return CsvExporter::download(
            $filename,
            ['Product', 'SKU', 'Quantity on hand', 'Reorder level', 'Suggested replenishment', 'Status'],
            $inventoryReports->lowStockExportRows($this->search, $this->status),
        );
    }

    public function render(InventoryReportService $inventoryReports, SettingsService $settings, AppDisplay $display)
    {
        return view('livewire.reports.low-stock', [
            'products' => $inventoryReports->lowStockPaginated(
                $this->search,
                $this->status,
                $settings->defaultPerPage(),
            ),
            'inventoryReports' => $inventoryReports,
            'display' => $display,
        ])->layoutData(['header' => 'Low Stock Report']);
    }
}
