<?php

namespace App\Livewire\Reports;

use App\Services\Reports\InventoryReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reports - StockPilot')]
class Index extends Component
{
    public function render(InventoryReportService $inventoryReports)
    {
        return view('livewire.reports.index', [
            'metrics' => $inventoryReports->overviewMetrics(),
            'sections' => [
                [
                    'title' => 'Low Stock Report',
                    'description' => 'Products at or below reorder level, including out-of-stock items.',
                    'route' => 'reports.low-stock',
                ],
                [
                    'title' => 'Stock Movement Report',
                    'description' => 'Historical stock in, stock out, adjustments, and opening balances.',
                    'route' => 'reports.stock-movements',
                ],
                [
                    'title' => 'Purchasing Report',
                    'description' => 'Purchase order status, committed value, and supplier summaries.',
                    'route' => 'reports.purchasing',
                ],
            ],
        ])->layoutData(['header' => 'Reports']);
    }
}
