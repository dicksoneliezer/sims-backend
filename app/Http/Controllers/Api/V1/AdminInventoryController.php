<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetMaintenanceResource;
use App\Http\Resources\AssetResource;
use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\LowStockAlertResource;
use App\Http\Resources\StockTransactionResource;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\InventoryItem;
use App\Models\LowStockAlert;
use App\Models\StockTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function overview(): JsonResponse
    {
        $assets = Asset::query();
        $items = InventoryItem::query();

        return response()->json([
            'success' => true,
            'message' => 'Inventory overview retrieved',
            'data' => [
                'total_assets' => (clone $assets)->count(),
                'active_assets' => (clone $assets)->where('status', 'active')->count(),
                'assets_purchase_value' => (float) (clone $assets)->sum('purchase_cost'),
                'assets_current_value' => (float) (clone $assets)->sum('current_value'),
                'maintenance_due' => AssetMaintenance::whereNotNull('next_due_date')
                    ->whereDate('next_due_date', '<=', now()->addDays(30))
                    ->where('status', '!=', 'cancelled')
                    ->count(),
                'total_items' => (clone $items)->count(),
                'stock_value' => (float) (clone $items)->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')->value('total'),
                'total_quantity' => (int) (clone $items)->sum('quantity'),
                'low_stock_items' => (clone $items)->whereColumn('quantity', '<=', 'reorder_level')->count(),
                'open_alerts' => LowStockAlert::where('is_resolved', false)->count(),
            ],
        ]);
    }

    public function assets(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $assets = Asset::query()
            ->withCount('maintenance')
            ->with('custodian:id,full_name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate($perPage);

        return $this->paginated('Assets retrieved', AssetResource::collection($assets->items()), $assets);
    }

    public function items(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $items = InventoryItem::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('item_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereColumn('quantity', '<=', 'reorder_level'))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($perPage);

        return $this->paginated('Inventory items retrieved', InventoryItemResource::collection($items->items()), $items);
    }

    public function stock(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $stock = StockTransaction::query()
            ->with(['item:id,item_code,name,unit', 'student:id,student_number,full_name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('item_id'), fn ($query) => $query->where('inventory_item_id', $request->integer('item_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->date('to')))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate($perPage);

        return $this->paginated('Stock transactions retrieved', StockTransactionResource::collection($stock->items()), $stock);
    }

    public function alerts(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $alerts = LowStockAlert::query()
            ->with('item:id,item_code,name,unit,quantity,reorder_level')
            ->when($request->filled('resolved'), fn ($query) => $query->where('is_resolved', $request->boolean('resolved')))
            ->latest('id')
            ->paginate($perPage);

        return $this->paginated('Low stock alerts retrieved', LowStockAlertResource::collection($alerts->items()), $alerts);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $maintenance = AssetMaintenance::query()
            ->with('asset:id,asset_code,name')
            ->when($request->filled('asset_id'), fn ($query) => $query->where('asset_id', $request->integer('asset_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('maintenance_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('maintenance_date', '<=', $request->date('to')))
            ->latest('maintenance_date')
            ->latest('id')
            ->paginate($perPage);

        return $this->paginated('Asset maintenance retrieved', AssetMaintenanceResource::collection($maintenance->items()), $maintenance);
    }

    private function paginated(string $message, mixed $data, mixed $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
