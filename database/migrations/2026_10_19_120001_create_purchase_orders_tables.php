<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchase orders and line items.
     *
     * Supplier/product FKs use restrictOnDelete so historical PO records remain intact.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->createSqliteTables();

            return;
        }

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('order_date');
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('ordered_quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id']);
            $table->index('product_id');
        });

        if ($driver === 'pgsql') {
            $this->addPostgresConstraints();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }

    protected function createSqliteTables(): void
    {
        DB::statement('
            CREATE TABLE "purchase_orders" (
                "id" integer primary key autoincrement not null,
                "number" varchar not null,
                "supplier_id" integer not null,
                "status" varchar not null,
                "order_date" date not null,
                "expected_delivery_date" date,
                "notes" text,
                "created_by" integer,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("supplier_id") references "suppliers"("id") on delete restrict,
                foreign key("created_by") references "users"("id") on delete set null,
                unique("number"),
                check ("status" in (\'draft\', \'ordered\', \'partially_received\', \'received\', \'cancelled\'))
            )
        ');

        DB::statement('CREATE INDEX "purchase_orders_status_index" ON "purchase_orders" ("status")');
        DB::statement('CREATE INDEX "purchase_orders_order_date_index" ON "purchase_orders" ("order_date")');
        DB::statement('CREATE INDEX "purchase_orders_supplier_id_status_index" ON "purchase_orders" ("supplier_id", "status")');

        DB::statement('
            CREATE TABLE "purchase_order_items" (
                "id" integer primary key autoincrement not null,
                "purchase_order_id" integer not null,
                "product_id" integer not null,
                "ordered_quantity" integer not null,
                "received_quantity" integer not null default 0,
                "unit_cost" numeric not null,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("purchase_order_id") references "purchase_orders"("id") on delete cascade,
                foreign key("product_id") references "products"("id") on delete restrict,
                unique("purchase_order_id", "product_id"),
                check ("ordered_quantity" > 0),
                check ("received_quantity" >= 0),
                check ("received_quantity" <= "ordered_quantity"),
                check ("unit_cost" >= 0)
            )
        ');

        DB::statement('CREATE INDEX "purchase_order_items_product_id_index" ON "purchase_order_items" ("product_id")');
    }

    protected function addPostgresConstraints(): void
    {
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_valid CHECK (
            status in ('draft', 'ordered', 'partially_received', 'received', 'cancelled')
        )");

        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_ordered_positive CHECK (ordered_quantity > 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_received_nonnegative CHECK (received_quantity >= 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_received_not_over CHECK (received_quantity <= ordered_quantity)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_unit_cost_nonnegative CHECK (unit_cost >= 0)');
    }
};
