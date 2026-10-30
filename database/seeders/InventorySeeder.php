<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\InventoryItem;
use App\Models\LowStockAlert;
use App\Models\StockTransaction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $storeKeeper = User::where('role', 'store_keeper')->first();
        $students = Student::orderBy('id')->limit(2)->get();

        $assets = [
            ['AST-2026-0001', 'School Bus', 'Vehicle', 'Main school compound', '2022-01-15', 180000000, 135000000, 10, 'active', 'good', 'Toyota Coaster used for daily student transport.'],
            ['AST-2026-0002', 'Desktop Computers', 'ICT Equipment', 'Computer Lab', '2024-03-10', 24000000, 18000000, 15, 'active', 'good', 'Twenty desktop computers for ICT lessons.'],
            ['AST-2026-0003', 'Laboratory Microscope', 'Laboratory', 'Science Lab', '2023-08-22', 4500000, 3600000, 8, 'active', 'fair', 'Binocular microscope used in biology practicals.'],
            ['AST-2026-0004', 'Dining Hall Tables', 'Furniture', 'Dining Hall', '2021-05-05', 8000000, 4200000, 12, 'active', 'fair', 'Long dining tables with benches.'],
            ['AST-2026-0005', 'Water Pump', 'Utilities', 'Borehole Room', '2020-11-12', 6500000, 2100000, 15, 'under_maintenance', 'poor', 'Electric pump serving the school borehole.'],
        ];

        $assetModels = [];

        foreach ($assets as [$code, $name, $category, $location, $date, $cost, $value, $rate, $status, $condition, $description]) {
            $assetModels[$code] = Asset::updateOrCreate(
                ['asset_code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'description' => $description,
                    'location' => $location,
                    'purchase_date' => $date,
                    'purchase_cost' => $cost,
                    'current_value' => $value,
                    'depreciation_rate' => $rate,
                    'custodian_id' => $storeKeeper?->id,
                    'status' => $status,
                    'condition' => $condition,
                    'created_by' => $storeKeeper?->id,
                ],
            );
        }

        $items = [
            ['INV-2026-0001', 'Exercise Books', 'Stationery', 'pcs', 850, 200, 1500],
            ['INV-2026-0002', 'Chalk Boxes', 'Teaching Supplies', 'box', 35, 20, 2500],
            ['INV-2026-0003', 'Toilet Paper', 'Cleaning', 'roll', 120, 100, 800],
            ['INV-2026-0004', 'Maize Flour', 'Food', 'kg', 640, 250, 2200],
            ['INV-2026-0005', 'Cooking Oil', 'Food', 'litre', 180, 80, 4500],
            ['INV-2026-0006', 'First Aid Kits', 'Medical', 'kit', 8, 10, 35000],
        ];

        $itemModels = [];

        foreach ($items as [$code, $name, $category, $unit, $quantity, $reorder, $price]) {
            $itemModels[$code] = InventoryItem::updateOrCreate(
                ['item_code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'reorder_level' => $reorder,
                    'unit_price' => $price,
                    'location' => 'Main Store',
                    'is_active' => true,
                ],
            );
        }

        $stock = [
            ['INV-2026-0001', 'in', 1000, 1500, 'PO-2026-001', 'Term 1 stationery supply', null],
            ['INV-2026-0001', 'out', 150, 1500, 'REQ-2026-011', 'Issued to Form 1A', 0],
            ['INV-2026-0004', 'in', 700, 2200, 'PO-2026-002', 'Food supplies for boarding section', null],
            ['INV-2026-0004', 'out', 60, 2200, 'REQ-2026-012', 'Daily kitchen issue', 0],
            ['INV-2026-0005', 'in', 200, 4500, 'PO-2026-003', 'Cooking oil supply', null],
            ['INV-2026-0005', 'out', 20, 4500, 'REQ-2026-013', 'Kitchen issue', 1],
            ['INV-2026-0006', 'in', 10, 35000, 'PO-2026-004', 'First aid restock', null],
            ['INV-2026-0006', 'out', 2, 35000, 'REQ-2026-014', 'Issued to sick bay', null],
        ];

        foreach ($stock as $index => [$code, $type, $quantity, $price, $reference, $notes, $studentIndex]) {
            $item = $itemModels[$code] ?? null;

            if (! $item) {
                continue;
            }

            StockTransaction::updateOrCreate(
                ['reference' => $reference],
                [
                    'inventory_item_id' => $item->id,
                    'student_id' => $studentIndex !== null ? $students[$studentIndex]?->id : null,
                    'type' => $type,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'notes' => $notes,
                    'recorded_by' => $storeKeeper?->id,
                    'transaction_date' => now()->subDays(10 - $index)->toDateString(),
                ],
            );
        }

        foreach (['INV-2026-0006' => 10] as $code => $threshold) {
            $item = $itemModels[$code] ?? null;

            if (! $item) {
                continue;
            }

            LowStockAlert::updateOrCreate(
                ['inventory_item_id' => $item->id, 'is_resolved' => false],
                [
                    'threshold' => $threshold,
                    'quantity_at_alert' => $item->quantity,
                    'resolved_by' => null,
                    'resolved_at' => null,
                ],
            );
        }

        $maintenance = [
            ['AST-2026-0005', now()->subDays(12)->toDateString(), 'Replaced pump impeller and serviced motor.', 450000, 'Tanga Water Services', now()->addDays(18)->toDateString(), 'completed'],
            ['AST-2026-0003', now()->subDays(30)->toDateString(), 'Annual microscope calibration and lens cleaning.', 120000, 'Lab Tech Ltd', now()->addDays(5)->toDateString(), 'completed'],
            ['AST-2026-0002', now()->subDays(60)->toDateString(), 'Computer lab network switch replacement.', 350000, 'NetSolutions', now()->addDays(2)->toDateString(), 'completed'],
        ];

        foreach ($maintenance as [$code, $date, $description, $cost, $performedBy, $nextDue, $status]) {
            $asset = $assetModels[$code] ?? null;

            if (! $asset) {
                continue;
            }

            AssetMaintenance::updateOrCreate(
                ['asset_id' => $asset->id, 'maintenance_date' => $date],
                [
                    'description' => $description,
                    'cost' => $cost,
                    'performed_by' => $performedBy,
                    'next_due_date' => $nextDue,
                    'status' => $status,
                ],
            );
        }
    }
}
