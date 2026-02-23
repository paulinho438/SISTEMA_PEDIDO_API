<?php

namespace App\Http\Controllers;

use App\Models\ResponsibilityTerm;
use App\Services\ResponsibilityTermService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ResponsibilityTermController extends Controller
{
    public function __construct(
        protected ResponsibilityTermService $service
    ) {
    }

    /**
     * Listar termos de responsabilidade (ferramentas).
     * Permite: view_estoque_movimentacoes ou view_estoque_almoxarifes (ex.: Supervisor Almoxarifado).
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->hasPermission('view_estoque_movimentacoes') && !$user->hasPermission('view_estoque_almoxarifes'))) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $paginator = $this->service->list($request, $user);
            $data = $paginator->getCollection()->map(fn ($term) => $this->termToArray($term));
            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Exibir um termo.
     * Permite: view_estoque_movimentacoes ou view_estoque_almoxarifes.
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->hasPermission('view_estoque_movimentacoes') && !$user->hasPermission('view_estoque_almoxarifes'))) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $term = $this->service->find($id, $user);
            return response()->json(['data' => $this->termToArray($term, true)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Criar termo de responsabilidade (saída de estoque).
     * Permite: view_estoque_movimentacoes, view_estoque_movimentacoes_create ou view_estoque_almoxarifes.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $can = $user && ($user->hasPermission('view_estoque_movimentacoes') || $user->hasPermission('view_estoque_movimentacoes_create') || $user->hasPermission('view_estoque_almoxarifes'));
        if (!$can) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $term = $this->service->store($request, $user);
            return response()->json([
                'message' => 'Termo de responsabilidade criado. Os itens foram dados de saída do estoque.',
                'data' => $this->termToArray($term->load(['items.stockProduct', 'stockLocation']), true),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Devolver itens do termo (entrada no estoque).
     * Permite: view_estoque_movimentacoes, view_estoque_movimentacoes_create ou view_estoque_almoxarifes.
     */
    public function devolver(int $id): JsonResponse
    {
        $user = auth()->user();
        $can = $user && ($user->hasPermission('view_estoque_movimentacoes') || $user->hasPermission('view_estoque_movimentacoes_create') || $user->hasPermission('view_estoque_almoxarifes'));
        if (!$can) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $term = $this->service->devolver($id, $user);
            return response()->json([
                'message' => 'Termo devolvido. Os itens retornaram ao estoque.',
                'data' => $this->termToArray($term, true),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Gerar PDF do termo.
     * Permite: view_estoque_movimentacoes ou view_estoque_almoxarifes.
     */
    public function pdf(int $id): Response|StreamedResponse|JsonResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->hasPermission('view_estoque_movimentacoes') && !$user->hasPermission('view_estoque_almoxarifes'))) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $term = $this->service->find($id, $user);
            $location = $term->stockLocation;
            $items = $term->items->map(function ($item) {
                $product = $item->stockProduct;
                return [
                    'description' => $product ? ($product->description ?? $product->code) : '-',
                    'quantity' => $item->quantity,
                    'unit' => $product->unit ?? 'UN',
                ];
            })->toArray();

            $data = [
                'termo' => $term,
                'location' => $location,
                'items' => $items,
                'data_emissao' => $term->created_at ? Carbon::parse($term->created_at)->format('d/m/Y H:i') : '',
            ];

            $pdf = Pdf::loadView('termo-responsabilidade-ferramentas', $data);
            $pdf->setPaper('A4', 'portrait');
            $fileName = 'termo-responsabilidade-' . preg_replace('/[^a-zA-Z0-9]/', '-', $term->numero) . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function termToArray(ResponsibilityTerm $term, bool $withItems = false): array
    {
        $arr = [
            'id' => $term->id,
            'numero' => $term->numero,
            'responsible_name' => $term->responsible_name,
            'cpf' => $term->cpf,
            'project' => $term->project,
            'stock_location_id' => $term->stock_location_id,
            'stock_location' => $term->relationLoaded('stockLocation') ? [
                'id' => $term->stockLocation->id,
                'code' => $term->stockLocation->code,
                'name' => $term->stockLocation->name,
            ] : null,
            'status' => $term->status,
            'returned_at' => $term->returned_at?->format('Y-m-d H:i:s'),
            'created_at' => $term->created_at?->format('Y-m-d H:i:s'),
        ];

        if ($withItems && $term->relationLoaded('items')) {
            $arr['items'] = $term->items->map(fn ($i) => [
                'id' => $i->id,
                'stock_product_id' => $i->stock_product_id,
                'quantity' => (float) $i->quantity,
                'product' => $i->relationLoaded('stockProduct') ? [
                    'id' => $i->stockProduct->id,
                    'code' => $i->stockProduct->code,
                    'description' => $i->stockProduct->description,
                    'unit' => $i->stockProduct->unit,
                ] : null,
            ])->toArray();
        }

        return $arr;
    }
}
