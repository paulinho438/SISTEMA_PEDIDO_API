<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Services\StockAccessService;
use App\Services\AssetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class StockService
{
    protected $accessService;
    protected $assetService;

    public function __construct(StockAccessService $accessService, AssetService $assetService)
    {
        $this->accessService = $accessService;
        $this->assetService = $assetService;
    }

    public function list(Request $request, $user)
    {
        $perPage = (int) $request->get('per_page', 15);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 15;
        
        $companyId = $request->header('company-id');
        $query = Stock::where('stocks.company_id', $companyId)
            ->with(['product', 'location']);

        // Aplicar filtro de acesso
        $this->accessService->applyLocationFilter($query, $user, $companyId, 'stock_location_id');

        // Filtro de busca por produto (código, referência ou descrição)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if ($request->filled('product_id')) {
            $query->where('stock_product_id', $request->get('product_id'));
        }

        if ($request->filled('stock_product_id')) {
            $query->where('stock_product_id', $request->get('stock_product_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('stock_location_id', $request->get('location_id'));
        }

        if ($request->filled('has_available')) {
            $hasAvailable = $request->get('has_available');
            // Aceitar boolean, string 'true'/'false', ou '1'/'0'
            if ($hasAvailable === true || $hasAvailable === 'true' || $hasAvailable === '1' || $hasAvailable === 1) {
                $query->where('quantity_available', '>', 0);
            }
        }

        if ($request->filled('has_reserved')) {
            if ($request->boolean('has_reserved')) {
                $query->where('quantity_reserved', '>', 0);
            }
        }

        if ($request->filled('low_stock')) {
            if ($request->boolean('low_stock')) {
                $query->whereRaw('quantity_available <= min_stock AND min_stock IS NOT NULL');
            }
        }

        $stocks = $query->orderByDesc('last_movement_at')->paginate($perPage);

        // Para cada stock com reserva, buscar a última movimentação de reserva
        foreach ($stocks->items() as $stock) {
            if ($stock->quantity_reserved > 0) {
                // Buscar a última movimentação que criou reserva
                // Priorizar movimentações com observation contendo "Reserva"
                $lastReservationMovement = StockMovement::where('stock_id', $stock->id)
                    ->where('movement_type', 'ajuste')
                    ->where('quantity', '<', 0)
                    ->whereNotNull('user_id')
                    ->where(function($q) {
                        $q->where('observation', 'like', '%Reserva%')
                          ->orWhere('observation', 'like', '%reserva%');
                    })
                    ->with('user')
                    ->orderByDesc('created_at')
                    ->first();

                // Se não encontrou com "Reserva" na observação, buscar qualquer movimentação de ajuste recente
                // que não seja cancelamento ou saída
                if (!$lastReservationMovement) {
                    $lastReservationMovement = StockMovement::where('stock_id', $stock->id)
                        ->where('movement_type', 'ajuste')
                        ->where('quantity', '<', 0)
                        ->whereNotNull('user_id')
                        ->where(function($q) {
                            $q->where(function($subQ) {
                                $subQ->where('observation', 'not like', '%Cancelamento%')
                                     ->where('observation', 'not like', '%cancelamento%')
                                     ->where('observation', 'not like', '%Saída%')
                                     ->where('observation', 'not like', '%saída%')
                                     ->where('observation', 'not like', '%Transferência%')
                                     ->where('observation', 'not like', '%transferência%');
                            })
                            ->orWhereNull('observation');
                        })
                        ->with('user')
                        ->orderByDesc('created_at')
                        ->first();
                }

                if ($lastReservationMovement && $lastReservationMovement->user) {
                    // Usar movement_date se disponível, senão usar created_at
                    $reservationDate = $lastReservationMovement->movement_date 
                        ? \Carbon\Carbon::parse($lastReservationMovement->movement_date)
                        : \Carbon\Carbon::parse($lastReservationMovement->created_at);
                    
                    $stock->reservation_date = $reservationDate;
                    $stock->reservation_user = $lastReservationMovement->user;
                }
            }
        }

        return $stocks;
    }

    /**
     * Buscar reservas agrupadas por solicitante
     */
    public function listReservasPorSolicitante(Request $request, $user)
    {
        $companyId = (int) $request->header('company-id');
        
        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }

        $query = Stock::where('stocks.company_id', $companyId)
            ->where('quantity_reserved', '>', 0)
            ->with(['product', 'location']);

        // Aplicar filtro de acesso
        $this->accessService->applyLocationFilter($query, $user, $companyId, 'stock_location_id');

        $stocks = $query->get();

        // Buscar informações de reserva para cada estoque
        $reservasPorSolicitante = [];
        
        foreach ($stocks as $stock) {
            // Buscar a última movimentação de reserva
            $lastReservationMovement = StockMovement::where('stock_id', $stock->id)
                ->where('movement_type', 'ajuste')
                ->where('quantity', '<', 0)
                ->whereNotNull('user_id')
                ->where(function($q) {
                    $q->where('observation', 'like', '%Reserva%')
                      ->orWhere('observation', 'like', '%reserva%');
                })
                ->with('user')
                ->orderByDesc('created_at')
                ->first();

            // Se não encontrou com "Reserva" na observação, buscar qualquer movimentação de ajuste recente
            if (!$lastReservationMovement) {
                $lastReservationMovement = StockMovement::where('stock_id', $stock->id)
                    ->where('movement_type', 'ajuste')
                    ->where('quantity', '<', 0)
                    ->whereNotNull('user_id')
                    ->where(function($q) {
                        $q->where(function($subQ) {
                            $subQ->where('observation', 'not like', '%Cancelamento%')
                                 ->where('observation', 'not like', '%cancelamento%')
                                 ->where('observation', 'not like', '%Saída%')
                                 ->where('observation', 'not like', '%saída%')
                                 ->where('observation', 'not like', '%Transferência%')
                                 ->where('observation', 'not like', '%transferência%');
                        })
                        ->orWhereNull('observation');
                    })
                    ->with('user')
                    ->orderByDesc('created_at')
                    ->first();
            }

            if ($lastReservationMovement && $lastReservationMovement->user) {
                $userId = $lastReservationMovement->user->id;
                $userName = $lastReservationMovement->user->nome_completo ?? $lastReservationMovement->user->name ?? 'N/A';
                
                if (!isset($reservasPorSolicitante[$userId])) {
                    $reservasPorSolicitante[$userId] = [
                        'user_id' => $userId,
                        'user_name' => $userName,
                        'reservas' => []
                    ];
                }

                $reservationDate = $lastReservationMovement->movement_date 
                    ? \Carbon\Carbon::parse($lastReservationMovement->movement_date)
                    : \Carbon\Carbon::parse($lastReservationMovement->created_at);

                $reservasPorSolicitante[$userId]['reservas'][] = [
                    'id' => $stock->id,
                    'product' => [
                        'id' => $stock->product->id ?? null,
                        'code' => $stock->product->code ?? null,
                        'description' => $stock->product->description ?? null,
                        'reference' => $stock->product->reference ?? null,
                        'unit' => $stock->product->unit ?? null,
                    ],
                    'location' => [
                        'id' => $stock->location->id ?? null,
                        'name' => $stock->location->name ?? null,
                        'code' => $stock->location->code ?? null,
                    ],
                    'quantity_reserved' => (float) $stock->quantity_reserved,
                    'reservation_date' => $reservationDate->format('d/m/Y'),
                ];
            }
        }

        return array_values($reservasPorSolicitante);
    }

    /**
     * Dar saída múltipla de reservas
     * @param array $items Array de items com stock_id e quantity
     * @return array Dados para geração do PDF
     */
    public function darSaidaMultipla(array $items): array
    {
        $validator = Validator::make(['items' => $items], [
            'items' => 'required|array|min:1',
            'items.*.stock_id' => 'required|integer|exists:stocks,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        DB::beginTransaction();

        try {
            $saidas = [];
            $solicitante = null;
            $company = null;
            
            foreach ($items as $itemData) {
                $stock = Stock::with(['product', 'location', 'company'])->findOrFail($itemData['stock_id']);
                
                if (!$solicitante) {
                    // Buscar informações do solicitante da primeira reserva
                    $lastReservationMovement = StockMovement::where('stock_id', $stock->id)
                        ->where('movement_type', 'ajuste')
                        ->where('quantity', '<', 0)
                        ->whereNotNull('user_id')
                        ->with('user')
                        ->orderByDesc('created_at')
                        ->first();
                    
                    if ($lastReservationMovement && $lastReservationMovement->user) {
                        $solicitante = $lastReservationMovement->user;
                    }
                }
                
                if (!$company) {
                    $company = $stock->company;
                }

                $quantity = (float) $itemData['quantity'];
                
                if ($stock->quantity_reserved < $quantity) {
                    throw new \Exception("Quantidade reservada insuficiente para o produto {$stock->product->description}.");
                }

                // Dar saída
                $reservedBefore = $stock->quantity_reserved;
                $reservedAfter = $stock->quantity_reserved - $quantity;
                $totalBefore = $stock->quantity_total;
                $totalAfter = $stock->quantity_total - $quantity;

                $this->updateModelWithStringTimestamps($stock, [
                    'quantity_reserved' => $reservedAfter,
                    'quantity_total' => $totalAfter,
                    'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                // Criar movimentação
                $this->insertMovementWithStringTimestamps([
                    'stock_id' => $stock->id,
                    'stock_product_id' => $stock->stock_product_id,
                    'stock_location_id' => $stock->stock_location_id,
                    'movement_type' => 'ajuste',
                    'quantity' => -$quantity,
                    'quantity_before' => $reservedBefore,
                    'quantity_after' => $reservedAfter,
                    'reference_type' => 'ajuste_manual',
                    'observation' => $itemData['observation'] ?? 'Saída de produto reservado',
                    'user_id' => auth()->id(),
                    'company_id' => $stock->company_id,
                    'movement_date' => Carbon::now()->toDateString(),
                ]);

                $saidas[] = [
                    'stock_id' => $stock->id,
                    'product' => $stock->product,
                    'location' => $stock->location,
                    'quantity' => $quantity,
                    'unit' => $stock->product->unit ?? 'UN',
                    'observation' => $itemData['observation'] ?? null,
                ];
            }

            DB::commit();

            return [
                'solicitante' => $solicitante,
                'company' => $company,
                'items' => $saidas,
                'data_saida' => Carbon::now()->format('d/m/Y'),
                'hora_saida' => Carbon::now()->format('H:i:s'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function find($id)
    {
        return Stock::with(['product', 'location', 'movements'])->findOrFail($id);
    }

    public function reservar(Stock $stock, float $quantity, ?string $observation = null): Stock
    {
        $validator = Validator::make(['quantity' => $quantity], [
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($stock->quantity_available < $quantity) {
            throw new \Exception('Quantidade disponível insuficiente.');
        }

        DB::beginTransaction();

        try {
            $quantityBefore = $stock->quantity_available;
            $quantityAfter = $stock->quantity_available - $quantity;
            $reservedBefore = $stock->quantity_reserved;
            $reservedAfter = $stock->quantity_reserved + $quantity;

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_available' => $quantityAfter,
                'quantity_reserved' => $reservedAfter,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Criar movimentação
            $movementId = $this->insertMovementWithStringTimestamps([
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => 'ajuste',
                'quantity' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => 'ajuste_manual',
                'observation' => $observation ?? 'Reserva de quantidade',
                'user_id' => auth()->id(),
                'company_id' => $stock->company_id,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            DB::commit();

            return $stock->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function liberar(Stock $stock, float $quantity, ?string $observation = null): Stock
    {
        $validator = Validator::make(['quantity' => $quantity], [
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($stock->quantity_reserved < $quantity) {
            throw new \Exception('Quantidade reservada insuficiente.');
        }

        DB::beginTransaction();

        try {
            $reservedBefore = $stock->quantity_reserved;
            $reservedAfter = $stock->quantity_reserved - $quantity;
            $availableBefore = $stock->quantity_available;
            $availableAfter = $stock->quantity_available + $quantity;

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_available' => $availableAfter,
                'quantity_reserved' => $reservedAfter,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Criar movimentação
            $this->insertMovementWithStringTimestamps([
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => 'ajuste',
                'quantity' => $quantity,
                'quantity_before' => $availableBefore,
                'quantity_after' => $availableAfter,
                'reference_type' => 'ajuste_manual',
                'observation' => $observation ?? 'Liberação de quantidade',
                'user_id' => auth()->id(),
                'company_id' => $stock->company_id,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            DB::commit();

            return $stock->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancelar reserva informando que o produto não está mais disponível
     * Libera a quantidade reservada de volta para disponível
     */
    public function cancelarReserva(Stock $stock, float $quantity, string $motivo): Stock
    {
        $validator = Validator::make([
            'quantity' => $quantity,
            'motivo' => $motivo,
        ], [
            'quantity' => 'required|numeric|min:0.0001',
            'motivo' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($stock->quantity_reserved < $quantity) {
            throw new \Exception('Quantidade reservada insuficiente.');
        }

        DB::beginTransaction();

        try {
            $reservedBefore = $stock->quantity_reserved;
            $reservedAfter = $stock->quantity_reserved - $quantity;
            $availableBefore = $stock->quantity_available;
            $availableAfter = $stock->quantity_available + $quantity;

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_available' => $availableAfter,
                'quantity_reserved' => $reservedAfter,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Criar movimentação com motivo do cancelamento
            $this->insertMovementWithStringTimestamps([
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => 'ajuste',
                'quantity' => $quantity,
                'quantity_before' => $availableBefore,
                'quantity_after' => $availableAfter,
                'reference_type' => 'ajuste_manual',
                'observation' => 'Cancelamento de reserva - Produto não disponível. Motivo: ' . $motivo,
                'user_id' => auth()->id(),
                'company_id' => $stock->company_id,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            DB::commit();

            return $stock->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Dar saída do produto (baixar do estoque total e liberar da reserva)
     */
    public function darSaida(Stock $stock, float $quantity, ?string $observation = null): Stock
    {
        $validator = Validator::make(['quantity' => $quantity], [
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($stock->quantity_reserved < $quantity) {
            throw new \Exception('Quantidade reservada insuficiente.');
        }

        DB::beginTransaction();

        try {
            $reservedBefore = $stock->quantity_reserved;
            $reservedAfter = $stock->quantity_reserved - $quantity;
            $totalBefore = $stock->quantity_total;
            $totalAfter = $stock->quantity_total - $quantity;

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_reserved' => $reservedAfter,
                'quantity_total' => $totalAfter,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Criar movimentação de saída
            $this->insertMovementWithStringTimestamps([
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => 'saida',
                'quantity' => -$quantity,
                'quantity_before' => $totalBefore,
                'quantity_after' => $totalAfter,
                'reference_type' => 'ajuste_manual',
                'observation' => $observation ?? 'Saída de produto reservado',
                'user_id' => auth()->id(),
                'company_id' => $stock->company_id,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            DB::commit();

            return $stock->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Transferir produto para outro local e dar saída
     */
    public function transferirESair(
        Stock $stockOrigem,
        int $locationDestinoId,
        float $quantity,
        ?string $observation = null
    ): array {
        $validator = Validator::make([
            'quantity' => $quantity,
            'location_id' => $locationDestinoId,
        ], [
            'quantity' => 'required|numeric|min:0.0001',
            'location_id' => 'required|exists:stock_locations,id',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($stockOrigem->quantity_reserved < $quantity) {
            throw new \Exception('Quantidade reservada insuficiente.');
        }

        if ($stockOrigem->stock_location_id == $locationDestinoId) {
            throw new \Exception('O local de origem e destino devem ser diferentes.');
        }

        $companyId = $stockOrigem->company_id;

        DB::beginTransaction();

        try {
            // 1. Liberar da reserva na origem
            $reservedAfter = $stockOrigem->quantity_reserved - $quantity;

            $this->updateModelWithStringTimestamps($stockOrigem, [
                'quantity_reserved' => $reservedAfter,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // 2. Baixar do total na origem
            $totalBefore = $stockOrigem->quantity_total;
            $totalAfter = $stockOrigem->quantity_total - $quantity;

            $this->updateModelWithStringTimestamps($stockOrigem, [
                'quantity_total' => $totalAfter,
            ]);

            // 3. Criar movimentação de saída na origem
            $movementFromId = $this->insertMovementWithStringTimestamps([
                'stock_id' => $stockOrigem->id,
                'stock_product_id' => $stockOrigem->stock_product_id,
                'stock_location_id' => $stockOrigem->stock_location_id,
                'movement_type' => 'saida',
                'quantity' => -$quantity,
                'quantity_before' => $totalBefore,
                'quantity_after' => $totalAfter,
                'reference_type' => 'transferencia',
                'observation' => ($observation ?? 'Transferência e saída') . ' (Origem)',
                'user_id' => auth()->id(),
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);
            $movementFrom = StockMovement::find($movementFromId);

            // 4. Buscar ou criar estoque no destino
            $stockDestino = Stock::where('stock_product_id', $stockOrigem->stock_product_id)
                ->where('stock_location_id', $locationDestinoId)
                ->where('company_id', $companyId)
                ->first();
            
            if (!$stockDestino) {
                $stockDestinoId = $this->insertStockWithStringTimestamps([
                    'stock_product_id' => $stockOrigem->stock_product_id,
                    'stock_location_id' => $locationDestinoId,
                    'company_id' => $companyId,
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'quantity_total' => 0,
                ]);
                $stockDestino = Stock::find($stockDestinoId);
            }

            // 5. Adicionar ao estoque RESERVADO no destino (mantém o status de reservado)
            $reservedDestinoBefore = $stockDestino->quantity_reserved;
            $totalDestinoBefore = $stockDestino->quantity_total;
            $reservedDestinoAfter = $stockDestino->quantity_reserved + $quantity;
            $totalDestinoAfter = $stockDestino->quantity_total + $quantity;

            $this->updateModelWithStringTimestamps($stockDestino, [
                'quantity_reserved' => $reservedDestinoAfter,
                'quantity_total' => $totalDestinoAfter,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // 6. Criar movimentação de entrada no destino (como reservado)
            $movementToId = $this->insertMovementWithStringTimestamps([
                'stock_id' => $stockDestino->id,
                'stock_product_id' => $stockOrigem->stock_product_id,
                'stock_location_id' => $locationDestinoId,
                'movement_type' => 'entrada',
                'quantity' => $quantity,
                'quantity_before' => $totalDestinoBefore,
                'quantity_after' => $totalDestinoAfter,
                'reference_type' => 'transferencia',
                'observation' => ($observation ?? 'Transferência de produto reservado') . ' (Destino - Entrada como RESERVADO)',
                'user_id' => auth()->id(),
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);
            $movementTo = StockMovement::find($movementToId);

            DB::commit();

            return [
                'stock_origem' => $stockOrigem->fresh(['product', 'location']),
                'stock_destino' => $stockDestino->fresh(['product', 'location']),
                'movement_from' => $movementFrom,
                'movement_to' => $movementTo,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Dar saída do produto e criar ativo automaticamente
     */
    public function darSaidaECriarAtivo(
        Stock $stock,
        float $quantity,
        array $assetData,
        ?string $observation = null
    ): array {
        $validator = Validator::make(['quantity' => $quantity], [
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($stock->quantity_reserved < $quantity) {
            throw new \Exception('Quantidade reservada insuficiente.');
        }

        $companyId = $stock->company_id;

        DB::beginTransaction();

        try {
            // 1. Dar saída do estoque
            $reservedAfter = $stock->quantity_reserved - $quantity;
            $totalBefore = $stock->quantity_total;
            $totalAfter = $stock->quantity_total - $quantity;

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_reserved' => $reservedAfter,
                'quantity_total' => $totalAfter,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // 2. Criar movimentação de saída
            $this->insertMovementWithStringTimestamps([
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => 'saida',
                'quantity' => -$quantity,
                'quantity_before' => $totalBefore,
                'quantity_after' => $totalAfter,
                'reference_type' => 'ajuste_manual',
                'observation' => ($observation ?? 'Saída e criação de ativo') . ' - Produto: ' . ($stock->product->description ?? ''),
                'user_id' => auth()->id(),
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            // 3. Buscar valor da nota fiscal (se houver movimentação de entrada associada)
            $invoiceItemValue = $this->buscarValorNotaFiscal($stock, $quantity);
            
            // 4. Preparar dados do ativo
            $assetData['description'] = $assetData['description'] ?? $stock->product->description;
            $assetData['acquisition_date'] = $assetData['acquisition_date'] ?? Carbon::now()->toDateString();
            // Usar valor da nota fiscal se disponível, senão usar valor informado pelo usuário
            $assetData['value_brl'] = $invoiceItemValue ?? ($assetData['value_brl'] ?? 0);
            $assetData['status'] = 'incluido';
            $assetData['item_quantity'] = $quantity;
            $assetData['purchase_reference_type'] = 'estoque';
            $assetData['purchase_reference_id'] = $stock->id;
            $assetData['purchase_reference_number'] = 'ESTOQUE-' . $stock->id;
            
            // Se encontrou nota fiscal, adicionar referência
            if ($invoiceItemValue !== null) {
                $invoiceItem = StockMovement::where('stock_id', $stock->id)
                    ->where('movement_type', 'entrada')
                    ->whereNotNull('purchase_invoice_item_id')
                    ->where('purchase_invoice_item_id', '>', 0)
                    ->orderByDesc('created_at')
                    ->first();
                
                if ($invoiceItem && $invoiceItem->purchaseInvoiceItem) {
                    $invoice = $invoiceItem->purchaseInvoiceItem->invoice;
                    $assetData['purchase_reference_type'] = 'nota_fiscal';
                    $assetData['purchase_reference_id'] = $invoice->id;
                    $assetData['purchase_reference_number'] = $invoice->invoice_number . ($invoice->invoice_series ? '/' . $invoice->invoice_series : '');
                }
            }
            
            // Remover cost_center_id se for string (código do Protheus)
            // O campo cost_center_id espera um bigint (ID da tabela costcenter local)
            // Se vier como string, significa que é código do Protheus e não pode ser salvo diretamente
            if (isset($assetData['cost_center_id']) && is_string($assetData['cost_center_id'])) {
                unset($assetData['cost_center_id']);
            }
            
            // Remover cost_center_selected dos dados antes de criar o ativo (não é campo do banco)
            unset($assetData['cost_center_selected']);

            // 5. Criar ativo
            $asset = $this->assetService->create($assetData, $companyId, auth()->id());

            // 6. Criar movimentação inicial do ativo com informações do estoque
            AssetMovement::create([
                'asset_id' => $asset->id,
                'movement_type' => 'cadastro',
                'movement_date' => $asset->acquisition_date ?? Carbon::now()->toDateString(),
                'to_branch_id' => $asset->branch_id,
                'to_location_id' => $asset->location_id ?? $stock->stock_location_id,
                'to_responsible_id' => $asset->responsible_id,
                'to_cost_center_id' => $asset->cost_center_id,
                'observation' => 'Criado a partir de baixa de estoque. Produto: ' . ($stock->product->code ?? '') . ' - ' . ($stock->product->description ?? ''),
                'user_id' => auth()->id(),
                'reference_type' => 'estoque',
                'reference_id' => $stock->id,
            ]);

            DB::commit();

            return [
                'stock' => $stock->fresh(['product', 'location']),
                'asset' => $asset,
                'movement' => $movement,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Buscar valor unitário da nota fiscal para o estoque
     * Usa FIFO (First In, First Out) - busca a entrada mais antiga com nota fiscal
     */
    protected function buscarValorNotaFiscal(Stock $stock, float $quantity): ?float
    {
        // Buscar movimentações de entrada com nota fiscal associada
        $entryMovements = StockMovement::where('stock_id', $stock->id)
            ->where('movement_type', 'entrada')
            ->whereNotNull('purchase_invoice_item_id')
            ->where('purchase_invoice_item_id', '>', 0)
            ->orderBy('created_at', 'asc') // FIFO - mais antiga primeiro
            ->with('purchaseInvoiceItem')
            ->get();

        if ($entryMovements->isEmpty()) {
            return null;
        }

        // Calcular quantidade acumulada até encontrar a quantidade necessária
        $accumulatedQuantity = 0;
        $lastMovement = null;

        foreach ($entryMovements as $movement) {
            $accumulatedQuantity += abs($movement->quantity);
            $lastMovement = $movement;
            
            if ($accumulatedQuantity >= $quantity) {
                break;
            }
        }

        // Se encontrou movimento com nota fiscal, retornar o valor unitário
        if ($lastMovement && $lastMovement->purchaseInvoiceItem) {
            return (float) $lastMovement->purchaseInvoiceItem->unit_price;
        }

        return null;
    }

    /**
     * Helper para atualizar modelos com timestamps como strings (compatível com SQL Server)
     */
    private function updateModelWithStringTimestamps($model, array $data)
    {
        // Remover campos que não devem ser atualizados
        unset($data['id'], $data['created_at']);
        
        // Adicionar updated_at como string
        $data['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Se não há dados para atualizar além do updated_at, apenas atualizar o timestamp
        if (empty($data) || (count($data) === 1 && isset($data['updated_at']))) {
            $table = $model->getTable();
            $id = $model->getKey();
            $idColumn = $model->getKeyName();
            
            $sql = "UPDATE [{$table}] SET [updated_at] = CAST(? AS DATETIME2) WHERE [{$idColumn}] = ?";
            DB::statement($sql, [$data['updated_at'], $id]);
            $model->refresh();
            return $model;
        }
        
        // Usar DB::statement() para garantir que campos de data sejam tratados corretamente
        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            // Campos de data precisam de CAST
            if ($column === 'updated_at' || $column === 'last_movement_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }
        
        $values[] = $id; // Para o WHERE
        
        $sql = "UPDATE [{$table}] SET " . implode(', ', $placeholders) . " WHERE [{$idColumn}] = ?";
        
        DB::statement($sql, $values);
        
        // Recarregar o modelo para ter os valores atualizados
        $model->refresh();
        
        return $model;
    }

    /**
     * Helper para inserir movimentações com timestamps como strings (compatível com SQL Server)
     */
    private function insertMovementWithStringTimestamps($data)
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            // Campos de data precisam de CAST
            if ($column === 'movement_date') {
                $placeholders[] = "CAST(? AS DATE)";
            } else {
                $placeholders[] = "?";
            }
            $values[] = $data[$column];
        }
        
        // Adicionar campos de data com CAST
        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [stock_movements] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Helper para inserir estoque com timestamps como strings (compatível com SQL Server)
     */
    private function insertStockWithStringTimestamps($data)
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            $placeholders[] = "?";
            $values[] = $data[$column];
        }
        
        // Adicionar campos de data com CAST
        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [stocks] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }
}

