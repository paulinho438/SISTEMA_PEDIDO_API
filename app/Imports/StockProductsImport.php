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
                    // Validar linha
                    $this->validateRow($row, $rowNumber);

                    // Buscar ou criar produto
                    $product = $this->findOrCreateProduct($row, $productService);

                    // Buscar local de estoque
                    $location = $this->findLocation($row);

                    // Criar ou atualizar estoque
                    $stock = $this->findOrCreateStock($product, $location);

                    // Adicionar quantidade ao estoque
                    $quantity = (float) ($row['quantidade'] ?? 0);
                    $cost = isset($row['custo_unitario']) && $row['custo_unitario'] !== null 
                        ? (float) $row['custo_unitario'] 
                        : null;

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

    protected function validateRow($row, $rowNumber)
    {
        $validator = Validator::make([
            'descricao' => $row['descricao'] ?? null,
            'unidade' => $row['unidade'] ?? null,
            'local_estoque' => $row['local_estoque'] ?? null,
            'quantidade' => $row['quantidade'] ?? null,
        ], [
            'descricao' => 'required|string|max:255',
            'unidade' => 'required|string|max:20',
            'local_estoque' => 'required|string',
            'quantidade' => 'required|numeric|min:0.0001',
        ], [
            'descricao.required' => 'Descrição é obrigatória',
            'unidade.required' => 'Unidade é obrigatória',
            'local_estoque.required' => 'Local de Estoque é obrigatório',
            'quantidade.required' => 'Quantidade é obrigatória',
            'quantidade.numeric' => 'Quantidade deve ser um número',
            'quantidade.min' => 'Quantidade deve ser maior que zero',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }
    }

    protected function findOrCreateProduct($row, StockProductService $productService)
    {
        $code = isset($row['codigo']) && !empty(trim($row['codigo'] ?? '')) 
            ? trim($row['codigo']) 
            : null;

        $description = trim($row['descricao']);
        $reference = isset($row['referencia']) && !empty(trim($row['referencia'] ?? '')) 
            ? trim($row['referencia']) 
            : null;
        $unit = trim($row['unidade']);

        // Se código foi fornecido, buscar produto existente
        if ($code) {
            $product = StockProduct::where('code', $code)
                ->where('company_id', $this->companyId)
                ->first();

            if ($product) {
                // Atualizar descrição e unidade se necessário
                if ($product->description !== $description || $product->unit !== $unit) {
                    $product->description = $description;
                    $product->unit = $unit;
                    if ($reference) {
                        $product->reference = $reference;
                    }
                    $product->save();
                }
                return $product;
            }
        }

        // Criar novo produto
        $productData = [
            'description' => $description,
            'unit' => $unit,
            'active' => true,
        ];

        if ($code) {
            $productData['code'] = $code;
        }

        if ($reference) {
            $productData['reference'] = $reference;
        }

        return $productService->create($productData, $this->companyId);
    }

    protected function findLocation($row)
    {
        $locationIdentifier = trim($row['local_estoque']);

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
            throw new \Exception("Local de estoque '{$locationIdentifier}' não encontrado ou inativo");
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
        $observation = isset($row['observacao']) && !empty(trim($row['observacao'] ?? '')) 
            ? trim($row['observacao']) 
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

