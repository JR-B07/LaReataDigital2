<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarPosController extends Controller
{
    public function currentCut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
        ]);

        $cut = DB::table('barra_cortes')
            ->where('id_evento', (int) $data['event_id'])
            ->where('id_usuario', (int) $request->user()->id)
            ->where('estado', 'abierto')
            ->orderByDesc('id')
            ->first();

        return response()->json($cut);
    }

    public function openCut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = DB::table('barra_cortes')
            ->where('id_evento', (int) $data['event_id'])
            ->where('id_usuario', (int) $request->user()->id)
            ->where('estado', 'abierto')
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'Ya tienes un corte abierto para este evento.',
            ], 422);
        }

        $id = DB::table('barra_cortes')->insertGetId([
            'id_evento' => (int) $data['event_id'],
            'id_usuario' => (int) $request->user()->id,
            'monto_apertura' => round((float) $data['opening_cash'], 2),
            'monto_efectivo_esperado' => round((float) $data['opening_cash'], 2),
            'estado' => 'abierto',
            'abierto_en' => now(),
            'notas_apertura' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cut = DB::table('barra_cortes')->where('id', $id)->first();

        return response()->json($cut, 201);
    }

    public function closeCut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cut_id' => ['required', 'exists:barra_cortes,id'],
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $cut = DB::table('barra_cortes')->where('id', (int) $data['cut_id'])->first();

        if (! $cut || $cut->estado !== 'abierto') {
            return response()->json(['message' => 'El corte ya esta cerrado o no existe.'], 422);
        }

        if ((int) $cut->id_usuario !== (int) $request->user()->id) {
            return response()->json(['message' => 'No puedes cerrar un corte de otro operador.'], 403);
        }

        $closingCash = round((float) $data['closing_cash'], 2);
        $expectedCash = round((float) $cut->monto_efectivo_esperado, 2);
        $difference = round($closingCash - $expectedCash, 2);

        DB::table('barra_cortes')
            ->where('id', $cut->id)
            ->update([
                'monto_cierre' => $closingCash,
                'diferencia' => $difference,
                'estado' => 'cerrado',
                'cerrado_en' => now(),
                'notas_cierre' => $data['notes'] ?? null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Corte cerrado.',
            'summary' => [
                'cut_id' => $cut->id,
                'expected_cash' => $expectedCash,
                'closing_cash' => $closingCash,
                'difference' => $difference,
            ],
        ]);
    }

    public function cutHistory(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $rows = DB::table('barra_cortes')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'barra_cortes.id_usuario')
            ->leftJoin('eventos', 'eventos.id', '=', 'barra_cortes.id_evento')
            ->selectRaw('barra_cortes.id, barra_cortes.estado, barra_cortes.monto_apertura, barra_cortes.monto_efectivo_esperado, barra_cortes.monto_cierre, barra_cortes.diferencia, barra_cortes.abierto_en, barra_cortes.cerrado_en, usuarios.nombre as operador, eventos.nombre as evento')
            ->where('barra_cortes.id_usuario', (int) $request->user()->id)
            ->when($eventId, fn($query) => $query->where('barra_cortes.id_evento', $eventId))
            ->orderByDesc('barra_cortes.id')
            ->limit(20)
            ->get();

        return response()->json($rows);
    }

    public function globalCutSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
        ]);

        $eventId = (int) $data['event_id'];

        $totals = DB::table('barra_cortes')
            ->where('id_evento', $eventId)
            ->selectRaw(
                'COUNT(*) as total_cuts,
                SUM(CASE WHEN estado = "abierto" THEN 1 ELSE 0 END) as open_cuts,
                SUM(CASE WHEN estado = "cerrado" THEN 1 ELSE 0 END) as closed_cuts,
                COALESCE(SUM(monto_apertura), 0) as opening_total,
                COALESCE(SUM(monto_efectivo_esperado), 0) as expected_cash_total,
                COALESCE(SUM(monto_cierre), 0) as closing_cash_total,
                COALESCE(SUM(diferencia), 0) as difference_total'
            )
            ->first();

        $salesTotals = DB::table('barra_ventas')
            ->where('id_evento', $eventId)
            ->selectRaw(
                'COUNT(*) as total_sales,
                COALESCE(SUM(total), 0) as sales_amount,
                COALESCE(SUM(CASE WHEN metodo_pago = "efectivo" THEN total ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN metodo_pago = "tarjeta" THEN total ELSE 0 END), 0) as card_sales,
                COALESCE(SUM(CASE WHEN metodo_pago = "transferencia" THEN total ELSE 0 END), 0) as transfer_sales'
            )
            ->first();

        $salesByCut = DB::table('barra_ventas')
            ->where('id_evento', $eventId)
            ->groupBy('id_corte')
            ->selectRaw(
                'id_corte,
                COALESCE(SUM(total), 0) as sales_total,
                COALESCE(SUM(CASE WHEN metodo_pago = "efectivo" THEN total ELSE 0 END), 0) as sales_cash'
            );

        $operators = DB::table('barra_cortes')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'barra_cortes.id_usuario')
            ->leftJoinSub($salesByCut, 'sales_by_cut', function ($join) {
                $join->on('sales_by_cut.id_corte', '=', 'barra_cortes.id');
            })
            ->where('barra_cortes.id_evento', $eventId)
            ->groupBy('barra_cortes.id_usuario', 'usuarios.nombre')
            ->selectRaw(
                'barra_cortes.id_usuario as operator_id,
                usuarios.nombre as operator_name,
                COUNT(DISTINCT barra_cortes.id) as cuts_count,
                SUM(CASE WHEN barra_cortes.estado = "abierto" THEN 1 ELSE 0 END) as open_cuts,
                SUM(CASE WHEN barra_cortes.estado = "cerrado" THEN 1 ELSE 0 END) as closed_cuts,
                COALESCE(SUM(barra_cortes.monto_efectivo_esperado), 0) as expected_cash,
                COALESCE(SUM(sales_by_cut.sales_total), 0) as sales_total,
                COALESCE(SUM(sales_by_cut.sales_cash), 0) as sales_cash'
            )
            ->orderByDesc('sales_total')
            ->get();

        return response()->json([
            'totals' => $totals,
            'sales' => $salesTotals,
            'operators' => $operators,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');

        $query = DB::table('barra_productos')
            ->orderBy('nombre');

        if (! $includeInactive) {
            $query->where('activo', true)->where('stock', '>', 0);
        }

        return response()->json($query->get());
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'precio' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $id = DB::table('barra_productos')->insertGetId([
            'nombre' => $data['nombre'],
            'precio' => round((float) $data['precio'], 2),
            'stock' => (int) ($data['stock'] ?? 0),
            'activo' => (bool) ($data['activo'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = DB::table('barra_productos')->where('id', $id)->first();

        return response()->json($product, 201);
    }

    public function updateProduct(Request $request, int $product): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'precio' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $exists = DB::table('barra_productos')->where('id', $product)->exists();

        if (! $exists) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        DB::table('barra_productos')
            ->where('id', $product)
            ->update([
                'nombre' => $data['nombre'],
                'precio' => round((float) $data['precio'], 2),
                'stock' => (int) $data['stock'],
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
                'updated_at' => now(),
            ]);

        $updated = DB::table('barra_productos')->where('id', $product)->first();

        return response()->json($updated);
    }

    public function destroyProduct(int $product): JsonResponse
    {
        $row = DB::table('barra_productos')->where('id', $product)->first();

        if (! $row) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $hasSales = DB::table('barra_venta_detalle')->where('id_producto', $product)->exists();

        if ($hasSales) {
            DB::table('barra_productos')
                ->where('id', $product)
                ->update([
                    'activo' => false,
                    'stock' => 0,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'message' => 'Producto desactivado porque tiene ventas registradas.',
            ]);
        }

        DB::table('barra_productos')->where('id', $product)->delete();

        return response()->json([
            'message' => 'Producto eliminado.',
        ]);
    }

    public function updateStock(Request $request, int $product): JsonResponse
    {
        $data = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $exists = DB::table('barra_productos')->where('id', $product)->exists();

        if (! $exists) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $update = [
            'stock' => $data['stock'],
            'updated_at' => now(),
        ];

        if (array_key_exists('activo', $data)) {
            $update['activo'] = (bool) $data['activo'];
        }

        DB::table('barra_productos')
            ->where('id', $product)
            ->update($update);

        return response()->json([
            'message' => 'Inventario actualizado.',
        ]);
    }

    public function storeSale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
            'payment_method' => ['required', 'in:cash,card,transfer'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:barra_productos,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $activeCut = DB::table('barra_cortes')
            ->where('id_evento', (int) $data['event_id'])
            ->where('id_usuario', (int) $request->user()->id)
            ->where('estado', 'abierto')
            ->orderByDesc('id')
            ->first();

        if (! $activeCut) {
            return response()->json([
                'message' => 'Debes abrir un corte de caja antes de vender alcohol en este evento.',
            ], 422);
        }

        $result = DB::transaction(function () use ($data, $request, $activeCut) {
            $itemsById = collect($data['items'])
                ->groupBy('product_id')
                ->map(fn($rows) => (int) collect($rows)->sum('quantity'));

            $products = DB::table('barra_productos')
                ->whereIn('id', $itemsById->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $detailRows = [];
            $total = 0.0;

            foreach ($itemsById as $productId => $quantity) {
                $product = $products->get((int) $productId);

                if (! $product || ! $product->activo) {
                    return [
                        'error' => "Producto no disponible: {$productId}",
                        'status' => 422,
                    ];
                }

                if ((int) $product->stock < $quantity) {
                    return [
                        'error' => "Stock insuficiente para {$product->nombre}.",
                        'status' => 422,
                    ];
                }

                $price = (float) $product->precio;
                $subtotal = round($price * $quantity, 2);
                $total += $subtotal;

                $detailRows[] = [
                    'id_producto' => (int) $productId,
                    'cantidad' => $quantity,
                    'precio_unitario' => $price,
                    'subtotal' => $subtotal,
                ];
            }

            $saleId = DB::table('barra_ventas')->insertGetId([
                'id_evento' => (int) $data['event_id'],
                'id_usuario' => $request->user()?->id,
                'id_corte' => (int) $activeCut->id,
                'metodo_pago' => match ($data['payment_method']) {
                    'card' => 'tarjeta',
                    'transfer' => 'transferencia',
                    default => 'efectivo',
                },
                'total' => round($total, 2),
                'notas' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($detailRows as $row) {
                DB::table('barra_venta_detalle')->insert([
                    'id_venta' => $saleId,
                    ...$row,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('barra_productos')
                    ->where('id', $row['id_producto'])
                    ->decrement('stock', $row['cantidad']);
            }

            if ($data['payment_method'] === 'cash') {
                DB::table('barra_cortes')
                    ->where('id', (int) $activeCut->id)
                    ->increment('monto_efectivo_esperado', round($total, 2));
            }

            return [
                'sale_id' => $saleId,
                'total' => round($total, 2),
            ];
        });

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['status'] ?? 422);
        }

        return response()->json([
            'message' => 'Venta de barra registrada.',
            'sale' => $result,
        ], 201);
    }

    public function recentSales(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $rows = DB::table('barra_ventas')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'barra_ventas.id_usuario')
            ->leftJoin('eventos', 'eventos.id', '=', 'barra_ventas.id_evento')
            ->selectRaw('barra_ventas.id, barra_ventas.total, barra_ventas.metodo_pago, barra_ventas.created_at, usuarios.nombre as vendedor, eventos.nombre as evento')
            ->when($eventId, fn($query) => $query->where('barra_ventas.id_evento', $eventId))
            ->orderByDesc('barra_ventas.id')
            ->limit(20)
            ->get();

        return response()->json($rows);
    }
}
