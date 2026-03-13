<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarPosController extends Controller
{
    private function logMovement(int $productId, ?int $userId, string $tipo, int $cantidad, int $stockAnterior, int $stockNuevo, ?string $motivo = null): void
    {
        DB::table('barra_movimientos')->insert([
            'id_producto' => $productId,
            'id_usuario' => $userId,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'motivo' => $motivo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

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

        $stock = (int) ($data['stock'] ?? 0);

        $id = DB::table('barra_productos')->insertGetId([
            'nombre' => $data['nombre'],
            'precio' => round((float) $data['precio'], 2),
            'stock' => $stock,
            'activo' => (bool) ($data['activo'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($stock > 0) {
            $this->logMovement($id, $request->user()?->id, 'entrada', $stock, 0, $stock, 'Stock inicial al crear producto');
        }

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
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $row = DB::table('barra_productos')->where('id', $product)->first();

        if (! $row) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $oldStock = (int) $row->stock;
        $newStock = (int) $data['stock'];

        $update = [
            'stock' => $newStock,
            'updated_at' => now(),
        ];

        if (array_key_exists('activo', $data)) {
            $update['activo'] = (bool) $data['activo'];
        }

        DB::table('barra_productos')
            ->where('id', $product)
            ->update($update);

        if ($oldStock !== $newStock) {
            $tipo = $newStock > $oldStock ? 'entrada' : 'ajuste';
            $this->logMovement(
                $product,
                $request->user()?->id,
                $tipo,
                abs($newStock - $oldStock),
                $oldStock,
                $newStock,
                $data['motivo'] ?? 'Ajuste manual de stock'
            );
        }

        return response()->json([
            'message' => 'Inventario actualizado.',
        ]);
    }

    public function manualMovement(Request $request, int $product): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:entrada,merma,ajuste'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $row = DB::table('barra_productos')->where('id', $product)->first();

        if (! $row) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $oldStock = (int) $row->stock;
        $cantidad = (int) $data['cantidad'];

        if ($data['tipo'] === 'entrada') {
            $newStock = $oldStock + $cantidad;
        } else {
            $newStock = max(0, $oldStock - $cantidad);
        }

        DB::table('barra_productos')
            ->where('id', $product)
            ->update(['stock' => $newStock, 'updated_at' => now()]);

        $this->logMovement(
            $product,
            $request->user()?->id,
            $data['tipo'],
            $cantidad,
            $oldStock,
            $newStock,
            $data['motivo'] ?? null
        );

        return response()->json([
            'message' => 'Movimiento registrado.',
            'stock_anterior' => $oldStock,
            'stock_nuevo' => $newStock,
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

            // Cargar promociones activas
            $today = now()->toDateString();
            $activePromos = DB::table('barra_promociones')
                ->where('activo', true)
                ->where(function ($q) use ($today) {
                    $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $today);
                })
                ->get();

            $detailRows = [];
            $total = 0.0;
            $totalDescuento = 0.0;

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

                // Aplicar la mejor promocion para este producto
                $bestDiscount = 0.0;
                foreach ($activePromos as $promo) {
                    if ($promo->id_producto !== null && (int) $promo->id_producto !== (int) $productId) {
                        continue;
                    }

                    $discount = $promo->tipo === 'porcentaje'
                        ? round($price * ((float) $promo->valor / 100), 2)
                        : min(round((float) $promo->valor, 2), $price);

                    if ($discount > $bestDiscount) {
                        $bestDiscount = $discount;
                    }
                }

                $finalPrice = round($price - $bestDiscount, 2);
                $subtotal = round($finalPrice * $quantity, 2);
                $total += $subtotal;
                $totalDescuento += round($bestDiscount * $quantity, 2);

                $detailRows[] = [
                    'id_producto' => (int) $productId,
                    'cantidad' => $quantity,
                    'precio_unitario' => $finalPrice,
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

                $currentProduct = DB::table('barra_productos')->where('id', $row['id_producto'])->first();
                $oldStock = (int) ($currentProduct->stock ?? 0);

                DB::table('barra_productos')
                    ->where('id', $row['id_producto'])
                    ->decrement('stock', $row['cantidad']);

                $this->logMovement(
                    $row['id_producto'],
                    $request->user()?->id,
                    'salida_venta',
                    $row['cantidad'],
                    $oldStock,
                    $oldStock - $row['cantidad'],
                    'Venta de barra'
                );
            }

            if ($data['payment_method'] === 'cash') {
                DB::table('barra_cortes')
                    ->where('id', (int) $activeCut->id)
                    ->increment('monto_efectivo_esperado', round($total, 2));
            }

            return [
                'sale_id' => $saleId,
                'total' => round($total, 2),
                'descuento' => round($totalDescuento, 2),
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
            ->selectRaw('barra_ventas.id, barra_ventas.total, barra_ventas.metodo_pago, barra_ventas.estado, barra_ventas.created_at, usuarios.nombre as vendedor, eventos.nombre as evento')
            ->when($eventId, fn($query) => $query->where('barra_ventas.id_evento', $eventId))
            ->orderByDesc('barra_ventas.id')
            ->limit(20)
            ->get();

        return response()->json($rows);
    }

    // ── Movimientos de inventario ────────────────────────────

    public function stockMovements(Request $request): JsonResponse
    {
        $productId = $request->query('product_id');

        $rows = DB::table('barra_movimientos')
            ->leftJoin('barra_productos', 'barra_productos.id', '=', 'barra_movimientos.id_producto')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'barra_movimientos.id_usuario')
            ->select(
                'barra_movimientos.*',
                'barra_productos.nombre as producto_nombre',
                'usuarios.nombre as usuario_nombre'
            )
            ->when($productId, fn($q) => $q->where('barra_movimientos.id_producto', (int) $productId))
            ->orderByDesc('barra_movimientos.id')
            ->limit(50)
            ->get();

        return response()->json($rows);
    }

    public function stockAlerts(): JsonResponse
    {
        $lowStockThreshold = 10;

        $lowStock = DB::table('barra_productos')
            ->where('activo', true)
            ->where('stock', '>', 0)
            ->where('stock', '<=', $lowStockThreshold)
            ->orderBy('stock')
            ->get();

        $outOfStock = DB::table('barra_productos')
            ->where('activo', true)
            ->where('stock', '<=', 0)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'threshold' => $lowStockThreshold,
        ]);
    }

    // ── Promociones CRUD ─────────────────────────────────────

    public function promotions(): JsonResponse
    {
        $rows = DB::table('barra_promociones')
            ->leftJoin('barra_productos', 'barra_productos.id', '=', 'barra_promociones.id_producto')
            ->select(
                'barra_promociones.*',
                'barra_productos.nombre as producto_nombre'
            )
            ->orderByDesc('barra_promociones.id')
            ->get();

        return response()->json($rows);
    }

    public function storePromotion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:porcentaje,monto_fijo'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'id_producto' => ['nullable', 'exists:barra_productos,id'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'activo' => ['nullable', 'boolean'],
        ]);

        if ($data['tipo'] === 'porcentaje' && $data['valor'] > 100) {
            return response()->json(['message' => 'El porcentaje no puede ser mayor a 100.'], 422);
        }

        $id = DB::table('barra_promociones')->insertGetId([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'valor' => round((float) $data['valor'], 2),
            'id_producto' => $data['id_producto'] ?? null,
            'fecha_inicio' => $data['fecha_inicio'] ?? null,
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'activo' => (bool) ($data['activo'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $promo = DB::table('barra_promociones')->where('id', $id)->first();

        return response()->json($promo, 201);
    }

    public function updatePromotion(Request $request, int $promotion): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:porcentaje,monto_fijo'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'id_producto' => ['nullable', 'exists:barra_productos,id'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $exists = DB::table('barra_promociones')->where('id', $promotion)->exists();

        if (! $exists) {
            return response()->json(['message' => 'Promoción no encontrada.'], 404);
        }

        if ($data['tipo'] === 'porcentaje' && $data['valor'] > 100) {
            return response()->json(['message' => 'El porcentaje no puede ser mayor a 100.'], 422);
        }

        DB::table('barra_promociones')
            ->where('id', $promotion)
            ->update([
                'nombre' => $data['nombre'],
                'tipo' => $data['tipo'],
                'valor' => round((float) $data['valor'], 2),
                'id_producto' => $data['id_producto'] ?? null,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
                'fecha_fin' => $data['fecha_fin'] ?? null,
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
                'updated_at' => now(),
            ]);

        $updated = DB::table('barra_promociones')->where('id', $promotion)->first();

        return response()->json($updated);
    }

    public function destroyPromotion(int $promotion): JsonResponse
    {
        $exists = DB::table('barra_promociones')->where('id', $promotion)->exists();

        if (! $exists) {
            return response()->json(['message' => 'Promoción no encontrada.'], 404);
        }

        DB::table('barra_promociones')->where('id', $promotion)->delete();

        return response()->json(['message' => 'Promoción eliminada.']);
    }

    public function activePromotions(): JsonResponse
    {
        $today = now()->toDateString();

        $rows = DB::table('barra_promociones')
            ->leftJoin('barra_productos', 'barra_productos.id', '=', 'barra_promociones.id_producto')
            ->where('barra_promociones.activo', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('barra_promociones.fecha_inicio')
                    ->orWhere('barra_promociones.fecha_inicio', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('barra_promociones.fecha_fin')
                    ->orWhere('barra_promociones.fecha_fin', '>=', $today);
            })
            ->select(
                'barra_promociones.*',
                'barra_productos.nombre as producto_nombre'
            )
            ->orderBy('barra_promociones.nombre')
            ->get();

        return response()->json($rows);
    }

    // ── Dashboard en tiempo real ─────────────────────────────

    public function liveDashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
        ]);

        $eventId = (int) $data['event_id'];

        // Tickets: vendidos vs escaneados
        $ticketStats = DB::table('boletos')
            ->where('id_evento', $eventId)
            ->selectRaw(
                'COUNT(*) as total_boletos,
                SUM(CASE WHEN estado IN ("vendido","usado") THEN 1 ELSE 0 END) as vendidos,
                SUM(CASE WHEN estado = "usado" THEN 1 ELSE 0 END) as escaneados'
            )
            ->first();

        // Ventas de boletos por hora (ultimas 12 horas)
        $ticketSalesPerHour = DB::table('ventas')
            ->join('venta_detalle', 'venta_detalle.id_venta', '=', 'ventas.id')
            ->join('boletos', 'boletos.id', '=', 'venta_detalle.id_boleto')
            ->where('boletos.id_evento', $eventId)
            ->where('ventas.created_at', '>=', now()->subHours(12))
            ->selectRaw('DATE_FORMAT(ventas.created_at, "%H:00") as hora, COUNT(DISTINCT ventas.id) as ordenes, COALESCE(SUM(ventas.total), 0) as monto')
            ->groupByRaw('DATE_FORMAT(ventas.created_at, "%H:00")')
            ->orderBy('hora')
            ->get();

        // Barra: ventas por hora (ultimas 12 horas)
        $barSalesPerHour = DB::table('barra_ventas')
            ->where('id_evento', $eventId)
            ->where('created_at', '>=', now()->subHours(12))
            ->selectRaw('DATE_FORMAT(created_at, "%H:00") as hora, COUNT(*) as ventas, COALESCE(SUM(total), 0) as monto')
            ->groupByRaw('DATE_FORMAT(created_at, "%H:00")')
            ->orderBy('hora')
            ->get();

        // Barra: estado de cortes
        $barCuts = DB::table('barra_cortes')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'barra_cortes.id_usuario')
            ->where('barra_cortes.id_evento', $eventId)
            ->select(
                'barra_cortes.id',
                'barra_cortes.estado',
                'barra_cortes.monto_apertura',
                'barra_cortes.monto_efectivo_esperado',
                'barra_cortes.monto_cierre',
                'barra_cortes.diferencia',
                'usuarios.nombre as operador'
            )
            ->orderByDesc('barra_cortes.id')
            ->limit(10)
            ->get();

        // Barra: resumen general
        $barTotals = DB::table('barra_ventas')
            ->where('id_evento', $eventId)
            ->selectRaw(
                'COUNT(*) as total_ventas,
                COALESCE(SUM(total), 0) as monto_total,
                COALESCE(SUM(CASE WHEN metodo_pago = "efectivo" THEN total ELSE 0 END), 0) as efectivo,
                COALESCE(SUM(CASE WHEN metodo_pago = "tarjeta" THEN total ELSE 0 END), 0) as tarjeta,
                COALESCE(SUM(CASE WHEN metodo_pago = "transferencia" THEN total ELSE 0 END), 0) as transferencia'
            )
            ->first();

        // Productos mas vendidos de barra
        $topBarProducts = DB::table('barra_venta_detalle')
            ->join('barra_ventas', 'barra_ventas.id', '=', 'barra_venta_detalle.id_venta')
            ->join('barra_productos', 'barra_productos.id', '=', 'barra_venta_detalle.id_producto')
            ->where('barra_ventas.id_evento', $eventId)
            ->groupBy('barra_venta_detalle.id_producto', 'barra_productos.nombre')
            ->selectRaw('barra_productos.nombre, SUM(barra_venta_detalle.cantidad) as cantidad, SUM(barra_venta_detalle.subtotal) as ingreso')
            ->orderByDesc('cantidad')
            ->limit(5)
            ->get();

        // Ingresos totales (boletos + barra)
        $ticketRevenue = (float) DB::table('ventas')
            ->join('venta_detalle', 'venta_detalle.id_venta', '=', 'ventas.id')
            ->join('boletos', 'boletos.id', '=', 'venta_detalle.id_boleto')
            ->where('boletos.id_evento', $eventId)
            ->sum('ventas.total');

        // Alertas de stock
        $lowStockThreshold = 10;

        $stockAlerts = DB::table('barra_productos')
            ->where('activo', true)
            ->where('stock', '<=', $lowStockThreshold)
            ->orderBy('stock')
            ->select('id', 'nombre', 'stock')
            ->get();

        return response()->json([
            'tickets' => $ticketStats,
            'ticket_sales_per_hour' => $ticketSalesPerHour,
            'bar_sales_per_hour' => $barSalesPerHour,
            'bar_cuts' => $barCuts,
            'bar_totals' => $barTotals,
            'top_bar_products' => $topBarProducts,
            'revenue' => [
                'boletos' => $ticketRevenue,
                'barra' => (float) ($barTotals->monto_total ?? 0),
                'total' => $ticketRevenue + (float) ($barTotals->monto_total ?? 0),
            ],
            'stock_alerts' => $stockAlerts,
        ]);
    }

    // ── Bar Reports ──────────────────────────────────────────

    public function reportSalesByProduct(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $query = DB::table('barra_venta_detalle')
            ->join('barra_ventas', 'barra_ventas.id', '=', 'barra_venta_detalle.id_venta')
            ->join('barra_productos', 'barra_productos.id', '=', 'barra_venta_detalle.id_producto');

        if ($eventId) {
            $query->where('barra_ventas.id_evento', (int) $eventId);
        }

        $rows = $query
            ->groupBy('barra_venta_detalle.id_producto', 'barra_productos.nombre', 'barra_productos.categoria', 'barra_productos.precio')
            ->selectRaw('
                barra_productos.nombre as producto,
                barra_productos.categoria,
                barra_productos.precio as precio_unitario,
                SUM(barra_venta_detalle.cantidad) as cantidad_vendida,
                SUM(barra_venta_detalle.subtotal) as ingreso_total
            ')
            ->orderByDesc('cantidad_vendida')
            ->get();

        return response()->json($rows);
    }

    public function reportSalesByPayment(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $query = DB::table('barra_ventas');

        if ($eventId) {
            $query->where('id_evento', (int) $eventId);
        }

        $rows = $query
            ->groupBy('metodo_pago')
            ->selectRaw('
                metodo_pago,
                COUNT(*) as num_ventas,
                SUM(total) as monto_total
            ')
            ->orderByDesc('monto_total')
            ->get();

        return response()->json($rows);
    }

    public function reportSalesByOperator(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $query = DB::table('barra_ventas')
            ->join('usuarios', 'usuarios.id', '=', 'barra_ventas.id_usuario');

        if ($eventId) {
            $query->where('barra_ventas.id_evento', (int) $eventId);
        }

        $rows = $query
            ->groupBy('barra_ventas.id_usuario', 'usuarios.nombre')
            ->selectRaw('
                usuarios.nombre as operador,
                COUNT(*) as num_ventas,
                SUM(barra_ventas.total) as monto_total,
                AVG(barra_ventas.total) as ticket_promedio
            ')
            ->orderByDesc('monto_total')
            ->get();

        return response()->json($rows);
    }

    public function reportRevenueByEvent(): JsonResponse
    {
        $rows = DB::table('barra_ventas')
            ->join('eventos', 'eventos.id', '=', 'barra_ventas.id_evento')
            ->groupBy('barra_ventas.id_evento', 'eventos.nombre', 'eventos.fecha_inicio')
            ->selectRaw('
                eventos.nombre as evento,
                eventos.fecha_inicio,
                COUNT(*) as num_ventas,
                SUM(barra_ventas.total) as ingreso_total
            ')
            ->orderByDesc('ingreso_total')
            ->get();

        return response()->json($rows);
    }

    // ── Reembolsos / Cancelaciones ──────────────────────────

    public function refundSale(Request $request, int $sale): JsonResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $venta = DB::table('barra_ventas')->where('id', $sale)->first();

        if (! $venta) {
            return response()->json(['message' => 'Venta no encontrada.'], 404);
        }

        if (($venta->estado ?? 'activa') !== 'activa') {
            return response()->json(['message' => 'Esta venta ya fue cancelada o reembolsada.'], 422);
        }

        DB::transaction(function () use ($venta, $data, $request) {
            // Marcar venta como cancelada
            DB::table('barra_ventas')
                ->where('id', $venta->id)
                ->update(['estado' => 'cancelada', 'updated_at' => now()]);

            // Registrar reembolso
            DB::table('barra_reembolsos')->insert([
                'id_venta' => $venta->id,
                'id_usuario' => $request->user()?->id,
                'tipo' => 'total',
                'monto' => (float) $venta->total,
                'motivo' => $data['motivo'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Devolver stock de cada producto
            $detalles = DB::table('barra_venta_detalle')
                ->where('id_venta', $venta->id)
                ->get();

            foreach ($detalles as $detalle) {
                $product = DB::table('barra_productos')
                    ->where('id', $detalle->id_producto)
                    ->first();

                if ($product) {
                    $oldStock = (int) $product->stock;
                    $newStock = $oldStock + (int) $detalle->cantidad;

                    DB::table('barra_productos')
                        ->where('id', $detalle->id_producto)
                        ->update(['stock' => $newStock, 'updated_at' => now()]);

                    $this->logMovement(
                        $detalle->id_producto,
                        $request->user()?->id,
                        'entrada',
                        (int) $detalle->cantidad,
                        $oldStock,
                        $newStock,
                        'Reembolso de venta #' . $venta->id
                    );
                }
            }

            // Descontar del corte si fue efectivo
            if ($venta->metodo_pago === 'efectivo' && $venta->id_corte) {
                DB::table('barra_cortes')
                    ->where('id', (int) $venta->id_corte)
                    ->decrement('monto_efectivo_esperado', (float) $venta->total);
            }
        });

        return response()->json(['message' => 'Venta cancelada y stock devuelto.']);
    }

    public function refundHistory(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $rows = DB::table('barra_reembolsos')
            ->join('barra_ventas', 'barra_ventas.id', '=', 'barra_reembolsos.id_venta')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'barra_reembolsos.id_usuario')
            ->leftJoin('eventos', 'eventos.id', '=', 'barra_ventas.id_evento')
            ->select(
                'barra_reembolsos.*',
                'barra_ventas.total as venta_total',
                'barra_ventas.metodo_pago',
                'usuarios.nombre as operador',
                'eventos.nombre as evento'
            )
            ->when($eventId, fn($q) => $q->where('barra_ventas.id_evento', (int) $eventId))
            ->orderByDesc('barra_reembolsos.id')
            ->limit(50)
            ->get();

        return response()->json($rows);
    }
}
