<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CHECK constraints are applied for PostgreSQL and SQLite.
     * SQLite cannot add named CHECKs after create, so it uses an inline CREATE TABLE.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->createSqliteStockMovementsTable();

            return;
        }

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        if ($driver === 'pgsql') {
            $this->addPostgresConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }

    protected function createSqliteStockMovementsTable(): void
    {
        DB::statement('
            CREATE TABLE "stock_movements" (
                "id" integer primary key autoincrement not null,
                "product_id" integer not null,
                "user_id" integer,
                "type" varchar not null,
                "quantity_change" integer not null,
                "quantity_before" integer not null,
                "quantity_after" integer not null,
                "reason" text,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("product_id") references "products"("id") on delete cascade,
                foreign key("user_id") references "users"("id") on delete set null,
                check ("type" in (\'stock_in\', \'stock_out\', \'adjustment\', \'legacy_opening\')),
                check ("quantity_change" != 0),
                check ("quantity_before" >= 0),
                check ("quantity_after" >= 0),
                check ("quantity_after" = "quantity_before" + "quantity_change"),
                check (
                    ("type" = \'stock_in\' and "quantity_change" > 0)
                    or ("type" = \'stock_out\' and "quantity_change" < 0)
                    or ("type" = \'adjustment\')
                    or ("type" = \'legacy_opening\' and "quantity_change" > 0)
                ),
                check (
                    ("type" not in (\'adjustment\', \'legacy_opening\'))
                    or ("reason" is not null and length(trim("reason")) > 0)
                )
            )
        ');

        DB::statement('CREATE INDEX "stock_movements_product_id_created_at_index" ON "stock_movements" ("product_id", "created_at")');
    }

    protected function addPostgresConstraints(): void
    {
        $constraints = [
            'stock_movements_type_valid' => "type in ('stock_in', 'stock_out', 'adjustment', 'legacy_opening')",
            'stock_movements_quantity_change_nonzero' => 'quantity_change <> 0',
            'stock_movements_quantity_before_nonnegative' => 'quantity_before >= 0',
            'stock_movements_quantity_after_nonnegative' => 'quantity_after >= 0',
            'stock_movements_quantity_math' => 'quantity_after = quantity_before + quantity_change',
            'stock_movements_type_sign' => "(
                (type = 'stock_in' and quantity_change > 0)
                or (type = 'stock_out' and quantity_change < 0)
                or (type = 'adjustment')
                or (type = 'legacy_opening' and quantity_change > 0)
            )",
            'stock_movements_adjustment_reason_required' => "(
                type not in ('adjustment', 'legacy_opening')
                or (reason is not null and length(btrim(reason)) > 0)
            )",
        ];

        foreach ($constraints as $name => $expression) {
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }
};
