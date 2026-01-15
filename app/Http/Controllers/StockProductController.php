<?php

namespace App\Http\Controllers;

use App\Models\CustomLog;
use App\Http\Resources\StockProductResource;
use App\Services\StockProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StockProductController extends Controller
{
    protected $custom_log;
    protected $service;

    public function __construct(CustomLog $custom_log, StockProductService $service)
    {
        $this->custom_log = $custom_log;
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: ' . auth()->user()->nome_completo . ' acessou a tela de Produtos de Estoque',
            'operation' => 'index'
        ]);

        $products = $this->service->list($request);
        return StockProductResource::collection($products);
    }

    public function buscar(Request $request)
    {
        $products = $this->service->buscar($request);
        
        // Formatar resposta com locations
        $formatted = $products->getCollection()->map(function($product) {
            return [
                'id' => $product->id,
                'code' => $product->code,
                'reference' => $product->reference,
                'description' => $product->description,
                'unit' => $product->unit,
                'locations' => $product->locations ?? [],
            ];
        });

        return response()->json([
            'data' => $formatted,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    /**
     * Buscar produtos combinados: Protheus + Sistema Interno
     */
    public function buscarCombinado(Request $request)
    {
        $result = $this->service->buscarProdutosCombinado($request);
        
        return response()->json([
            'data' => $result['items'],
            'pagination' => $result['pagination']
        ]);
    }

    public function show(Request $request, $id)
    {
        $product = $this->service->find($id);
        return new StockProductResource($product);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $companyId = $request->header('company-id');
            $product = $this->service->create($request->all(), $companyId);
            
            DB::commit();
            
            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: ' . auth()->user()->nome_completo . ' criou o produto de estoque: ' . $product->id,
                'operation' => 'create'
            ]);

            return new StockProductResource($product);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao criar produto.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $product = $this->service->find($id);
            $product = $this->service->update($product, $request->all());
            
            DB::commit();
            
            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: ' . auth()->user()->nome_completo . ' atualizou o produto de estoque: ' . $id,
                'operation' => 'update'
            ]);

            return new StockProductResource($product);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao atualizar produto.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function toggleActive(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $product = $this->service->find($id);
            $product = $this->service->toggleActive($product);
            
            DB::commit();
            
            return new StockProductResource($product);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao alterar status do produto.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function cadastrarComProtheus(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $companyId = $request->header('company-id');

            if (!$companyId) {
                return response()->json([
                    'message' => 'Company ID é obrigatório.',
                    'error' => 'Header company-id é obrigatório'
                ], Response::HTTP_BAD_REQUEST);
            }

            $validated = $request->validate([
                'code' => 'nullable|string|max:100', // Código é opcional, será gerado automaticamente
                'description' => 'required|string|max:255',
                'reference' => 'nullable|string|max:100',
                'unit' => 'required|string|max:20',
            ]);
            
            // Remover código se estiver vazio para garantir geração automática
            if (empty($validated['code']) || trim($validated['code']) === '') {
                unset($validated['code']);
            }

            $product = $this->service->createWithProtheus($validated, (int) $companyId);
            
            DB::commit();
            
            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: ' . auth()->user()->nome_completo . ' criou o produto de estoque ' . $product->code . ' e cadastrou no Protheus',
                'operation' => 'create'
            ]);

            return response()->json([
                'message' => 'Produto cadastrado com sucesso no sistema e no Protheus.',
                'data' => new StockProductResource($product)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao cadastrar produto.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}

