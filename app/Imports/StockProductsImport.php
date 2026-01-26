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
use Carbon\Carbon;

class StockProductsImport implements ToCollection, WithHeadingRow
{
    protected $companyId;
    protected $userId;
    protected $errors = [];
    protected $successCount = 0;
    protected $skipCount = 0;

    public function __construct(int $companyId, int $userId)
    {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    public function collection(Collection $rows)
    {
        $productService = new StockProductService();

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque linha 1 é cabeçalho e index começa em 0

                try {
                    // Verificar se a linha está vazia (ignorar linhas completamente vazias)
                    // IMPORTANTE: Verificar ANTES de validar para evitar erros em linhas vazias
                    if ($this->isRowEmpty($row)) {
                        $this->skipCount++;
                        continue; // Pular linha vazia sem gerar erro
                    }

                    // Validar linha (validação manual com normalização de colunas)
                    // A validação é feita manualmente para garantir que encontra as colunas
                    // corretamente mesmo com variações de nomes do Maatwebsite/Excel
                    $this->validateRow($row, $rowNumber);

                    // Buscar ou criar produto
                    $product = $this->findOrCreateProduct($row, $productService);
                    
                    if (!$product || !$product->id) {
                        throw new \Exception("Erro ao criar/buscar produto na linha {$rowNumber}");
                    }

                    // Buscar local de estoque
                    $location = $this->findLocation($row);
                    
                    if (!$location || !$location->id) {
                        throw new \Exception("Erro ao buscar local de estoque na linha {$rowNumber}");
                    }

                    // Criar ou atualizar estoque
                    $stock = $this->findOrCreateStock($product, $location);
                    
                    if (!$stock || !$stock->id) {
                        throw new \Exception("Erro ao criar/buscar estoque na linha {$rowNumber}");
                    }

                    // Adicionar quantidade ao estoque
                    $quantidadeValue = $this->normalizeColumnName($row, ['quantidade', 'Quantidade']);
                    $quantity = $quantidadeValue !== null ? (float) $quantidadeValue : 0;
                    
                    $custoValue = $this->normalizeColumnName($row, ['custo_unitario', 'custo unitário', 'Custo Unitário', 'custo_unitário']);
                    $cost = ($custoValue !== null && $custoValue !== '') ? (float) $custoValue : null;

                    if ($quantity > 0) {
                        $this->addStockQuantity($stock, $product, $location, $quantity, $cost, $row);
                        $this->successCount++;
                    } else {
                        $this->errors[] = "Linha {$rowNumber}: Quantidade deve ser maior que zero";
                        $this->skipCount++;
                    }

                } catch (\Exception $e) {
                    $this->errors[] = "Linha {$rowNumber}: " . $e->getMessage();
                    $this->skipCount++;
                    // Continuar processando as próximas linhas mesmo se esta falhar
                    continue;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
        
        $description = $this->normalizeColumnName($row, ['descricao', 'descrição', 'Descrição']);
        $description = $description !== null ? trim((string) $description) : '';
        
        $reference = $this->normalizeColumnName($row, ['referencia', 'referência', 'Referência']);
        $reference = ($reference !== null && !empty(trim((string) $reference))) ? trim((string) $reference) : null;
        
        $unit = $this->normalizeColumnName($row, ['unidade', 'Unidade']);
        $unit = $unit !== null ? trim((string) $unit) : '';

        // Buscar produto existente por descrição e unidade (não por código)
        // Isso permite atualizar produtos existentes se necessário
        $product = StockProduct::where('description', $description)
            ->where('unit', $unit)
            ->where('company_id', $this->companyId)
            ->first();

        if ($product) {
            // Atualizar referência se necessário
            if ($reference && $product->reference !== $reference) {
                $product->reference = $reference;
                $product->save();
            }
            return $product;
        }

        // Criar novo produto (código será gerado automaticamente)
        $productData = [
            'description' => $description,
            'unit' => $unit,
            'active' => true,
        ];

        if ($reference) {
            $productData['reference'] = $reference;
        }

        return $productService->create($productData, $this->companyId);
    }

    protected function findLocation($row)
    {
        // Tentar todas as variações possíveis do nome "Local de Estoque"
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

        if (empty($locationIdentifier)) {
            throw new \Exception('O campo "Local de Estoque" é obrigatório');
        }

        // Tentar buscar por código
        $location = StockLocation::where('code', $locationIdentifier)
            ->where('company_id', $this->companyId)
            ->where('active', true)
            ->first();

        // Se não encontrou por código, tentar por nome
        if (!$location) {
            $location = StockLocation::where('name', $locationIdentifier)
                ->where('company_id', $this->companyId)
                ->where('active', true)
                ->first();
        }

        if (!$location) {
            throw new \Exception("Local de estoque '{$locationIdentifier}' não encontrado ou inativo. Verifique se o local existe e está ativo no cadastro de locais de estoque.");
        }

        return $location;
    }

    protected function findOrCreateStock($product, $location)
    {
        $stock = Stock::where('stock_product_id', $product->id)
            ->where('stock_location_id', $location->id)
            ->where('company_id', $this->companyId)
            ->first();

        if (!$stock) {
            $now = now()->format('Y-m-d H:i:s');
            
            // Usar OUTPUT INSERTED.id para garantir que funciona corretamente no SQL Server
            $result = DB::select(
                "INSERT INTO [stocks] ([stock_product_id], [stock_location_id], [company_id], [quantity_available], [quantity_reserved], [quantity_total], [created_at], [updated_at]) 
                 OUTPUT INSERTED.[id]
                 VALUES (?, ?, ?, 0, 0, 0, CAST(? AS DATETIME2), CAST(? AS DATETIME2))",
                [$product->id, $location->id, $this->companyId, $now, $now]
            );

            $stockId = $result[0]->id;
            $stock = Stock::find($stockId);
        }

        return $stock;
    }

    protected function addStockQuantity($stock, $product, $location, $quantity, $cost, $row)
    {
        // Buscar valores atuais diretamente do banco com lock para evitar problemas de concorrência
        // Usar WITH (UPDLOCK, ROWLOCK) para garantir que lemos os valores mais recentes e bloqueamos a linha
        $currentStock = DB::selectOne(
            "SELECT [quantity_available], [quantity_total] FROM [stocks] WITH (UPDLOCK, ROWLOCK) WHERE [id] = ?",
            [$stock->id]
        );
        
        if (!$currentStock) {
            throw new \Exception("Estoque não encontrado no banco de dados");
        }
        
        $quantityBefore = (float) $currentStock->quantity_available;
        $quantityAfter = $quantityBefore + $quantity;
        $quantityTotalAfter = (float) $currentStock->quantity_total + $quantity;

        // Atualizar estoque usando valores do banco
        $now = now()->format('Y-m-d H:i:s');
        DB::statement(
            "UPDATE [stocks] SET [quantity_available] = ?, [quantity_total] = ?, [last_movement_at] = CAST(? AS DATETIME2), [updated_at] = CAST(? AS DATETIME2) WHERE [id] = ?",
            [$quantityAfter, $quantityTotalAfter, $now, $now, $stock->id]
        );

        // Criar movimentação usando os IDs do produto e local passados como parâmetros
        $observacaoValue = $this->normalizeColumnName($row, ['observacao', 'observação', 'Observação']);
        $observation = ($observacaoValue !== null && !empty(trim((string) $observacaoValue))) 
            ? trim((string) $observacaoValue) 
            : 'Importação em massa via Excel';
        
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

