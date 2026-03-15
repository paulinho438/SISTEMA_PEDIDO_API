<?php

namespace App\Services;

use App\Models\ResponsibilityTerm;
use App\Models\ResponsibilityTermItem;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ResponsibilityTermService
{
    public function __construct(
        protected StockMovementService $stockMovementService,
        protected StockAccessService $accessService
    ) {
    }

    public function list(Request $request, $user): LengthAwarePaginator
    {
        $companyId = (int) $request->header('company-id');
        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $perPage = $perPage > 0 ? $perPage : 15;

        $query = ResponsibilityTerm::where('company_id', $companyId)
            ->with(['stockLocation', 'items.stockProduct']);

        $locationIds = $this->accessService->getAccessibleLocationIds($user, $companyId);
        $query->whereIn('stock_location_id', $locationIds);

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('search')) {
            $term = '%' . $request->get('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('numero', 'like', $term)
                    ->orWhere('responsible_name', 'like', $term)
                    ->orWhere('project', 'like', $term);
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id, $user): ResponsibilityTerm
    {
        $term = ResponsibilityTerm::with(['stockLocation', 'items.stockProduct', 'items.stock', 'createdByUser'])
            ->findOrFail($id);

        $companyId = (int) request()->header('company-id');
        $canByLocation = $this->accessService->canAccessLocation($user, $term->stock_location_id, $companyId);
        $canByPermission = $companyId && (int) $term->company_id === $companyId
            && ($user->hasPermission('view_estoque_movimentacoes') || $user->hasPermission('view_estoque_almoxarifes'));
        if (!$canByLocation && !$canByPermission) {
            throw new \Exception('Acesso negado a este termo.');
        }

        return $term;
    }

    public function store(Request $request, $user): ResponsibilityTerm
    {
        $validator = Validator::make($request->all(), [
            'responsible_name' => 'required|string|max:255',
            'cpf' => 'nullable|string|max:20',
            'project' => 'nullable|string|max:255',
            'stock_location_id' => 'required|exists:stock_locations,id',
            'observation' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.stock_product_id' => 'required|exists:stock_products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $companyId = (int) $request->header('company-id');
        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }

        $locationId = (int) $request->input('stock_location_id');
        if (!$this->accessService->canAccessLocation($user, $locationId, $companyId)) {
            throw new \Exception('Acesso negado a este local de estoque.');
        }

        DB::beginTransaction();
        try {
            $numero = $this->generateNumero($companyId);

            $termId = $this->insertWithStringTimestamps('responsibility_terms', [
                'numero' => $numero,
                'responsible_name' => $request->input('responsible_name'),
                'cpf' => $request->input('cpf'),
                'project' => $request->input('project'),
                'stock_location_id' => $locationId,
                'status' => ResponsibilityTerm::STATUS_ABERTO,
                'company_id' => $companyId,
                'created_by' => $user->id,
                'observation' => $request->input('observation'),
            ]);
            $term = ResponsibilityTerm::findOrFail($termId);

            foreach ($request->input('items') as $row) {
                $productId = (int) $row['stock_product_id'];
                $quantity = (float) $row['quantity'];

                $stock = Stock::where('stock_product_id', $productId)
                    ->where('stock_location_id', $locationId)
                    ->where('company_id', $companyId)
                    ->first();

                if (!$stock) {
                    throw new \Exception("Produto não possui estoque no local selecionado.");
                }
                if ($stock->quantity_available < $quantity) {
                    $product = $stock->product;
                    $nome = $product ? $product->description : $productId;
                    throw new \Exception("Quantidade insuficiente de \"{$nome}\" no estoque (disponível: {$stock->quantity_available}).");
                }

                $this->stockMovementService->saidaTermoResponsabilidade(
                    (int) $stock->id,
                    $quantity,
                    (int) $term->id,
                    $user,
                    $companyId
                );

                $this->insertWithStringTimestamps('responsibility_term_items', [
                    'responsibility_term_id' => $term->id,
                    'stock_product_id' => $productId,
                    'stock_id' => $stock->id,
                    'quantity' => $quantity,
                    'quantity_returned' => 0,
                ]);
            }

            DB::commit();
            return $term->load(['stockLocation', 'items.stockProduct']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function devolver(int $id, $user, array $returnItems = []): ResponsibilityTerm
    {
        $term = ResponsibilityTerm::with('items')->findOrFail($id);

        if ($term->status === ResponsibilityTerm::STATUS_DEVOLVIDO) {
            throw new \Exception('Este termo já foi devolvido.');
        }

        $companyId = (int) request()->header('company-id');
        $canByLocation = $this->accessService->canAccessLocation($user, $term->stock_location_id, $companyId);
        $canByPermission = $companyId && (int) $term->company_id === $companyId
            && ($user->hasPermission('view_estoque_movimentacoes') || $user->hasPermission('view_estoque_almoxarifes'));
        if (!$canByLocation && !$canByPermission) {
            throw new \Exception('Acesso negado a este termo.');
        }

        DB::beginTransaction();
        try {
            $itemsById = $term->items->keyBy('id');
            $movements = [];

            if (empty($returnItems)) {
                foreach ($term->items as $item) {
                    $pending = round((float) $item->quantity - (float) ($item->quantity_returned ?? 0), 4);
                    if ($pending > 0) {
                        $movements[] = ['item' => $item, 'quantity' => $pending];
                    }
                }
            } else {
                foreach ($returnItems as $row) {
                    $itemId = (int) ($row['id'] ?? 0);
                    $quantity = (float) ($row['quantity'] ?? 0);
                    $item = $itemsById->get($itemId);

                    if (!$item) {
                        throw new \Exception('Item informado não pertence ao termo.');
                    }
                    if ($quantity <= 0) {
                        throw new \Exception('Quantidade de devolução deve ser maior que zero.');
                    }

                    $pending = round((float) $item->quantity - (float) ($item->quantity_returned ?? 0), 4);
                    if ($pending <= 0) {
                        throw new \Exception('Um dos itens selecionados já foi devolvido totalmente.');
                    }
                    if ($quantity > $pending) {
                        throw new \Exception('Quantidade de devolução maior que o pendente para um dos itens.');
                    }

                    $movements[] = ['item' => $item, 'quantity' => $quantity];
                }
            }

            if (empty($movements)) {
                throw new \Exception('Não há itens pendentes para devolução.');
            }

            foreach ($movements as $movement) {
                /** @var ResponsibilityTermItem $item */
                $item = $movement['item'];
                $quantity = (float) $movement['quantity'];

                $this->stockMovementService->entradaTermoResponsabilidade(
                    (int) $item->stock_id,
                    $quantity,
                    (int) $term->id,
                    $user,
                    $companyId
                );

                $itemUpdateData = [
                    'quantity_returned' => round((float) ($item->quantity_returned ?? 0) + $quantity, 4),
                ];
                if ($itemUpdateData['quantity_returned'] >= (float) $item->quantity) {
                    $itemUpdateData['returned_at'] = Carbon::now()->format('Y-m-d H:i:s');
                }
                $this->updateModelWithStringTimestamps($item, $itemUpdateData, ['returned_at']);
            }

            $hasPending = $term->items()->whereRaw('(quantity - quantity_returned) > 0.0000')->exists();
            $this->updateModelWithStringTimestamps($term, [
                'status' => $hasPending ? ResponsibilityTerm::STATUS_PARCIAL : ResponsibilityTerm::STATUS_DEVOLVIDO,
                'returned_by' => $hasPending ? null : $user->id,
                'returned_at' => $hasPending ? null : Carbon::now()->format('Y-m-d H:i:s'),
            ], ['returned_at']);

            DB::commit();
            return $term->fresh(['stockLocation', 'items.stockProduct']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function generateNumero(int $companyId): string
    {
        $year = date('Y');
        $last = ResponsibilityTerm::where('company_id', $companyId)
            ->whereRaw('YEAR(created_at) = ?', [$year])
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) preg_replace('/^\D+-?\d+-/', '', $last->numero) + 1) : 1;
        return sprintf('TRM-%s-%05d', $year, $seq);
    }

    /**
     * Helper para inserir com timestamps string/cast (padrão SQL Server do projeto)
     */
    private function insertWithStringTimestamps(string $table, array $data): int
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;

        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;

        $columnsBracketed = array_map(fn ($col) => "[{$col}]", $columns);
        $sql = "INSERT INTO [{$table}] (" . implode(', ', $columnsBracketed) . ") OUTPUT INSERTED.[id] VALUES (" . implode(', ', $placeholders) . ")";
        $result = DB::select($sql, $values);

        return (int) $result[0]->id;
    }

    /**
     * Helper para update com timestamps string/cast (padrão SQL Server do projeto)
     */
    private function updateModelWithStringTimestamps($model, array $data, array $dateFields = [])
    {
        unset($data['id'], $data['created_at']);
        $data['updated_at'] = now()->format('Y-m-d H:i:s');

        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();

        $columns = array_keys($data);
        $setters = [];
        $values = [];

        foreach ($columns as $column) {
            $isDateField = $column === 'updated_at' || in_array($column, $dateFields, true);
            $setters[] = $isDateField ? "[{$column}] = CAST(? AS DATETIME2)" : "[{$column}] = ?";
            $values[] = $data[$column];
        }

        $values[] = $id;
        $sql = "UPDATE [{$table}] SET " . implode(', ', $setters) . " WHERE [{$idColumn}] = ?";
        DB::statement($sql, $values);

        $model->refresh();
        return $model;
    }
}
