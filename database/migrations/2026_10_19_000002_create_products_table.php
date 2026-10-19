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
     * Nonnegative integrity is enforced with CHECK constraints on PostgreSQL and SQLite.
     * SQLite cannot ALTER TABLE to add named CHECKs after create, so it uses an
     * inline CREATE TABLE definition. PostgreSQL uses Blueprint + ALTER TABLE.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->createSqliteProductsTable();

            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if ($driver === 'pgsql') {
            $this->addPostgresNonNegativeConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }

    /**
     * Create the products table for SQLite with inline CHECK constraints.
     */
    protected function createSqliteProductsTable(): void
    {
        DB::statement('
            CREATE TABLE "products" (
                "id" integer primary key autoincrement not null,
                "name" varchar not null,
                "sku" varchar not null,
                "description" text,
                "unit_price" numeric(12, 2) not null default 0,
                "cost_price" numeric(12, 2) not null default 0,
                "quantity_on_hand" integer not null default 0,
                "reorder_level" integer not null default 0,
                "is_active" tinyint(1) not null default 1,
                "created_at" datetime,
                "updated_at" datetime,
                unique ("sku"),
                check ("unit_price" >= 0),
                check ("cost_price" >= 0),
                check ("quantity_on_hand" >= 0),
                check ("reorder_level" >= 0)
            )
        ');
    }

    /**
     * Add named CHECK constraints for PostgreSQL.
     *
     * Note: PostgreSQL has no unsigned integer type, so these CHECKs are the
     * database-level guarantee for nonnegative stock and price columns.
     */
    protected function addPostgresNonNegativeConstraints(): void
    {
        $constraints = [
            'products_unit_price_nonnegative' => 'unit_price >= 0',
            'products_cost_price_nonnegative' => 'cost_price >= 0',
            'products_quantity_on_hand_nonnegative' => 'quantity_on_hand >= 0',
            'products_reorder_level_nonnegative' => 'reorder_level >= 0',
        ];

        foreach ($constraints as $name => $expression) {
            DB::statement("ALTER TABLE products ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }
};
