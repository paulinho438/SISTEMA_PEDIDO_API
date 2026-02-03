<?php

namespace App\Services;

use App\Models\StockProduct;
use App\Models\Stock;
use App\Models\Company;
use App\Services\Protheus\ProtheusDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StockProductService
{
    /**
     * Gera código sequencial para produto
     */
    public function generateNextCode(int $companyId): string
    {
        $prefix = 'PROD-';
        
        // Buscar todos os produtos da empresa com o prefixo
        $products = StockProduct::where('company_id', $companyId)
            ->where('code', 'like', $prefix . '%')
            ->get();

        $maxNumber = 0;
        
        foreach ($products as $product) {
            // Extrair o número do código (remover o prefixo)
            $numberStr = str_replace($prefix, '', $product->code);
            $number = (int) $numberStr;
            
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }

        $newNumber = $maxNumber + 1;

        return $prefix . str_pad((string) $newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Listar produtos com paginação
     */
    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 15;
        $page = (int) $request->get('page', 1);
        
        $companyId = $request->header('company-id');
        $query = StockProduct::where('company_id', $companyId);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return $query->orderBy('description')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Buscar produtos com estoque disponível (para modal de busca)
     */
    public function buscar(Request $request)
    {
        \Log::info('StockProductService::buscar INÍCIO', ['location_id' => $request->get('location_id'), 'page' => $request->get('page')]);
        $perPage = (int) $request->get('per_page', 15);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 15;

        // Buscar produtos com estoque no local informado, sem filtrar por company
        $productIdsQuery = DB::table('stocks')
            ->join('stock_products', 'stocks.stock_product_id', '=', 'stock_products.id')
            ->where('stock_products.active', true)
            ->where('stocks.quantity_available', '>', 0)
            ->select('stock_products.id')
            ->distinct();
            
        if ($request->filled('search')) {
            $search = $request->get('search');
            $productIdsQuery->where(function($q) use ($search) {
                $q->where('stock_products.code', 'like', "%{$search}%")
                  ->orWhere('stock_products.reference', 'like', "%{$search}%")
                  ->orWhere('stock_products.description', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('location_id')) {
            $productIdsQuery->where('stocks.stock_location_id', $request->get('location_id'));
        }
        
        $productIds = $productIdsQuery->pluck('id')->toArray();
        \Log::info('StockProductService::buscar productIds count', ['count' => count($productIds)]);

        if (empty($productIds)) {
            // Retornar paginação vazia se não houver produtos
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        
        // Buscar produtos usando whereIn
        $query = StockProduct::whereIn('id', $productIds);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('description')->paginate($perPage);
        \Log::info('StockProductService::buscar paginate OK', ['total' => $products->total()]);

        // Carregar estoques por local para cada produto
        foreach ($products->items() as $product) {
            $stocks = Stock::where('stock_product_id', $product->id)
                ->where('quantity_available', '>', 0)
                ->with('location')
                ->get();

            $product->locations = $stocks->map(function($stock) {
                return [
                    'stock_id' => $stock->id,
                    'location_id' => $stock->stock_location_id,
                    'location_code' => $stock->location->code ?? null,
                    'location_name' => $stock->location->name ?? null,
                    'quantity_available' => (float) $stock->quantity_available,
                    'quantity_reserved' => (float) $stock->quantity_reserved,
                    'quantity_total' => (float) $stock->quantity_total,
                ];
            });
        }

        \Log::info('StockProductService::buscar FIM');
        return $products;
    }

    /**
     * Obter produto por ID
     */
    public function find($id)
    {
        return StockProduct::findOrFail($id);
    }

    /**
     * Criar produto
     */
    public function create(array $data, int $companyId): StockProduct
    {
        // Se o código não foi fornecido ou está vazio, gerar automaticamente
        if (!isset($data['code']) || empty($data['code']) || trim($data['code']) === '') {
            $data['code'] = $this->generateNextCode($companyId);
        }

        $validator = Validator::make($data, [
            'code' => 'required|string|max:100|unique:stock_products,code,NULL,id,company_id,' . $companyId,
            'reference' => 'nullable|string|max:100',
            'description' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $data['company_id'] = $companyId;
        $data['active'] = $data['active'] ?? true;
        
        // Usar DB::statement() com CAST para garantir compatibilidade com SQL Server
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        $values = array_values($data);
        
        // Adicionar campos de data com CAST
        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [stock_products] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Buscar o ID do último registro inserido
        $productId = DB::getPdo()->lastInsertId();
        
        // Carregar e retornar o modelo usando o ID retornado
        return StockProduct::findOrFail($productId);
    }

    /**
     * Atualizar produto
     */
    public function update(StockProduct $product, array $data): StockProduct
    {
        $validator = Validator::make($data, [
            'code' => 'sometimes|required|string|max:100',
            'reference' => 'nullable|string|max:100',
            'description' => 'sometimes|required|string|max:255',
            'unit' => 'sometimes|required|string|max:20',
            'min_stock' => 'nullable|integer',
            'max_stock' => 'nullable|integer',
            'image_path' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        // Usar método que trata timestamps como string para SQL Server
        $this->updateModelWithStringTimestamps($product, $data);
        
        return $product->fresh();
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
            if ($column === 'updated_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }
        
        $values[] = $id; // Para o WHERE
        
        $sql = "UPDATE [{$table}] SET " . implode(', ', $placeholders) . " WHERE [{$idColumn}] = ?";
        
        DB::statement($sql, $values);
        
        // Recarregar o modelo
        $model->refresh();
        
        return $model;
    }

    /**
     * Toggle active status
     */
    public function toggleActive(StockProduct $product): StockProduct
    {
        $this->updateModelWithStringTimestamps($product, ['active' => !$product->active]);
        
        return $product->fresh();
    }

    /**
     * Criar produto no sistema (código gerado automaticamente)
     * NÃO insere no Protheus - apenas cria no sistema interno
     */
    public function createWithProtheus(array $data, int $companyId): StockProduct
    {
        // Criar produto apenas no sistema interno
        // Não insere no Protheus conforme solicitado
        return $this->create($data, $companyId);
    }

    /**
     * Inserir produto na tabela do Protheus
     */
    protected function insertProductInProtheus(StockProduct $product, string $tableName): void
    {
        try {
            $connection = DB::connection('protheus');
            $databaseName = $connection->getDatabaseName();

            if (empty($databaseName)) {
                throw new \Exception('Defina PROTHEUS_DB_DATABASE no arquivo .env');
            }

            // Sanitizar nome da tabela
            $tableIdentifier = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            if (empty($tableIdentifier)) {
                throw new \Exception('Tabela Protheus inválida');
            }

            // Verificar se o produto já existe no Protheus
            $exists = $connection->table(DB::raw("[$databaseName].[dbo].[$tableIdentifier]"))
                ->where('B1_COD', $product->code)
                ->where('D_E_L_E_T_', '<>', '*')
                ->exists();

            if ($exists) {
                Log::info('Produto já existe no Protheus', [
                    'product_code' => $product->code,
                    'table' => $tableIdentifier,
                ]);
                return;
            }

            // Buscar próximo R_E_C_N_O_ (número do registro)
            $lastRecord = $connection->table(DB::raw("[$databaseName].[dbo].[$tableIdentifier]"))
                ->where('D_E_L_E_T_', '<>', '*')
                ->orderBy('R_E_C_N_O_', 'desc')
                ->first();

            $nextRecno = ($lastRecord?->R_E_C_N_O_ ?? 0) + 1;

            // Inserir produto no Protheus
            // Campos principais da tabela SB1 (Produtos) do Protheus
            $protheusData = [
                'R_E_C_N_O_' => $nextRecno,
                'B1_FILIAL' => '01', // Filial padrão
                'B1_COD' => str_pad(substr($product->code, 0, 15), 15, ' ', STR_PAD_RIGHT), // Código do produto (15 chars)
                'B1_DESC' => str_pad(substr($product->description, 0, 40), 40, ' ', STR_PAD_RIGHT), // Descrição (40 chars)
                'B1_UM' => str_pad(substr($product->unit ?? 'UN', 0, 2), 2, ' ', STR_PAD_RIGHT), // Unidade de medida (2 chars)
                'B1_TIPO' => 'PA', // Tipo: PA = Produto Acabado
                'B1_LOCPAD' => '01', // Local padrão
                'B1_MSBLQL' => '2', // Não bloqueado (2 = não bloqueado, 1 = bloqueado)
                'D_E_L_E_T_' => ' ', // Não deletado
                'R_E_C_D_E_L_' => 0,
                'B1_USER' => 'PORTAL',
                'B1_USERLGA' => now()->format('Ymd'),
                'B1_DATE' => now()->format('Ymd'),
                'B1_HORA' => now()->format('His'),
            ];

            // Se tiver referência, adicionar
            if (!empty($product->reference)) {
                $protheusData['B1_DESC'] = str_pad(substr($product->reference . ' - ' . $product->description, 0, 40), 40, ' ', STR_PAD_RIGHT);
            }

            $connection->table(DB::raw("[$databaseName].[dbo].[$tableIdentifier]"))
                ->insert($protheusData);

            Log::info('Produto inserido no Protheus com sucesso', [
                'product_id' => $product->id,
                'product_code' => $product->code,
                'table' => $tableIdentifier,
                'recno' => $nextRecno,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao inserir produto no Protheus', [
                'product_id' => $product->id,
                'product_code' => $product->code,
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Erro ao cadastrar produto no Protheus: ' . $e->getMessage());
        }
    }

    /**
     * Buscar produtos combinados: Protheus + Sistema Interno
     */
    public function buscarProdutosCombinado(Request $request): array
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10;
        $page = max((int) $request->get('page', 1), 1);
        
        $companyId = $request->header('company-id');
        $search = $request->get('busca', '');
        
        $allProducts = collect([]);
        
        // 1. Buscar produtos do sistema interno
        $internalProductsQuery = StockProduct::where('company_id', $companyId)
            ->where('active', true);
            
        if (!empty($search)) {
            $internalProductsQuery->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $internalProducts = $internalProductsQuery->get()->map(function($product) {
            return [
                'B1_COD' => $product->code,
                'B1_DESC' => $product->description,
                'B1_UM' => $product->unit,
                'B1_REF' => $product->reference ?? null, // Adicionar referência
                'source' => 'internal', // Identificar como produto interno
                'internal_id' => $product->id,
            ];
        });
        
        $allProducts = $allProducts->merge($internalProducts);
        
        // 2. Buscar produtos do Protheus (se houver associação) - buscar diretamente da conexão
        try {
            $company = Company::find($companyId);
            if (!$company) {
                Log::warning('Empresa não encontrada ao buscar produtos do Protheus', [
                    'company_id' => $companyId,
                ]);
            } else {
                $association = $company->getProtheusAssociationByDescricao('Produto');
                
                if (!$association) {
                    Log::info('Associação Protheus não encontrada para produtos', [
                        'company_id' => $companyId,
                        'descricao_buscada' => 'Produto',
                    ]);
                } elseif (empty($association->tabela_protheus)) {
                    Log::warning('Associação Protheus encontrada mas sem tabela_protheus', [
                        'company_id' => $companyId,
                        'association_id' => $association->id,
                        'descricao' => $association->descricao,
                    ]);
                } else {
                    $connection = DB::connection('protheus');
                    $databaseName = $connection->getDatabaseName();
                    
                    if (empty($databaseName)) {
                        Log::warning('Database Protheus não configurado', [
                            'company_id' => $companyId,
                        ]);
                    } else {
                        $tableIdentifier = preg_replace('/[^a-zA-Z0-9_]/', '', $association->tabela_protheus);
                        
                        if (empty($tableIdentifier)) {
                            Log::warning('Tabela Protheus inválida após sanitização', [
                                'company_id' => $companyId,
                                'tabela_original' => $association->tabela_protheus,
                            ]);
                        } else {
                            Log::info('Buscando produtos do Protheus', [
                                'company_id' => $companyId,
                                'database' => $databaseName,
                                'tabela' => $tableIdentifier,
                                'search' => $search,
                            ]);
                            
                            $query = $connection->table(DB::raw("[$databaseName].[dbo].[$tableIdentifier]"))
                                ->where('D_E_L_E_T_', '<>', '*')
                                ->select('B1_COD', 'B1_DESC', 'B1_UM');
                            
                            if (!empty($search)) {
                                $searchUpper = mb_strtoupper(trim($search));
                                $query->where(function($q) use ($searchUpper) {
                                    $q->where('B1_COD', 'LIKE', "%{$searchUpper}%")
                                      ->orWhere('B1_DESC', 'LIKE', "%{$searchUpper}%");
                                });
                            }
                            
                            $protheusProducts = $query->orderBy('B1_DESC')
                                ->limit(100) // Limite para não sobrecarregar
                                ->get()
                                ->map(function($item) {
                                    return [
                                        'B1_COD' => trim($item->B1_COD ?? ''),
                                        'B1_DESC' => trim($item->B1_DESC ?? ''),
                                        'B1_UM' => trim($item->B1_UM ?? 'UN'),
                                        'B1_REF' => null, // Campo não existe na tabela SB1010
                                        'source' => 'protheus', // Identificar como produto do Protheus
                                        'internal_id' => null,
                                    ];
                                })
                                ->filter(function($item) {
                                    // Filtrar apenas produtos válidos
                                    return !empty($item['B1_COD']) && !empty($item['B1_DESC']);
                                });
                            
                            Log::info('Produtos encontrados no Protheus', [
                                'company_id' => $companyId,
                                'total' => $protheusProducts->count(),
                            ]);
                            
                            // Mesclar produtos, removendo duplicados por código
                            $existingCodes = $allProducts->pluck('B1_COD')->map(function($code) {
                                return trim($code ?? '');
                            })->filter()->toArray();
                            
                            $protheusProductsUnique = $protheusProducts->filter(function($item) use ($existingCodes) {
                                $code = trim($item['B1_COD'] ?? '');
                                return !empty($code) && !in_array($code, $existingCodes);
                            });
                            
                            Log::info('Produtos únicos do Protheus (após remover duplicados)', [
                                'company_id' => $companyId,
                                'total' => $protheusProductsUnique->count(),
                            ]);
                            
                            $allProducts = $allProducts->merge($protheusProductsUnique);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar produtos do Protheus', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Continua com apenas produtos internos em caso de erro
        }
        
        // Ordenar por descrição
        $allProducts = $allProducts->sortBy('B1_DESC')->values();
        
        // Aplicar paginação manual
        $total = $allProducts->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $allProducts->slice($offset, $perPage)->values();
        
        return [
            'items' => $paginated->toArray(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ];
    }
}

