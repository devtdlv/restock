<?php

namespace App\Livewire;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
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

        $attentionProducts = Product::query()
            ->where('is_active', true)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->orderBy('quantity_on_hand')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'sku', 'quantity_on_hand', 'reorder_level', 'image_path']);

        $attentionOrders = PurchaseOrder::query()
            ->with('supplier:id,name,code')
            ->whereIn('status', [
                PurchaseOrderStatus::Ordered,
                PurchaseOrderStatus::PartiallyReceived,
            ])
            ->orderBy('expected_delivery_date')
            ->orderBy('number')
            ->limit(5)
            ->get();

        $recentMovements = StockMovement::query()
            ->with(['product:id,name,sku,image_path', 'user:id,name'])
            ->latest('id')
            ->limit(8)
            ->get();

        return view('livewire.dashboard', [
            'metrics' => $metrics,
            'outstandingPurchaseOrders' => $outstandingPurchaseOrders,
            'inventoryValueDisplay' => $display->money($metrics['inventory_value_at_cost']),
            'attentionProducts' => $attentionProducts,
            'attentionOrders' => $attentionOrders,
            'recentMovements' => $recentMovements,
            'display' => $display,
        ])->layoutData(['header' => 'Dashboard']);
    }
}
