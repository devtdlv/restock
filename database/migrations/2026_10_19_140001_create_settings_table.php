<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Application settings (single logical profile row).
     *
     * Display preferences only affect presentation. They must not rewrite
     * historical purchase-order costs, stock quantities, or stored timestamps.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->default('StockPilot');
            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->text('business_address')->nullable();
            $table->unsignedInteger('default_reorder_level')->default(0);
            $table->unsignedSmallInteger('default_per_page')->default(10);
            $table->string('date_format', 32)->default('Y-m-d');
            $table->string('currency_symbol', 8)->default('$');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
