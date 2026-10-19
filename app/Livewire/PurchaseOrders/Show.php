<?php

namespace App\Livewire\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOrderException;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Order - StockPilot')]
class Show extends Component
{
    public PurchaseOrder $purchaseOrder;

    public bool $confirmingMarkOrdered = false;

    public bool $confirmingCancel = false;

    public bool $showReceiveForm = false;

    public bool $confirmingReceive = false;

    /** @var array<int|string, string> */
    public array $receiveQuantities = [];

    public string $receiptKey = '';

    public string $receiveNotes = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder->load([
            'supplier',
            'items.product',
            'receipts.items.purchaseOrderItem.product',
            'receipts.items.stockMovement',
            'receipts.user',
            'creator',
        ]);
    }

    public function confirmMarkOrdered(): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();
        $this->closeReceivePanels();
        $this->confirmingCancel = false;
        $this->confirmingMarkOrdered = true;
    }

    public function cancelMarkOrdered(): void
    {
        $this->confirmingMarkOrdered = false;
    }

    public function markOrdered(PurchaseOrderService $purchaseOrders): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        try {
            $this->purchaseOrder = $purchaseOrders->markOrdered(
                $this->purchaseOrder,
                auth()->user(),
            )->load([
                'supplier',
                'items.product',
                'receipts.items.purchaseOrderItem.product',
                'receipts.items.stockMovement',
                'receipts.user',
                'creator',
            ]);
            $this->statusMessage = "Purchase order {$this->purchaseOrder->number} marked as ordered.";
        } catch (InvalidPurchaseOrderException $exception) {
            $this->errorMessage = $exception->getMessage();
        }

        $this->confirmingMarkOrdered = false;
    }

    public function confirmCancelOrder(): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();
        $this->closeReceivePanels();
        $this->confirmingMarkOrdered = false;
        $this->confirmingCancel = true;
    }

    public function cancelCancelOrder(): void
    {
        $this->confirmingCancel = false;
    }

    public function cancelOrder(PurchaseOrderService $purchaseOrders): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        try {
            $this->purchaseOrder = $purchaseOrders->cancel(
                $this->purchaseOrder,
                auth()->user(),
            )->load([
                'supplier',
                'items.product',
                'receipts.items.purchaseOrderItem.product',
                'receipts.items.stockMovement',
                'receipts.user',
                'creator',
            ]);
            $this->statusMessage = "Purchase order {$this->purchaseOrder->number} cancelled.";
        } catch (InvalidPurchaseOrderException $exception) {
            $this->errorMessage = $exception->getMessage();
        }

        $this->confirmingCancel = false;
    }

    public function openReceiveForm(): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();
        $this->confirmingMarkOrdered = false;
        $this->confirmingCancel = false;

        $this->purchaseOrder->refresh()->load(['items.product']);

        if (! $this->purchaseOrder->status->allowsReceiving()) {
            $this->errorMessage = 'This purchase order cannot receive inventory in its current status.';

            return;
        }

        $this->receiveQuantities = [];
        foreach ($this->purchaseOrder->items as $item) {
            $this->receiveQuantities[$item->id] = '';
        }

        // Fresh key only when starting a genuinely new receipt operation.
        $this->receiptKey = (string) Str::uuid();
        $this->receiveNotes = '';
        $this->confirmingReceive = false;
        $this->showReceiveForm = true;
        $this->resetValidation();
    }

    public function cancelReceive(): void
    {
        $this->closeReceivePanels();
    }

    public function reviewReceive(): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $this->validateReceiveQuantities();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->confirmingReceive = true;
    }

    public function backToReceiveForm(): void
    {
        // Keep the same receiptKey for this intended receipt operation.
        $this->confirmingReceive = false;
    }

    public function submitReceive(PurchaseOrderService $purchaseOrders): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $quantities = $this->validateReceiveQuantities();

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->confirmingReceive = false;

            return;
        }

        try {
            $receipt = $purchaseOrders->receive(
                $this->purchaseOrder,
                $quantities,
                auth()->user(),
                $this->receiptKey,
                $this->receiveNotes !== '' ? $this->receiveNotes : null,
            );

            $this->purchaseOrder = $this->purchaseOrder->fresh()->load([
                'supplier',
                'items.product',
                'receipts.items.purchaseOrderItem.product',
                'receipts.items.stockMovement',
                'receipts.user',
                'creator',
            ]);

            $this->statusMessage = sprintf(
                'Receipt recorded for %s. Status is now %s.',
                $this->purchaseOrder->number,
                $this->purchaseOrder->status->label(),
            );

            $this->closeReceivePanels();
            unset($receipt);
        } catch (InvalidPurchaseOrderException $exception) {
            // Retain receiptKey so retries of this same operation stay idempotent.
            $this->errorMessage = $exception->getMessage();
            $this->confirmingReceive = false;
        }
    }

    public function render(\App\Support\AppDisplay $display)
    {
        $this->purchaseOrder->loadMissing([
            'supplier',
            'items.product',
            'receipts.items.purchaseOrderItem.product',
            'receipts.items.stockMovement',
            'receipts.user',
            'creator',
        ]);

        $receiptPreview = [];
        if ($this->confirmingReceive) {
            foreach ($this->purchaseOrder->items as $item) {
                $qty = (int) ($this->receiveQuantities[$item->id] ?? 0);
                if ($qty > 0) {
                    $receiptPreview[] = [
                        'product' => $item->product?->name ?? 'Product #'.$item->product_id,
                        'quantity' => $qty,
                        'outstanding' => $item->outstandingQuantity(),
                    ];
                }
            }
        }

        return view('livewire.purchase-orders.show', [
            'order' => $this->purchaseOrder,
            'canEdit' => $this->purchaseOrder->status->isEditable(),
            'canMarkOrdered' => $this->purchaseOrder->status === PurchaseOrderStatus::Draft,
            'canCancel' => $this->purchaseOrder->status->canBeCancelled()
                && ! $this->purchaseOrder->hasAnyReceivedQuantity(),
            'canReceive' => $this->purchaseOrder->status->allowsReceiving(),
            'receiptPreview' => $receiptPreview,
            'display' => $display,
        ])->layoutData(['header' => $this->purchaseOrder->number]);
    }

    /**
     * @return array<int, int>
     */
    protected function validateReceiveQuantities(): array
    {
        $this->resetValidation();

        $rules = [
            'receiptKey' => ['required', 'string', 'max:64'],
            'receiveNotes' => ['nullable', 'string', 'max:1000'],
            'receiveQuantities' => ['required', 'array'],
        ];

        foreach ($this->purchaseOrder->items as $item) {
            $rules['receiveQuantities.'.$item->id] = [
                'nullable',
                'integer',
                'min:0',
                'max:'.$item->outstandingQuantity(),
            ];
        }

        $this->validate($rules);

        $quantities = [];
        foreach ($this->purchaseOrder->items as $item) {
            $raw = $this->receiveQuantities[$item->id] ?? '';
            if ($raw === '' || $raw === null) {
                continue;
            }

            $qty = (int) $raw;
            if ($qty > 0) {
                $quantities[$item->id] = $qty;
            }
        }

        if ($quantities === []) {
            $this->addError('receiveQuantities', 'Enter at least one positive received quantity.');
        }

        return $quantities;
    }

    protected function closeReceivePanels(): void
    {
        $this->showReceiveForm = false;
        $this->confirmingReceive = false;
        $this->receiveQuantities = [];
        $this->receiptKey = '';
        $this->receiveNotes = '';
        $this->resetValidation();
    }

    protected function clearFeedback(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    protected function ensureAuthenticated(): void
    {
        abort_unless(auth()->check(), 403);
    }
}
