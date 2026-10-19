<?php

namespace App\Livewire\StockMovements;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Services\Settings\SettingsService;
use App\Support\AppDisplay;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Movements - StockPilot')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function render(SettingsService $settings, AppDisplay $display)
    {
        $movements = StockMovement::query()
            ->with(['product', 'user'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.Str::lower($this->search).'%';
                $query->whereHas('product', function ($productQuery) use ($term) {
                    $productQuery->where(function ($inner) use ($term) {
                        $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(sku) LIKE ?', [$term]);
                    });
                });
            })
            ->when($this->type !== '', function ($query) {
                $query->where('type', $this->type);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($settings->defaultPerPage());

        return view('livewire.stock-movements.index', [
            'movements' => $movements,
            'movementTypes' => StockMovementType::cases(),
            'display' => $display,
        ])->layoutData(['header' => 'Stock Movements']);
    }
}
