<?php

namespace App\Livewire\Reports;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Services\Reports\StockMovementReportService;
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
#[Title('Stock Movement Report - StockPilot')]
class StockMovements extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $productId = '';

    #[Url]
    public string $type = '';

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingProductId(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function export(StockMovementReportService $reportService): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $filename = 'stock-movement-report-'.now()->format('Ymd-His').'.csv';

        return CsvExporter::download(
            $filename,
            ['Timestamp', 'Product', 'SKU', 'Type', 'Quantity change', 'Before', 'After', 'Reason', 'User'],
            $reportService->exportRows(
                $this->dateFrom !== '' ? $this->dateFrom : null,
                $this->dateTo !== '' ? $this->dateTo : null,
                $this->productId !== '' ? (int) $this->productId : null,
                $this->type !== '' ? $this->type : null,
            ),
        );
    }

    public function render(
        StockMovementReportService $reportService,
        SettingsService $settings,
        AppDisplay $display,
    ) {
        return view('livewire.reports.stock-movements', [
            'movements' => $reportService->paginated(
                $this->dateFrom !== '' ? $this->dateFrom : null,
                $this->dateTo !== '' ? $this->dateTo : null,
                $this->productId !== '' ? (int) $this->productId : null,
                $this->type !== '' ? $this->type : null,
                $settings->defaultPerPage(),
            ),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'movementTypes' => StockMovementType::cases(),
            'display' => $display,
        ])->layoutData(['header' => 'Stock Movement Report']);
    }
}
