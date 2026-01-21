<?php

namespace App\Http\Controllers;

use App\Models\CustomLog;
use App\Http\Resources\StockProductResource;
use App\Services\StockProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->custom_log->create([
            'user_id' => $user->id,
            'content' => 'O usuário: ' . $user->nome_completo . ' acessou a tela de Produtos de Estoque',
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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $product = $this->service->find($id);
        // Carregar estoques com relacionamento de local
        $product->load(['stocks.location']);
        return new StockProductResource($product);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $companyId = $request->header('company-id');
            $product = $this->service->create($request->all(), $companyId);
            
            DB::commit();
            
            $this->custom_log->create([
                'user_id' => $user->id,
                'content' => 'O usuário: ' . $user->nome_completo . ' criou o produto de estoque: ' . $product->id,
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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos_edit')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $product = $this->service->find($id);
            $product = $this->service->update($product, $request->all());
            
            DB::commit();
            
            $this->custom_log->create([
                'user_id' => $user->id,
                'content' => 'O usuário: ' . $user->nome_completo . ' atualizou o produto de estoque: ' . $id,
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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos_delete')) {
            return response()->json([
                'message' => 'Você não tem permissão para alterar o status de produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

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
                'min_stock' => 'nullable|integer|min:0',
                'max_stock' => 'nullable|integer|min:0',
            ]);
            
            // Remover código se estiver vazio para garantir geração automática
            if (empty($validated['code']) || trim($validated['code']) === '') {
                unset($validated['code']);
            }

            $product = $this->service->createWithProtheus($validated, (int) $companyId);
            
            DB::commit();
            
            $this->custom_log->create([
                'user_id' => $user->id,
                'content' => 'O usuário: ' . $user->nome_completo . ' criou o produto de estoque ' . $product->code,
                'operation' => 'create'
            ]);

            return response()->json([
                'message' => 'Produto cadastrado com sucesso no sistema.',
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

    /**
     * Upload de imagem do produto
     */
    public function uploadImage(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos_edit')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png|max:5120', // Máximo 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Arquivo inválido',
                'error' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $product = $this->service->find($id);

            // Deletar imagem antiga se existir
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }

            $imagePath = $this->uploadImageFile($request->file('image'), $product->code ?? 'product_' . $product->id);

            // Usar o service para atualizar, garantindo compatibilidade com SQL Server
            $product = $this->service->update($product, ['image_path' => $imagePath]);

            return response()->json([
                'message' => 'Imagem enviada com sucesso',
                'data' => [
                    'image_path' => $imagePath,
                    'image_url' => $request->getSchemeAndHttpHost() . '/storage/' . $imagePath
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao fazer upload da imagem',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remover imagem do produto
     */
    public function removeImage(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_produtos_edit')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar produtos de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $product = $this->service->find($id);

            // Deletar imagem se existir
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }

            // Usar o service para atualizar, garantindo compatibilidade com SQL Server
            $this->service->update($product, ['image_path' => null]);

            return response()->json([
                'message' => 'Imagem removida com sucesso'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao remover imagem',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Método privado para fazer upload do arquivo de imagem
     */
    private function uploadImageFile($file, $productCode)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = 'product_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $productCode) . '_' . time() . '.' . $extension;
        $path = 'stock-products/' . $filename;
        
        // Criar diretório se não existir
        Storage::disk('public')->makeDirectory('stock-products');
        
        // Salvar arquivo
        $file->storeAs('stock-products', $filename, 'public');
        
        return $path;
    }
}

