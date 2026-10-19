<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable receiving history with idempotency keys and stock-movement links.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->createSqliteTables();

            return;
        }

        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_key', 64)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'received_at']);
        });

        Schema::create('purchase_order_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['purchase_order_receipt_id', 'purchase_order_item_id'], 'po_receipt_items_receipt_item_unique');
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE purchase_order_receipt_items ADD CONSTRAINT purchase_order_receipt_items_quantity_positive CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_items');
        Schema::dropIfExists('purchase_order_receipts');
    }

    protected function createSqliteTables(): void
    {
        DB::statement('
            CREATE TABLE "purchase_order_receipts" (
                "id" integer primary key autoincrement not null,
                "receipt_key" varchar not null,
                "purchase_order_id" integer not null,
                "user_id" integer,
                "received_at" datetime not null,
                "notes" text,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("purchase_order_id") references "purchase_orders"("id") on delete restrict,
                foreign key("user_id") references "users"("id") on delete set null,
                unique("receipt_key")
            )
        ');

        DB::statement('CREATE INDEX "purchase_order_receipts_purchase_order_id_received_at_index" ON "purchase_order_receipts" ("purchase_order_id", "received_at")');

        DB::statement('
            CREATE TABLE "purchase_order_receipt_items" (
                "id" integer primary key autoincrement not null,
                "purchase_order_receipt_id" integer not null,
                "purchase_order_item_id" integer not null,
                "quantity" integer not null,
                "stock_movement_id" integer,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("purchase_order_receipt_id") references "purchase_order_receipts"("id") on delete cascade,
                foreign key("purchase_order_item_id") references "purchase_order_items"("id") on delete restrict,
                foreign key("stock_movement_id") references "stock_movements"("id") on delete restrict,
                unique("purchase_order_receipt_id", "purchase_order_item_id"),
                check ("quantity" > 0)
            )
        ');
    }
};
