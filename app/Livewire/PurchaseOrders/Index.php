<?php

namespace App\Livewire\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOrderException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Purchase Orders - StockPilot')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public bool $showForm = false;

    public ?int $purchaseOrderId = null;

    public string $supplier_id = '';

    public string $order_date = '';

    public string $expected_delivery_date = '';

    public string $notes = '';

    /** @var list<array{product_id: string, ordered_quantity: string, unit_cost: string}> */
    public array $lines = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $editId = request()->query('edit');

        if ($editId !== null && ctype_digit((string) $editId)) {
            $this->edit((int) $editId);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->ensureAuthenticated();
        $this->resetForm();
        $this->order_date = now()->toDateString();
        $this->lines = [$this->emptyLine()];
        $this->showForm = true;
        $this->clearFeedback();
    }

    public function edit(int $purchaseOrderId): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $order = PurchaseOrder::query()
            ->with('items')
            ->findOrFail($purchaseOrderId);

        if (! $order->status->isEditable()) {
            $this->errorMessage = 'Only draft purchase orders can be edited.';

            return;
        }

        $this->purchaseOrderId = $order->id;
        $this->supplier_id = (string) $order->supplier_id;
        $this->order_date = $order->order_date?->toDateString() ?? now()->toDateString();
        $this->expected_delivery_date = $order->expected_delivery_date?->toDateString() ?? '';
        $this->notes = (string) ($order->notes ?? '');
        $this->lines = $order->items
            ->map(fn ($item) => [
                'product_id' => (string) $item->product_id,
                'ordered_quantity' => (string) $item->ordered_quantity,
                'unit_cost' => Money::of($item->unit_cost),
            ])
            ->values()
            ->all();

        if ($this->lines === []) {
            $this->lines = [$this->emptyLine()];
        }

        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function addLine(): void
    {
        $this->ensureAuthenticated();
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        $this->ensureAuthenticated();

        if (count($this->lines) <= 1) {
            $this->addError('lines', 'A purchase order must include at least one line item.');

            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(PurchaseOrderService $purchaseOrders): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $validated = $this->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.ordered_quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);

        $productIds = collect($validated['lines'])->pluck('product_id')->map(fn ($id) => (int) $id);
        if ($productIds->count() !== $productIds->unique()->count()) {
            $this->addError('lines', 'Duplicate products are not allowed on a purchase order.');

            return;
        }

        $items = collect($validated['lines'])
            ->map(fn (array $line) => [
                'product_id' => (int) $line['product_id'],
                'ordered_quantity' => (int) $line['ordered_quantity'],
                'unit_cost' => Money::of($line['unit_cost']),
            ])
            ->values()
            ->all();

        try {
            if ($this->purchaseOrderId) {
                $order = PurchaseOrder::query()->findOrFail($this->purchaseOrderId);
                $purchaseOrders->updateDraft(
                    $order,
                    auth()->user(),
                    (int) $validated['supplier_id'],
                    $items,
                    $validated['order_date'],
                    $validated['expected_delivery_date'] ?: null,
                    $validated['notes'] ?? null,
                );
                $this->statusMessage = 'Purchase order updated successfully.';
            } else {
                $order = $purchaseOrders->createDraft(
                    auth()->user(),
                    (int) $validated['supplier_id'],
                    $items,
                    $validated['order_date'],
                    $validated['expected_delivery_date'] ?: null,
                    $validated['notes'] ?? null,
                );
                $this->statusMessage = "Purchase order {$order->number} created as draft.";
            }
        } catch (InvalidPurchaseOrderException $exception) {
            $this->errorMessage = $exception->getMessage();

            return;
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function render()
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.Str::lower($this->search).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw('LOWER(number) LIKE ?', [$term])
                        ->orWhereHas('supplier', function ($supplierQuery) use ($term) {
                            $supplierQuery->whereRaw('LOWER(name) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                        });
                });
            })
            ->when($this->status !== '', function ($query) {
                $query->where('status', $this->status);
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(10);

        $supplierQuery = Supplier::query()->orderBy('name');
        if ($this->purchaseOrderId === null) {
            $supplierQuery->where('is_active', true);
        }

        $productIdsOnForm = collect($this->lines)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $products = Product::query()
            ->where(function ($query) use ($productIdsOnForm) {
                $query->where('is_active', true);
                if ($productIdsOnForm !== []) {
                    $query->orWhereIn('id', $productIdsOnForm);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'cost_price', 'is_active']);

        return view('livewire.purchase-orders.index', [
            'orders' => $orders,
            'statuses' => PurchaseOrderStatus::cases(),
            'suppliers' => $supplierQuery->get(['id', 'name', 'code', 'is_active']),
            'products' => $products,
            'formGrandTotal' => $this->formGrandTotal(),
        ])->layoutData(['header' => 'Purchase Orders']);
    }

    protected function formGrandTotal(): string
    {
        $totals = [];

        foreach ($this->lines as $line) {
            $quantity = (int) ($line['ordered_quantity'] ?? 0);
            $unitCost = trim((string) ($line['unit_cost'] ?? ''));

            if ($quantity <= 0 || $unitCost === '' || ! is_numeric($unitCost)) {
                continue;
            }

            try {
                $totals[] = Money::multiply(Money::of($unitCost), $quantity);
            } catch (\Throwable) {
                continue;
            }
        }

        return $totals === [] ? Money::of('0') : Money::add(...$totals);
    }

    /**
     * @return array{product_id: string, ordered_quantity: string, unit_cost: string}
     */
    protected function emptyLine(): array
    {
        return [
            'product_id' => '',
            'ordered_quantity' => '',
            'unit_cost' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->reset([
            'showForm',
            'purchaseOrderId',
            'supplier_id',
            'order_date',
            'expected_delivery_date',
            'notes',
            'lines',
        ]);

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
