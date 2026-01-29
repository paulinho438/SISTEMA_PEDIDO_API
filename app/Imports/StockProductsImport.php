<?php

namespace App\Imports;

use App\Models\StockProduct;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockProductService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StockProductsImport implements ToCollection, WithHeadingRow
{
    protected $companyId;
    protected $userId;
    protected $errors = [];
    protected $successCount = 0;
    protected $skipCount = 0;

    /** @var bool Modo apenas validação: não persiste, retorna resultado por linha */
    protected $validateOnly = false;

    /** @var array Resultado da pré-validação (linha, dados, status, mensagem) */
    protected $validationResults = [];

    public function __construct(int $companyId, int $userId, bool $validateOnly = false)
    {
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->validateOnly = $validateOnly;
    }

    public function collection(Collection $rows)
    {
        if ($this->validateOnly) {
            $this->runValidationOnly($rows);
            return;
        }

        $productService = new StockProductService();

        DB::beginTransaction();

        try {
            Log::info("=== INÍCIO DA IMPORTAÇÃO ===");
            Log::info("Total de linhas a processar: " . count($rows));
            Log::info("Company ID: {$this->companyId}, User ID: {$this->userId}");
            
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque linha 1 é cabeçalho e index começa em 0
                
                Log::info("--- PROCESSANDO LINHA {$rowNumber} ---");

                try {
                    // Debug: mostrar todas as chaves disponíveis na linha
                    $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row;
                    $availableKeys = array_keys($rowArray);
                    Log::info("Linha {$rowNumber} - Chaves disponíveis: " . implode(', ', $availableKeys));
                    foreach ($rowArray as $key => $value) {
                        Log::info("Linha {$rowNumber} - '{$key}' => '{$value}'");
                    }
                    
                    // Verificar se a linha está vazia (ignorar linhas completamente vazias)
                    // IMPORTANTE: Verificar ANTES de validar para evitar erros em linhas vazias
                    if ($this->isRowEmpty($row)) {
                        Log::info("Linha {$rowNumber} - Linha vazia, pulando");
                        $this->skipCount++;
                        continue; // Pular linha vazia sem gerar erro
                    }

                    // Validar linha (validação manual com normalização de colunas)
                    // A validação é feita manualmente para garantir que encontra as colunas
                    // corretamente mesmo com variações de nomes do Maatwebsite/Excel
                    Log::info("Linha {$rowNumber} - Iniciando validação");
                    $this->validateRow($row, $rowNumber);
                    Log::info("Linha {$rowNumber} - Validação OK");

                    // Buscar ou criar produto
                    Log::info("Linha {$rowNumber} - Buscando/criando produto");
                    $product = $this->findOrCreateProduct($row, $productService);
                    
                    if (!$product || !$product->id) {
                        // Debug: listar chaves disponíveis
                        $availableKeys = array_keys($rowArray);
                        Log::error("Linha {$rowNumber} - Erro ao criar/buscar produto. Chaves disponíveis: " . implode(', ', $availableKeys));
                        throw new \Exception("Erro ao criar/buscar produto na linha {$rowNumber}. Chaves disponíveis: " . implode(', ', $availableKeys));
                    }
                    
                    Log::info("Linha {$rowNumber} - Produto encontrado/criado: ID={$product->id}, Descrição='{$product->description}', Unidade='{$product->unit}'");

                    // Buscar local de estoque
                    Log::info("Linha {$rowNumber} - Buscando local de estoque");
                    $location = $this->findLocation($row);
                    
                    if (!$location || !$location->id) {
                        Log::error("Linha {$rowNumber} - Erro ao buscar local de estoque");
                        throw new \Exception("Erro ao buscar local de estoque na linha {$rowNumber}");
                    }
                    
                    Log::info("Linha {$rowNumber} - Local encontrado: ID={$location->id}, Nome='{$location->name}'");

                    // Criar ou atualizar estoque
                    Log::info("Linha {$rowNumber} - Buscando/criando estoque (Produto ID: {$product->id}, Local ID: {$location->id})");
                    $stock = $this->findOrCreateStock($product, $location);
                    
                    if (!$stock || !$stock->id) {
                        Log::error("Linha {$rowNumber} - Erro ao criar/buscar estoque. Produto ID: {$product->id}, Local ID: {$location->id}");
                        throw new \Exception("Erro ao criar/buscar estoque na linha {$rowNumber}. Produto ID: {$product->id}, Local ID: {$location->id}");
                    }
                    
                    Log::info("Linha {$rowNumber} - Estoque encontrado/criado: ID={$stock->id}, Quantidade Disponível={$stock->quantity_available}, Quantidade Total={$stock->quantity_total}");

                    // Adicionar quantidade ao estoque
                    $quantidadeValue = $this->normalizeColumnName($row, ['quantidade', 'Quantidade']);
                    $quantity = $quantidadeValue !== null ? (float) $quantidadeValue : 0;
                    
                    $custoValue = $this->normalizeColumnName($row, ['custo_unitario', 'custo unitário', 'Custo Unitário', 'custo_unitário']);
                    $cost = ($custoValue !== null && $custoValue !== '') ? (float) $custoValue : null;
                    
                    Log::info("Linha {$rowNumber} - Valores extraídos: Quantidade={$quantity}, Custo=" . ($cost ?? 'null'));

                    if ($quantity > 0) {
                        Log::info("Linha {$rowNumber} - Adicionando quantidade ao estoque");
                        $this->addStockQuantity($stock, $product, $location, $quantity, $cost, $row);
                        Log::info("Linha {$rowNumber} - Quantidade adicionada com sucesso!");
                        $this->successCount++;
                    } else {
                        Log::warning("Linha {$rowNumber} - Quantidade deve ser maior que zero");
                        $this->errors[] = "Linha {$rowNumber}: Quantidade deve ser maior que zero";
                        $this->skipCount++;
                    }

                } catch (\Exception $e) {
                    Log::error("Linha {$rowNumber} - ERRO: " . $e->getMessage());
                    Log::error("Linha {$rowNumber} - Stack trace: " . $e->getTraceAsString());
                    $this->errors[] = "Linha {$rowNumber}: " . $e->getMessage();
                    $this->skipCount++;
                    // Continuar processando as próximas linhas mesmo se esta falhar
                    continue;
                }
            }
            
            Log::info("=== FIM DA IMPORTAÇÃO ===");
            Log::info("Sucessos: {$this->successCount}, Ignorados: {$this->skipCount}, Erros: " . count($this->errors));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Executa apenas validação por linha, sem persistir. Preenche $this->validationResults.
     */
    protected function runValidationOnly(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2: linha 1 é cabeçalho, index começa em 0
            $dados = $this->getRowDisplayData($row);

            if ($this->isRowEmpty($row)) {
                $this->validationResults[] = [
                    'linha' => $rowNumber,
                    'dados' => $dados,
                    'status' => 'vazio',
                    'mensagem' => 'Linha vazia (será ignorada)',
                ];
                continue;
            }

            try {
                $this->validateRow($row, $rowNumber);
                $this->findLocation($row);
                $this->validationResults[] = [
                    'linha' => $rowNumber,
                    'dados' => $dados,
                    'status' => 'ok',
                    'mensagem' => null,
                ];
            } catch (\Exception $e) {
                $this->validationResults[] = [
                    'linha' => $rowNumber,
                    'dados' => $dados,
                    'status' => 'erro',
                    'mensagem' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Retorna dados da linha normalizados para exibição na pré-validação.
     */
    protected function getRowDisplayData($row): array
    {
        $str = function ($v) {
            if ($v === null || $v === '') {
                return null;
            }
            return trim((string) $v);
        };
        return [
            'referencia' => $str($this->normalizeColumnName($row, ['referencia', 'referência', 'Referência'])),
            'descricao' => $str($this->normalizeColumnName($row, ['descricao', 'descrição', 'Descrição'])),
            'unidade' => $str($this->normalizeColumnName($row, ['unidade', 'Unidade'])),
            'local_estoque' => $str($this->normalizeColumnName($row, [
                'local de estoque', 'local_de_estoque', 'Local de Estoque', 'Local de estoque',
                'LOCAL DE ESTOQUE', 'local_estoque', 'localestoque',
            ])),
            'quantidade' => $str($this->normalizeColumnName($row, ['quantidade', 'Quantidade'])),
        ];
    }

    /**
     * Retorna o resultado da pré-validação (uso após runValidationOnly).
     */
    public function getValidationResults(): array
    {
        return $this->validationResults;
    }

    /**
     * Verifica se uma linha está completamente vazia
     * Uma linha é considerada vazia se TODOS os campos obrigatórios estiverem vazios
     * Verifica diretamente nas chaves do array para ser mais eficiente e confiável
     */
    protected function isRowEmpty($row): bool
    {
        // Converter row para array
        $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row;
        
        // Se o array estiver vazio, a linha está vazia
        if (empty($rowArray)) {
            return true;
        }
        
        // Verificar diretamente nas chaves do array se algum campo obrigatório tem valor
        // Isso é mais eficiente e não depende do normalizeColumnName que pode falhar
        $hasContent = false;
        foreach ($rowArray as $key => $value) {
            $keyStr = strtolower(trim((string) $key));
            $valueStr = trim((string) $value);
            
            // Se o valor não está vazio, verificar se a chave corresponde a algum campo obrigatório
            if ($valueStr !== '' && $valueStr !== null) {
                // Normalizar a chave para comparação (remover espaços, underscores, etc)
                $normalizedKey = preg_replace('/[\s_\-]/', '', $keyStr);
                
                // Campos obrigatórios: descricao, unidade, local de estoque, quantidade
                if (strpos($normalizedKey, 'descricao') !== false ||
                    strpos($normalizedKey, 'unidade') !== false ||
                    (strpos($normalizedKey, 'local') !== false && strpos($normalizedKey, 'estoque') !== false) ||
                    strpos($normalizedKey, 'quantidade') !== false) {
                    $hasContent = true;
                    break;
                }
            }
        }
        
        // Se não encontrou nenhum campo obrigatório com valor, a linha está vazia
        return !$hasContent;
    }

    /**
     * Normaliza o nome da coluna do Excel (pode vir com espaços, underscores, etc)
     * O Maatwebsite/Excel com WithHeadingRow converte cabeçalhos para chaves de array
     * Ex: "Local de Estoque" pode vir como "local_de_estoque" ou "local de estoque"
     */
    protected function normalizeColumnName($row, $possibleNames)
    {
        // Converter row para array se for Collection
        $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row;
        
        // Se o array estiver vazio, retornar null
        if (empty($rowArray)) {
            return null;
        }
        
        // Primeiro, tentar encontrar exatamente como está no array (case-sensitive)
        foreach ($possibleNames as $name) {
            if (isset($rowArray[$name])) {
                return $rowArray[$name];
            }
        }
        
        // Segundo, tentar case-insensitive direto no array
        foreach ($rowArray as $key => $value) {
            foreach ($possibleNames as $name) {
                if (strcasecmp((string) $key, (string) $name) === 0) {
                    return $value;
                }
            }
        }
        
        // O WithHeadingRow do Maatwebsite/Excel pode converter "Local de Estoque" para:
        // - "local de estoque" (lowercase com espaços)
        // - "local_de_estoque" (lowercase com underscores) - MAIS COMUM
        // - "localdeestoque" (lowercase sem espaços)
        // Vamos criar um mapa completo de todas as variações possíveis
        
        // Normalizar todas as chaves do array para comparação
        $normalizedKeys = [];
        foreach ($rowArray as $key => $value) {
            $keyStr = trim((string) $key);
            if ($keyStr === '') {
                continue;
            }
            
            // Guardar a chave original
            $normalizedKeys[$this->normalizeKey($keyStr)] = $key;
            // Também guardar a chave em lowercase com espaços preservados
            $normalizedKeys[strtolower($keyStr)] = $key;
            // Guardar com underscores substituindo espaços
            $normalizedKeys[str_replace(' ', '_', strtolower($keyStr))] = $key;
            // Guardar sem espaços
            $normalizedKeys[str_replace(' ', '', strtolower($keyStr))] = $key;
            // Guardar sem underscores
            $normalizedKeys[str_replace('_', '', strtolower($keyStr))] = $key;
        }
        
        // Tentar diferentes variações do nome
        foreach ($possibleNames as $name) {
            $nameStr = trim((string) $name);
            if ($nameStr === '') {
                continue;
            }
            
            // Tentar exatamente como está
            if (isset($rowArray[$nameStr])) {
                return $rowArray[$nameStr];
            }
            
            // Tentar lowercase
            $lowerName = strtolower($nameStr);
            if (isset($normalizedKeys[$lowerName])) {
                $originalKey = $normalizedKeys[$lowerName];
                return $rowArray[$originalKey];
            }
            
            // Tentar normalizado (sem espaços, underscores, etc)
            $normalizedName = $this->normalizeKey($nameStr);
            if (isset($normalizedKeys[$normalizedName])) {
                $originalKey = $normalizedKeys[$normalizedName];
                return $rowArray[$originalKey];
            }
            
            // Tentar com underscores substituindo espaços
            $underscoreName = str_replace(' ', '_', $lowerName);
            if (isset($normalizedKeys[$underscoreName])) {
                $originalKey = $normalizedKeys[$underscoreName];
                return $rowArray[$originalKey];
            }
            
            // Tentar sem espaços
            $noSpacesName = str_replace(' ', '', $lowerName);
            if (isset($normalizedKeys[$noSpacesName])) {
                $originalKey = $normalizedKeys[$noSpacesName];
                return $rowArray[$originalKey];
            }
            
            // Tentar sem underscores
            $noUnderscoreName = str_replace('_', '', $lowerName);
            if (isset($normalizedKeys[$noUnderscoreName])) {
                $originalKey = $normalizedKeys[$noUnderscoreName];
                return $rowArray[$originalKey];
            }
        }
        
        // Última tentativa: buscar diretamente nas chaves do array usando comparação parcial
        // Isso ajuda quando há pequenas diferenças que não foram capturadas acima
        $searchTerms = [];
        foreach ($possibleNames as $name) {
            $nameStr = trim((string) $name);
            if ($nameStr === '') {
                continue;
            }
            $normalized = $this->normalizeKey($nameStr);
            $searchTerms[] = $normalized;
            // Também adicionar variações do termo
            $searchTerms[] = str_replace(' ', '', strtolower($nameStr));
            $searchTerms[] = str_replace(' ', '_', strtolower($nameStr));
        }
        
        foreach ($rowArray as $key => $value) {
            $keyNormalized = $this->normalizeKey((string) $key);
            $keyLower = strtolower(trim((string) $key));
            
            foreach ($searchTerms as $searchTerm) {
                // Comparação exata
                if ($keyNormalized === $searchTerm || $keyLower === $searchTerm) {
                    return $value;
                }
                
                // Comparação parcial para termos maiores
                if (strlen($searchTerm) > 5 && strlen($keyNormalized) > 5) {
                    if (strpos($keyNormalized, $searchTerm) !== false ||
                        strpos($searchTerm, $keyNormalized) !== false) {
                        return $value;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Normaliza uma chave para comparação (remove espaços, underscores, converte para lowercase)
     * Remove todos os caracteres especiais para comparação flexível
     */
    protected function normalizeKey($key)
    {
        // Remover espaços, underscores, hífens e converter para lowercase
        $normalized = strtolower(trim((string) $key));
        // Remover todos os espaços, underscores, hífens e caracteres especiais
        $normalized = preg_replace('/[\s_\-]/', '', $normalized);
        return $normalized;
    }

    protected function validateRow($row, $rowNumber)
    {
        // Normalizar valores (remover espaços e converter para string)
        // Tentar diferentes variações dos nomes das colunas
        $descricao = $this->normalizeColumnName($row, ['descricao', 'descrição', 'Descrição']);
        $descricao = $descricao !== null ? trim((string) $descricao) : null;
        
        $unidade = $this->normalizeColumnName($row, ['unidade', 'Unidade']);
        $unidade = $unidade !== null ? trim((string) $unidade) : null;
        
        // Tentar todas as variações possíveis do nome "Local de Estoque"
        // O Maatwebsite/Excel pode converter de várias formas
        $localEstoque = $this->normalizeColumnName($row, [
            'local de estoque',  // lowercase com espaços (mais comum)
            'local_de_estoque',  // lowercase com underscores
            'Local de Estoque',  // original
            'Local de estoque',  // primeira maiúscula
            'LOCAL DE ESTOQUE',  // uppercase
            'local_estoque',     // sem "de"
            'localestoque',      // sem espaços
        ]);
        $localEstoque = $localEstoque !== null ? trim((string) $localEstoque) : null;
        
        $quantidade = $this->normalizeColumnName($row, ['quantidade', 'Quantidade']);
        $quantidade = $quantidade !== null ? trim((string) $quantidade) : null;

        // Se localEstoque está vazio, tentar listar as chaves disponíveis para debug
        if (empty($localEstoque)) {
            $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row;
            $availableKeys = [];
            foreach ($rowArray as $key => $value) {
                $availableKeys[] = "'{$key}' => '{$value}'";
            }
            $keysList = implode(', ', $availableKeys);
            throw new \Exception("O campo 'Local de Estoque' é obrigatório na linha {$rowNumber}. Colunas disponíveis: [{$keysList}]. Verifique se o cabeçalho 'Local de Estoque' está correto na primeira linha do Excel e se o valor está preenchido nesta linha.");
        }

        $validator = Validator::make([
            'descricao' => $descricao,
            'unidade' => $unidade,
            'local_estoque' => $localEstoque,
            'quantidade' => $quantidade,
        ], [
            'descricao' => 'required|string|max:255',
            'unidade' => 'required|string|max:20',
            'local_estoque' => 'required|string',
            'quantidade' => 'required|numeric|min:0.0001',
        ], [
            'descricao.required' => 'O campo "Descrição" é obrigatório',
            'unidade.required' => 'O campo "Unidade" é obrigatório',
            'local_estoque.required' => 'O campo "Local de Estoque" é obrigatório',
            'quantidade.required' => 'O campo "Quantidade" é obrigatório',
            'quantidade.numeric' => 'O campo "Quantidade" deve ser um número',
            'quantidade.min' => 'O campo "Quantidade" deve ser maior que zero',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }
    }

    protected function findOrCreateProduct($row, StockProductService $productService)
    {
        // Código sempre será gerado automaticamente pelo sistema
        // Ignorar se vier no Excel
        
        Log::info("findOrCreateProduct - Buscando referência");
        $reference = $this->normalizeColumnName($row, ['referencia', 'referência', 'Referência']);
        $reference = ($reference !== null && !empty(trim((string) $reference))) ? trim((string) $reference) : null;
        Log::info("findOrCreateProduct - Referência encontrada: " . ($reference ?? 'null'));
        
        if (empty($reference)) {
            // Debug: listar chaves disponíveis se referência estiver vazia
            $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row;
            $availableKeys = array_keys($rowArray);
            Log::error("findOrCreateProduct - Referência vazia! Chaves disponíveis: " . implode(', ', $availableKeys));
            throw new \Exception("Referência do produto não encontrada. Chaves disponíveis na linha: " . implode(', ', $availableKeys));
        }
        
        Log::info("findOrCreateProduct - Buscando descrição");
        $description = $this->normalizeColumnName($row, ['descricao', 'descrição', 'Descrição']);
        $description = $description !== null ? trim((string) $description) : '';
        Log::info("findOrCreateProduct - Descrição encontrada: '{$description}'");
        
        if (empty($description)) {
            // Debug: listar chaves disponíveis se descrição estiver vazia
            $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row;
            $availableKeys = array_keys($rowArray);
            Log::error("findOrCreateProduct - Descrição vazia! Chaves disponíveis: " . implode(', ', $availableKeys));
            throw new \Exception("Descrição do produto não encontrada. Chaves disponíveis na linha: " . implode(', ', $availableKeys));
        }
        
        Log::info("findOrCreateProduct - Buscando unidade");
        $unit = $this->normalizeColumnName($row, ['unidade', 'Unidade']);
        $unit = $unit !== null ? trim((string) $unit) : '';
        Log::info("findOrCreateProduct - Unidade encontrada: '{$unit}'");
        
        if (empty($unit)) {
            Log::error("findOrCreateProduct - Unidade vazia!");
            throw new \Exception("Unidade do produto não encontrada");
        }

        // Buscar produto existente APENAS por referência
        Log::info("findOrCreateProduct - Buscando produto existente por referência: reference='{$reference}', company_id={$this->companyId}");
        $product = StockProduct::where('reference', $reference)
            ->where('company_id', $this->companyId)
            ->first();

        if ($product) {
            Log::info("findOrCreateProduct - Produto existente encontrado: ID={$product->id}, Reference='{$product->reference}', Description='{$product->description}'");
            // Atualizar descrição e unidade se necessário
            $updated = false;
            if ($product->description !== $description) {
                Log::info("findOrCreateProduct - Atualizando descrição de '{$product->description}' para '{$description}'");
                $product->description = $description;
                $updated = true;
            }
            if ($product->unit !== $unit) {
                Log::info("findOrCreateProduct - Atualizando unidade de '{$product->unit}' para '{$unit}'");
                $product->unit = $unit;
                $updated = true;
            }
            if ($updated) {
                $product->save();
                Log::info("findOrCreateProduct - Produto atualizado");
            }
            return $product;
        }

        // Criar novo produto (código será gerado automaticamente)
        Log::info("findOrCreateProduct - Criando novo produto");
        $productData = [
            'reference' => $reference,
            'description' => $description,
            'unit' => $unit,
            'active' => true,
        ];
        
        Log::info("findOrCreateProduct - Dados do produto: " . json_encode($productData));

        $newProduct = $productService->create($productData, $this->companyId);
        Log::info("findOrCreateProduct - Novo produto criado: ID={$newProduct->id}, Code={$newProduct->code}, Reference='{$newProduct->reference}'");
        return $newProduct;
    }

    protected function findLocation($row)
    {
        // Tentar todas as variações possíveis do nome "Local de Estoque"
        Log::info("findLocation - Buscando local de estoque");
        $locationIdentifier = $this->normalizeColumnName($row, [
            'local de estoque',  // lowercase com espaços (mais comum)
            'local_de_estoque',  // lowercase com underscores
            'Local de Estoque',  // original
            'Local de estoque',  // primeira maiúscula
            'LOCAL DE ESTOQUE',  // uppercase
            'local_estoque',     // sem "de"
            'localestoque',      // sem espaços
        ]);
        $locationIdentifier = $locationIdentifier !== null ? trim((string) $locationIdentifier) : '';
        Log::info("findLocation - Identificador encontrado: '{$locationIdentifier}'");

        if (empty($locationIdentifier)) {
            Log::error("findLocation - Identificador vazio!");
            throw new \Exception('O campo "Local de Estoque" é obrigatório');
        }

        // Tentar buscar por código
        Log::info("findLocation - Buscando por código: '{$locationIdentifier}', company_id={$this->companyId}");
        $location = StockLocation::where('code', $locationIdentifier)
            ->where('company_id', $this->companyId)
            ->where('active', true)
            ->first();

        // Se não encontrou por código, tentar por nome
        if (!$location) {
            Log::info("findLocation - Não encontrado por código, tentando por nome: '{$locationIdentifier}'");
            $location = StockLocation::where('name', $locationIdentifier)
                ->where('company_id', $this->companyId)
                ->where('active', true)
                ->first();
        }

        if (!$location) {
            Log::error("findLocation - Local não encontrado: '{$locationIdentifier}'");
            throw new \Exception("Local de estoque '{$locationIdentifier}' não encontrado ou inativo. Verifique se o local existe e está ativo no cadastro de locais de estoque.");
        }

        Log::info("findLocation - Local encontrado: ID={$location->id}, Code='{$location->code}', Name='{$location->name}'");
        return $location;
    }

    protected function findOrCreateStock($product, $location)
    {
        Log::info("findOrCreateStock - Buscando estoque: product_id={$product->id}, location_id={$location->id}, company_id={$this->companyId}");
        $stock = Stock::where('stock_product_id', $product->id)
            ->where('stock_location_id', $location->id)
            ->where('company_id', $this->companyId)
            ->first();

        if (!$stock) {
            Log::info("findOrCreateStock - Estoque não encontrado, criando novo");
            $now = now()->format('Y-m-d H:i:s');
            
            // Usar OUTPUT INSERTED.id para garantir que funciona corretamente no SQL Server
            $result = DB::select(
                "INSERT INTO [stocks] ([stock_product_id], [stock_location_id], [company_id], [quantity_available], [quantity_reserved], [quantity_total], [created_at], [updated_at]) 
                 OUTPUT INSERTED.[id]
                 VALUES (?, ?, ?, 0, 0, 0, CAST(? AS DATETIME2), CAST(? AS DATETIME2))",
                [$product->id, $location->id, $this->companyId, $now, $now]
            );

            $stockId = $result[0]->id;
            Log::info("findOrCreateStock - Novo estoque criado: ID={$stockId}");
            $stock = Stock::find($stockId);
        } else {
            Log::info("findOrCreateStock - Estoque existente encontrado: ID={$stock->id}, quantity_available={$stock->quantity_available}, quantity_total={$stock->quantity_total}");
        }

        return $stock;
    }

    protected function addStockQuantity($stock, $product, $location, $quantity, $cost, $row)
    {
        Log::info("addStockQuantity - INÍCIO: stock_id={$stock->id}, product_id={$product->id}, location_id={$location->id}, quantity={$quantity}, cost=" . ($cost ?? 'null'));
        
        // Usar UPDATE com cálculo direto no banco para evitar problemas de concorrência
        // Isso garante que cada atualização seja feita corretamente, mesmo em transações
        $now = now()->format('Y-m-d H:i:s');
        
        // Buscar quantidade antes para a movimentação
        Log::info("addStockQuantity - Buscando valores atuais do estoque ID={$stock->id}");
        $currentStock = DB::selectOne(
            "SELECT [quantity_available], [quantity_total] FROM [stocks] WHERE [id] = ?",
            [$stock->id]
        );
        
        if (!$currentStock) {
            Log::error("addStockQuantity - Estoque não encontrado no banco! Stock ID: {$stock->id}");
            throw new \Exception("Estoque não encontrado no banco de dados. Stock ID: {$stock->id}");
        }
        
        $quantityBefore = (float) $currentStock->quantity_available;
        $quantityTotalBefore = (float) $currentStock->quantity_total;
        Log::info("addStockQuantity - Valores ANTES: quantity_available={$quantityBefore}, quantity_total={$quantityTotalBefore}");
        
        // Atualizar estoque usando cálculo direto no banco (mais seguro para concorrência)
        Log::info("addStockQuantity - Executando UPDATE no estoque ID={$stock->id}, adicionando quantity={$quantity}");
        DB::statement(
            "UPDATE [stocks] 
             SET [quantity_available] = [quantity_available] + ?, 
                 [quantity_total] = [quantity_total] + ?, 
                 [last_movement_at] = CAST(? AS DATETIME2), 
                 [updated_at] = CAST(? AS DATETIME2) 
             WHERE [id] = ?",
            [$quantity, $quantity, $now, $now, $stock->id]
        );
        Log::info("addStockQuantity - UPDATE executado com sucesso");
        
        // Buscar quantidade depois para a movimentação
        $updatedStock = DB::selectOne(
            "SELECT [quantity_available], [quantity_total] FROM [stocks] WHERE [id] = ?",
            [$stock->id]
        );
        
        if (!$updatedStock) {
            Log::error("addStockQuantity - Erro ao buscar estoque após UPDATE! Stock ID: {$stock->id}");
            $quantityAfter = $quantityBefore + $quantity;
        } else {
            $quantityAfter = (float) $updatedStock->quantity_available;
            $quantityTotalAfter = (float) $updatedStock->quantity_total;
            Log::info("addStockQuantity - Valores DEPOIS: quantity_available={$quantityAfter}, quantity_total={$quantityTotalAfter}");
        }

        // Criar movimentação usando os IDs do produto e local passados como parâmetros
        Log::info("addStockQuantity - Criando movimentação de estoque");
        $observacaoValue = $this->normalizeColumnName($row, ['observacao', 'observação', 'Observação']);
        $observation = ($observacaoValue !== null && !empty(trim((string) $observacaoValue))) 
            ? trim((string) $observacaoValue) 
            : 'Importação em massa via Excel';
        
        Log::info("addStockQuantity - Dados da movimentação: stock_id={$stock->id}, product_id={$product->id}, location_id={$location->id}, quantity={$quantity}, quantity_before={$quantityBefore}, quantity_after={$quantityAfter}, cost=" . ($cost ?? 'null'));
        
        DB::statement(
            "INSERT INTO [stock_movements] ([stock_id], [stock_product_id], [stock_location_id], [movement_type], [quantity], [quantity_before], [quantity_after], [reference_type], [cost], [total_cost], [observation], [user_id], [company_id], [movement_date], [created_at], [updated_at]) 
             VALUES (?, ?, ?, 'entrada', ?, ?, ?, 'outro', ?, ?, ?, ?, ?, CAST(? AS DATE), CAST(? AS DATETIME2), CAST(? AS DATETIME2))",
            [
                $stock->id,
                $product->id,
                $location->id,
                $quantity,
                $quantityBefore,
                $quantityAfter,
                $cost,
                $cost ? $cost * $quantity : null,
                $observation,
                $this->userId,
                $this->companyId,
                Carbon::now()->toDateString(),
                $now,
                $now
            ]
        );
        
        Log::info("addStockQuantity - Movimentação criada com sucesso!");
        Log::info("addStockQuantity - FIM: Processamento completo para stock_id={$stock->id}");
    }


    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getSkipCount(): int
    {
        return $this->skipCount;
    }
}

