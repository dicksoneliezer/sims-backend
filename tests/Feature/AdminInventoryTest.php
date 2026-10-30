<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\InventoryItem;
use App\Models\LowStockAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_view_inventory_overview(): void
    {
        $this->actingAsAdmin();

        $asset = Asset::create([
            'asset_code' => 'AST-TEST-001',
            'name' => 'Projector',
            'category' => 'ICT Equipment',
            'purchase_cost' => 2000,
            'current_value' => 1500,
            'status' => 'active',
            'condition' => 'good',
        ]);

        $item = InventoryItem::create([
            'item_code' => 'INV-TEST-001',
            'name' => 'Chalk',
            'category' => 'Supplies',
            'quantity' => 5,
            'reorder_level' => 10,
            'unit_price' => 100,
        ]);

        LowStockAlert::create([
            'inventory_item_id' => $item->id,
            'threshold' => 10,
            'quantity_at_alert' => 5,
        ]);

        AssetMaintenance::create([
            'asset_id' => $asset->id,
            'maintenance_date' => now()->toDateString(),
            'description' => 'Lamp replacement',
            'cost' => 100,
            'next_due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->getJson('/api/v1/admin/inventory/overview')
            ->assertOk()
            ->assertJsonPath('data.total_assets', 1)
            ->assertJsonPath('data.assets_current_value', 1500)
            ->assertJsonPath('data.total_items', 1)
            ->assertJsonPath('data.stock_value', 500)
            ->assertJsonPath('data.low_stock_items', 1)
            ->assertJsonPath('data.open_alerts', 1)
            ->assertJsonPath('data.maintenance_due', 1);
    }

    public function test_admin_can_filter_inventory_lists(): void
    {
        $this->actingAsAdmin();

        $asset = Asset::create([
            'asset_code' => 'AST-TEST-002',
            'name' => 'School Van',
            'category' => 'Vehicle',
            'purchase_cost' => 5000,
            'current_value' => 4000,
            'status' => 'active',
            'condition' => 'good',
        ]);

        InventoryItem::create([
            'item_code' => 'INV-TEST-002',
            'name' => 'Soap',
            'category' => 'Cleaning',
            'quantity' => 50,
            'reorder_level' => 10,
            'unit_price' => 20,
        ]);

        $this->getJson('/api/v1/admin/inventory/assets?search=Van')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.asset_code', 'AST-TEST-002');

        $this->getJson('/api/v1/admin/inventory/items?low_stock=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/admin/inventory/maintenance?asset_id='.$asset->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/admin/inventory/stock')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/admin/inventory/alerts')->assertOk()->assertJsonPath('meta.total', 0);
    }
}
