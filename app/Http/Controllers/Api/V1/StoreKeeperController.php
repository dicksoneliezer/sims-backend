<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreKeeperController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'assets_count' => Asset::count(),
                'inventory_count' => InventoryItem::count(),
                'low_stock_count' => InventoryItem::whereColumn('quantity', '<=', 'reorder_level')->count(),
                'maintenance_count' => AssetMaintenance::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function assets(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);
        $assets = Asset::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('asset_code', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Assets retrieved',
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }

    public function storeAsset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'asset_code' => 'required|string|max:50|unique:assets,asset_code',
            'category' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'purchase_cost' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:active,under_maintenance,retired',
        ]);

        $asset = Asset::create([
            'name' => $validated['name'],
            'asset_code' => $validated['asset_code'],
            'category' => $validated['category'],
            'location' => $validated['location'] ?? null,
            'purchase_cost' => $validated['purchase_cost'] ?? 0,
            'current_value' => $validated['current_value'] ?? 0,
            'status' => $validated['status'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asset registered successfully',
            'data' => $asset,
        ], 201);
    }

    public function inventory(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);
        $items = InventoryItem::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('item_code', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Inventory items retrieved',
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function storeInventory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'item_code' => 'required|string|max:50|unique:inventory_items,item_code',
            'category' => 'required|string|max:100',
            'unit' => 'required|string|max:30',
            'quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'unit_price' => 'required|numeric|min:0',
            'location' => 'nullable|string|max:150',
        ]);

        $item = InventoryItem::create([
            'name' => $validated['name'],
            'item_code' => $validated['item_code'],
            'category' => $validated['category'],
            'unit' => $validated['unit'],
            'quantity' => $validated['quantity'],
            'reorder_level' => $validated['reorder_level'],
            'unit_price' => $validated['unit_price'],
            'location' => $validated['location'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inventory item added successfully',
            'data' => $item,
        ], 201);
    }

    public function stock(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);
        $transactions = StockTransaction::query()
            ->with(['item', 'recorder'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Stock transactions retrieved',
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);
        $records = AssetMaintenance::query()
            ->with('asset')
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance records retrieved',
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    // =========================================
    // LOW STOCK ALERTS (MPYA)
    // =========================================
    public function alerts(Request $request): JsonResponse
    {
        $alerts = InventoryItem::whereColumn('quantity', '<=', 'reorder_level')
            ->orderBy('quantity')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'category' => $item->category,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'reorder_level' => $item->reorder_level,
                'location' => $item->location,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Low-stock alerts retrieved',
            'data' => $alerts,
        ]);
    }

    // =========================================
    // REPORTS (MPYA)
    // =========================================
    public function reports(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->subDays(30);
        $to = $request->date('to') ?? now();

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved',
            'data' => [
                'assets' => [
                    'total' => Asset::count(),
                    'by_status' => Asset::selectRaw('status, COUNT(*) as count')
                        ->groupBy('status')
                        ->get(),
                    'total_value' => (float) Asset::sum('current_value'),
                ],
                'inventory' => [
                    'total_items' => InventoryItem::count(),
                    'low_stock' => InventoryItem::whereColumn('quantity', '<=', 'reorder_level')->count(),
                    'total_value' => (float) InventoryItem::selectRaw('SUM(quantity * unit_price) as total')
                        ->value('total') ?? 0,
                ],
                'stock_movements' => [
                    'in' => (int) StockTransaction::where('type', 'in')
                        ->whereBetween('transaction_date', [$from, $to])
                        ->sum('quantity'),
                    'out' => (int) StockTransaction::where('type', 'out')
                        ->whereBetween('transaction_date', [$from, $to])
                        ->sum('quantity'),
                ],
                'maintenance' => [
                    'total_records' => AssetMaintenance::whereBetween('maintenance_date', [$from, $to])->count(),
                    'total_cost' => (float) AssetMaintenance::whereBetween('maintenance_date', [$from, $to])->sum('cost'),
                    'pending' => AssetMaintenance::where('status', 'pending')->count(),
                ],
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
            ],
        ]);
    }

    // =========================================
    // ASSET VALUE & DEPRECIATION REPORT (MPYA)
    // =========================================
    public function assetReport(Request $request): JsonResponse
    {
        $byCategory = Asset::selectRaw('category, COUNT(*) as count, SUM(purchase_cost) as total_purchase, SUM(current_value) as total_current')
            ->groupBy('category')
            ->get();

        $byStatus = Asset::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $totalPurchase = (float) Asset::sum('purchase_cost');
        $totalCurrent = (float) Asset::sum('current_value');
        $totalDepreciation = $totalPurchase - $totalCurrent;

        return response()->json([
            'success' => true,
            'message' => 'Asset report retrieved',
            'data' => [
                'by_category' => $byCategory,
                'by_status' => $byStatus,
                'totals' => [
                    'purchase_cost' => $totalPurchase,
                    'current_value' => $totalCurrent,
                    'depreciation' => $totalDepreciation,
                    'depreciation_rate' => $totalPurchase > 0
                        ? round(($totalDepreciation / $totalPurchase) * 100, 2)
                        : 0,
                ],
            ],
        ]);
    }
}