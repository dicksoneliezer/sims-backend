<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 100);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->decimal('depreciation_rate', 5, 2)->default(0);
            $table->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->string('condition', 30)->default('good');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->date('maintenance_date');
            $table->text('description');
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('performed_by', 150)->nullable();
            $table->date('next_due_date')->nullable();
            $table->string('status', 30)->default('completed');
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 100);
            $table->string('unit', 30)->default('pcs');
            $table->integer('quantity')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('location', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('type', 10)->index();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('transaction_date');
            $table->timestamps();
        });

        Schema::create('low_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('threshold');
            $table->integer('quantity_at_alert');
            $table->boolean('is_resolved')->default(false)->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_stock_alerts');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('asset_maintenance');
        Schema::dropIfExists('assets');
    }
};
