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
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class StockProductsImport implements ToCollection, WithHeadingRow, WithValidation
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
                    if ($this->isRowEmpty($row)) {
                        continue; // Pular linha vazia sem gerar erro
                    }

                    // Validar linha
                    $this->validateRow($row, $rowNumber);

                    // Buscar ou criar produto
                    $product = $this->findOrCreateProduct($row, $productService);

                    // Buscar local de estoque
                    $location = $this->findLocation($row);

                    // Criar ou atualizar estoque
                    $stock = $this->findOrCreateStock($product, $location);

                    // Adicionar quantidade ao estoque
                    $quantidadeValue = $this->normalizeColumnName($row, ['quantidade', 'Quantidade']);
                    $quantity = $quantidadeValue !== null ? (float) $quantidadeValue : 0;
                    
                    $custoValue = $this->normalizeColumnName($row, ['custo_unitario', 'custo unitário', 'Custo Unitário', 'custo_unitário']);
                    $cost = ($custoValue !== null && $custoValue !== '') ? (float) $custoValue : null;

                    if ($quantity > 0) {
                        $this->addStockQuantity($stock, $quantity, $cost, $row);
                        $this->successCount++;
                    } else {
                        $this->errors[] = "Linha {$rowNumber}: Quantidade deve ser maior que zero";
                        $this->skipCount++;
                    }

                } catch (\Exception $e) {
                    $this->errors[] = "Linha {$rowNumber}: " . $e->getMessage();
                    $this->skipCount++;
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
     */
    protected function isRowEmpty($row): bool
    {
        // Verificar se pelo menos um campo obrigatório tem valor
        $descricao = $this->normalizeColumnName($row, ['descricao', 'descrição', 'Descrição']);
        $unidade = $this->normalizeColumnName($row, ['unidade', 'Unidade']);
        $localEstoque = $this->normalizeColumnName($row, ['local_estoque', 'local de estoque', 'local_de_estoque', 'Local de Estoque']);
        $quantidade = $this->normalizeColumnName($row, ['quantidade', 'Quantidade']);
        
        // Se pelo menos um campo obrigatório tiver valor, a linha não está vazia
        if (($descricao !== null && trim((string) $descricao) !== '') ||
            ($unidade !== null && trim((string) $unidade) !== '') ||
            ($localEstoque !== null && trim((string) $localEstoque) !== '') ||
            ($quantidade !== null && trim((string) $quantidade) !== '')) {
            return false;
        }
        
        return true; // Linha está vazia
    }

    /**
     * Normaliza o nome da coluna do Excel (pode vir com espaços, underscores, etc)
     */
    protected function normalizeColumnName($row, $possibleNames)
    {
        foreach ($possibleNames as $name) {
            // Tentar diferentes variações do nome
            $variations = [
                $name,
                strtolower($name),
                str_replace(' ', '_', strtolower($name)),
                str_replace('_', ' ', strtolower($name)),
                str_replace(' ', '', strtolower($name)),
            ];
            
            foreach ($variations as $variation) {
                if (isset($row[$variation])) {
                    return $row[$variation];
                }
            }
        }
        
        return null;
    }

    protected function validateRow($row, $rowNumber)
    {
        // Normalizar valores (remover espaços e converter para string)
        // Tentar diferentes variações dos nomes das colunas
        $descricao = $this->normalizeColumnName($row, ['descricao', 'descrição', 'Descrição']);
        $descricao = $descricao !== null ? trim((string) $descricao) : null;
        
        $unidade = $this->normalizeColumnName($row, ['unidade', 'Unidade']);
        $unidade = $unidade !== null ? trim((string) $unidade) : null;
        
        $localEstoque = $this->normalizeColumnName($row, ['local_estoque', 'local de estoque', 'local_de_estoque', 'Local de Estoque']);
        $localEstoque = $localEstoque !== null ? trim((string) $localEstoque) : null;
        
        $quantidade = $this->normalizeColumnName($row, ['quantidade', 'Quantidade']);
        $quantidade = $quantidade !== null ? trim((string) $quantidade) : null;

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
        $locationIdentifier = $this->normalizeColumnName($row, ['local_estoque', 'local de estoque', 'local_de_estoque', 'Local de Estoque']);
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
            
            DB::statement(
                "INSERT INTO [stocks] ([stock_product_id], [stock_location_id], [company_id], [quantity_available], [quantity_reserved], [quantity_total], [created_at], [updated_at]) 
                 VALUES (?, ?, ?, 0, 0, 0, CAST(? AS DATETIME2), CAST(? AS DATETIME2))",
                [$product->id, $location->id, $this->companyId, $now, $now]
            );

            $stockId = DB::getPdo()->lastInsertId();
            $stock = Stock::find($stockId);
        }

        return $stock;
    }

    protected function addStockQuantity($stock, $quantity, $cost, $row)
    {
        $quantityBefore = $stock->quantity_available;
        $quantityAfter = $quantityBefore + $quantity;

        // Atualizar estoque
        $now = now()->format('Y-m-d H:i:s');
        DB::statement(
            "UPDATE [stocks] SET [quantity_available] = ?, [quantity_total] = ?, [last_movement_at] = CAST(? AS DATETIME2), [updated_at] = CAST(? AS DATETIME2) WHERE [id] = ?",
            [$quantityAfter, $stock->quantity_total + $quantity, $now, $now, $stock->id]
        );

        // Criar movimentação
        $observacaoValue = $this->normalizeColumnName($row, ['observacao', 'observação', 'Observação']);
        $observation = ($observacaoValue !== null && !empty(trim((string) $observacaoValue))) 
            ? trim((string) $observacaoValue) 
            : 'Importação em massa via Excel';
        
        DB::statement(
            "INSERT INTO [stock_movements] ([stock_id], [stock_product_id], [stock_location_id], [movement_type], [quantity], [quantity_before], [quantity_after], [reference_type], [cost], [total_cost], [observation], [user_id], [company_id], [movement_date], [created_at], [updated_at]) 
             VALUES (?, ?, ?, 'entrada', ?, ?, ?, 'outro', ?, ?, ?, ?, ?, CAST(? AS DATE), CAST(? AS DATETIME2), CAST(? AS DATETIME2))",
            [
                $stock->id,
                $stock->stock_product_id,
                $stock->stock_location_id,
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

    public function rules(): array
    {
        return [
            'descricao' => 'required',
            'unidade' => 'required',
            'local_estoque' => 'required',
            'quantidade' => 'required|numeric|min:0.0001',
        ];
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

