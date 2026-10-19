<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Stock\StockMovementService;
use Illuminate\Console\Command;

class ReconcileLegacyOpeningBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:reconcile-legacy-opening-balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create system-generated legacy opening balance movements for products with existing stock and no movement history';

    /**
     * Execute the console command.
     */
    public function handle(StockMovementService $stockMovements): int
    {
        $candidates = Product::query()
            ->where('quantity_on_hand', '>', 0)
            ->whereDoesntHave('stockMovements')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No products required legacy opening balance reconciliation.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($candidates as $product) {
            $movement = $stockMovements->recordLegacyOpeningBalance($product);

            if ($movement !== null) {
                $created++;
                $this->line(sprintf(
                    'Reconciled %s (%s): legacy opening balance of %d',
                    $product->name,
                    $product->sku,
                    $movement->quantity_after,
                ));
            }
        }

        $this->info("Created {$created} legacy opening balance movement(s).");

        return self::SUCCESS;
    }
}
