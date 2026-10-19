<?php

namespace App\Livewire;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\Reports\InventoryReportService;
use App\Support\AppDisplay;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard - StockPilot')]
class Dashboard extends Component
{
    public function render(InventoryReportService $inventoryReports, AppDisplay $display)
    {
        $metrics = $inventoryReports->overviewMetrics();

        $outstandingPurchaseOrders = PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Ordered,
                PurchaseOrderStatus::PartiallyReceived,
            ])
            ->count();

        return view('livewire.dashboard', [
            'metrics' => $metrics,
            'outstandingPurchaseOrders' => $outstandingPurchaseOrders,
            'inventoryValueDisplay' => $display->money($metrics['inventory_value_at_cost']),
        ])->layoutData(['header' => 'Dashboard']);
    }
}
