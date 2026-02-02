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
        if (!$this->accessService->canAccessLocation($user, $term->stock_location_id, $companyId)) {
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

            $term = ResponsibilityTerm::create([
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

                ResponsibilityTermItem::create([
                    'responsibility_term_id' => $term->id,
                    'stock_product_id' => $productId,
                    'stock_id' => $stock->id,
                    'quantity' => $quantity,
                ]);
            }

            DB::commit();
            return $term->load(['stockLocation', 'items.stockProduct']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function devolver(int $id, $user): ResponsibilityTerm
    {
        $term = ResponsibilityTerm::with('items')->findOrFail($id);

        if ($term->status === ResponsibilityTerm::STATUS_DEVOLVIDO) {
            throw new \Exception('Este termo já foi devolvido.');
        }

        $companyId = (int) request()->header('company-id');
        if (!$this->accessService->canAccessLocation($user, $term->stock_location_id, $companyId)) {
            throw new \Exception('Acesso negado a este termo.');
        }

        DB::beginTransaction();
        try {
            foreach ($term->items as $item) {
                $this->stockMovementService->entradaTermoResponsabilidade(
                    (int) $item->stock_id,
                    (float) $item->quantity,
                    (int) $term->id,
                    $user,
                    $companyId
                );
            }

            $term->update([
                'status' => ResponsibilityTerm::STATUS_DEVOLVIDO,
                'returned_by' => $user->id,
                'returned_at' => Carbon::now(),
            ]);

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
}
