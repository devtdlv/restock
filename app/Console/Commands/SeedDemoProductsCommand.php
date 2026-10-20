<?php

namespace App\Console\Commands;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Stock\StockMovementService;
use App\Support\Demo\DemoProductCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeedDemoProductsCommand extends Command
{
    protected $signature = 'stockpilot:demo-products
                            {--dry-run : Preview planned changes without writing to the database}
                            {--force : Skip the interactive confirmation prompt}';

    protected $description = 'Create 100 DEMO-prefixed sample products with opening stock via catalog and stock services';

    public function handle(
        ProductCatalogService $products,
        CategoryCatalogService $categories,
        StockMovementService $stockMovements,
    ): int {
        if ($this->laravel->environment('production')) {
            $this->error('Refusing to run stockpilot:demo-products in the production environment.');

            return self::FAILURE;
        }

        $definitions = DemoProductCatalog::definitions();

        if (count($definitions) !== 100) {
            $this->error('Demo catalog must contain exactly 100 products. Found '.count($definitions).'.');

            return self::FAILURE;
        }

        $user = User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->error('No users found. Register or create a user before seeding demo products.');

            return self::FAILURE;
        }

        $existingSkus = Product::query()->pluck('sku')->all();
        $existingSkuSet = array_fill_keys($existingSkus, true);

        $toCreate = [];
        $toSkip = [];
        $collisions = [];

        foreach ($definitions as $definition) {
            $sku = $definition['sku'];

            if (! str_starts_with($sku, DemoProductCatalog::SKU_PREFIX)) {
                $this->error("Catalog SKU [{$sku}] is missing the required DEMO- prefix.");

                return self::FAILURE;
            }

            if (isset($existingSkuSet[$sku])) {
                $toSkip[] = $definition;

                continue;
            }

            // Guard against unexpected non-prefixed collisions (should not occur for DEMO- SKUs).
            if (in_array($sku, $existingSkus, true)) {
                $collisions[] = $sku;

                continue;
            }

            $toCreate[] = $definition;
        }

        if ($collisions !== []) {
            $this->error('SKU collision(s) detected: '.implode(', ', $collisions));
            $this->error('No records were written.');

            return self::FAILURE;
        }

        $this->info('StockPilot demo product seed');
        $this->line('Environment: '.$this->laravel->environment());
        $this->line('Acting user: '.$user->name.' (id '.$user->id.')');
        $this->line('Catalog definitions: '.count($definitions));
        $this->line('Already present (skip): '.count($toSkip));
        $this->line('Planned creates: '.count($toCreate));
        $this->newLine();
        $this->table(
            ['Category', 'Products'],
            collect(DemoProductCatalog::categoryDistribution())
                ->map(fn (int $count, string $name) => [$name, $count])
                ->values()
                ->all(),
        );
        $this->table(
            ['Stock tier', 'Count'],
            collect(DemoProductCatalog::stockTierDistribution())
                ->map(fn (int $count, string $tier) => [$tier, $count])
                ->values()
                ->all(),
        );

        if ($toCreate !== []) {
            $preview = array_slice($toCreate, 0, 8);
            $this->newLine();
            $this->line('Sample of planned inserts:');
            $this->table(
                ['SKU', 'Name', 'Qty', 'Reorder', 'Tier'],
                collect($preview)->map(fn (array $row) => [
                    $row['sku'],
                    $row['name'],
                    $row['opening_stock'],
                    $row['reorder_level'],
                    $row['stock_tier'],
                ])->all(),
            );

            if (count($toCreate) > 8) {
                $this->line('… and '.(count($toCreate) - 8).' more.');
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Dry-run only. No PostgreSQL / database records were changed.');

            return self::SUCCESS;
        }

        if (count($toCreate) === 0) {
            $this->info('Nothing to insert. All DEMO catalog SKUs already exist.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Create '.count($toCreate).' new DEMO product(s) without modifying existing records?',
            false,
        )) {
            $this->warn('Aborted. No records were written.');

            return self::FAILURE;
        }

        $created = 0;
        $movements = 0;
        $categoriesCreated = 0;
        $failed = [];

        $categoryCache = [];

        try {
            DB::transaction(function () use (
                $toCreate,
                $user,
                $products,
                $categories,
                $stockMovements,
                &$created,
                &$movements,
                &$categoriesCreated,
                &$categoryCache,
            ) {
                foreach ($toCreate as $definition) {
                    $category = $categoryCache[$definition['category']]
                        ?? Category::query()->where('name', $definition['category'])->first();

                    if ($category === null) {
                        $category = $categories->create([
                            'name' => $definition['category'],
                            'slug' => $definition['category_slug'],
                            'description' => 'Demo catalog category for '.$definition['category'].'.',
                            'is_active' => true,
                        ], $user);
                        $categoriesCreated++;
                    }

                    $categoryCache[$definition['category']] = $category;

                    $product = $products->create(
                        [
                            'name' => $definition['name'],
                            'sku' => $definition['sku'],
                            'description' => $definition['description'],
                            'unit_price' => $definition['unit_price'],
                            'cost_price' => $definition['cost_price'],
                            'reorder_level' => $definition['reorder_level'],
                            'is_active' => true,
                        ],
                        [(int) $category->id],
                        $user,
                        openingStock: 0,
                    );

                    if ($definition['opening_stock'] > 0) {
                        $stockMovements->apply(
                            $product,
                            StockMovementType::StockIn,
                            $definition['opening_stock'],
                            $user,
                            DemoProductCatalog::OPENING_REASON,
                        );
                        $movements++;
                    }

                    $created++;
                }
            });
        } catch (Throwable $exception) {
            $failed[] = $exception->getMessage();
            $this->error('Seeding failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Created products: {$created}");
        $this->info("Skipped existing DEMO SKUs: ".count($toSkip));
        $this->info("Categories created: {$categoriesCreated}");
        $this->info("Opening stock movements: {$movements}");
        $this->info('Failed: '.count($failed));

        return self::SUCCESS;
    }
}
