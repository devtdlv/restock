<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow system-generated legacy opening balance movements.
     *
     * PostgreSQL already has the original CHECK constraints from batch 3, so this
     * migration replaces the type/sign/reason constraints. SQLite test databases
     * recreate from the updated base migration and skip this alter path.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_valid');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_sign');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_adjustment_reason_required');

        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_valid CHECK (type in ('stock_in', 'stock_out', 'adjustment', 'legacy_opening'))");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_sign CHECK (
            (type = 'stock_in' and quantity_change > 0)
            or (type = 'stock_out' and quantity_change < 0)
            or (type = 'adjustment')
            or (type = 'legacy_opening' and quantity_change > 0)
        )");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_adjustment_reason_required CHECK (
            type not in ('adjustment', 'legacy_opening')
            or (reason is not null and length(btrim(reason)) > 0)
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_valid');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_sign');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_adjustment_reason_required');

        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_valid CHECK (type in ('stock_in', 'stock_out', 'adjustment'))");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_sign CHECK (
            (type = 'stock_in' and quantity_change > 0)
            or (type = 'stock_out' and quantity_change < 0)
            or (type = 'adjustment')
        )");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_adjustment_reason_required CHECK (
            type <> 'adjustment'
            or (reason is not null and length(btrim(reason)) > 0)
        )");
    }
};
