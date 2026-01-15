<?php

namespace App\Services\PurchaseQuote;

use App\Models\Company;
use App\Models\PurchaseQuote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseQuoteProductDefaultsService
{
    /**
     * Helper para salvar modelo com timestamps como strings (compatível com SQL Server)
     */
    private function saveModelWithStringTimestamps($model)
    {
        // Se não há mudanças, não precisa salvar
        if (!$model->isDirty()) {
            return $model;
        }
        
        // Pegar todos os atributos modificados
        $dirty = $model->getDirty();
        
        // Adicionar updated_at
        $dirty['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Se for novo modelo, adicionar created_at também
        if (!$model->exists) {
            $dirty['created_at'] = now()->format('Y-m-d H:i:s');
        }
        
        // Usar DB::statement() para garantir que updated_at seja string
        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();
        
        $columns = array_keys($dirty);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            // Campos de data precisam de CAST
            if ($column === 'updated_at' || $column === 'created_at' || $column === 'protheus_exported_at' || $column === 'approved_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $dirty[$column];
        }
        
        $values[] = $id; // Para o WHERE
        
        $sql = "UPDATE [{$table}] SET " . implode(', ', $placeholders) . " WHERE [{$idColumn}] = ?";
        
        DB::statement($sql, $values);
        
        // Recarregar o modelo para ter os valores atualizados
        $model->refresh();
        
        return $model;
    }
    public function apply(PurchaseQuote $quote): void
    {
        $quote->loadMissing('items');

        if (!$quote->company_id || $quote->items->isEmpty()) {
            return;
        }

        $company = Company::find($quote->company_id);

        if (!$company) {
            return;
        }

        $productAssociation = $company->getProtheusAssociationByDescricao('Produto');

        if (!$productAssociation || empty($productAssociation->tabela_protheus)) {
            return;
        }

        $tableName = $this->sanitizeIdentifier($productAssociation->tabela_protheus);

        if (empty($tableName)) {
            return;
        }

        try {
            $products = $this->fetchProducts($tableName, $quote->items);
        } catch (\Throwable $exception) {
            Log::warning('Falha ao sincronizar integrações Protheus a partir do produto', [
                'quote_id' => $quote->id,
                'company_id' => $quote->company_id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($products->isEmpty()) {
            return;
        }

        $tesCodes = $products
            ->flatMap(function ($product) {
                return [
                    $product['tes_code'] ?? null,
                    $product['nature_code'] ?? null,
                ];
            })
            ->filter()
            ->unique()
            ->values();

        $tesData = $tesCodes->isNotEmpty()
            ? $this->fetchTesData($tesCodes->all())
            : collect();

        $quoteDirty = false;

        foreach ($quote->items as $item) {
            $productCode = strtoupper((string) ($item->product_code ?? ''));

            if (!$productCode) {
                continue;
            }

            $product = $products->get($productCode);

            if (!$product) {
                continue;
            }

            $tesCode = $product['tes_code'] ?? null;
            $productCfop = $product['cfop_code'] ?? null;
            $natureCode = $product['nature_code'] ?? null;

            if (!$item->tes_code && $tesCode) {
                $item->tes_code = $tesCode;
            }

            if (!$item->tes_description && $item->tes_code) {
                $item->tes_description = $tesData->get($item->tes_code)['description'] ?? null;
            }

            if (!$item->cfop_code) {
                if ($productCfop) {
                    $item->cfop_code = $productCfop;
                } elseif ($item->tes_code) {
                    $item->cfop_code = $tesData->get($item->tes_code)['cfop'] ?? null;
                }
            }

            if ($item->isDirty(['tes_code', 'tes_description', 'cfop_code'])) {
                $this->saveModelWithStringTimestamps($item);
            }

            if (!$quote->nature_operation_code && $natureCode) {
                $quote->nature_operation_code = $natureCode;
                $quote->nature_operation_description = $tesData->get($natureCode)['description'] ?? null;
                $quote->nature_operation_cfop = $tesData->get($natureCode)['cfop'] ?? $productCfop;
                $quoteDirty = true;
            }
        }

        if ($quoteDirty) {
            $this->saveModelWithStringTimestamps($quote);
        }
    }

    protected function fetchProducts(string $table, Collection $items): Collection
    {
        $connection = DB::connection('protheus');
        $database = $connection->getDatabaseName();

        $codes = $items
            ->pluck('product_code')
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        $query = $connection
            ->table(DB::raw("[$database].[dbo].[$table]"))
            ->select([
                'B1_COD',
                'B1_TES',
                'B1_CF',
                'B1_NATOP',
            ])
            ->whereIn('B1_COD', $codes->all())
            ->where('D_E_L_E_T_', '<>', '*');

        $records = $query->get();

        return $records->mapWithKeys(function ($record) {
            $code = strtoupper((string) ($record->B1_COD ?? ''));

            if (!$code) {
                return [];
            }

            return [
                $code => [
                    'tes_code' => $this->normalizeString($record->B1_TES ?? null),
                    'cfop_code' => $this->normalizeString($record->B1_CF ?? null),
                    'nature_code' => $this->normalizeString($record->B1_NATOP ?? null),
                ],
            ];
        });
    }

    protected function fetchTesData(array $codes): Collection
    {
        $connection = DB::connection('protheus');
        $database = $connection->getDatabaseName();
        $normalizedCodes = collect($codes)
            ->map(fn ($code) => $this->normalizeString($code))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedCodes->isEmpty()) {
            return collect();
        }

        $tables = ['SF4010', 'SF4'];

        foreach ($tables as $table) {
            $identifier = $this->sanitizeIdentifier($table);

            if (!$identifier) {
                continue;
            }

            try {
                $rows = $connection
                    ->table(DB::raw("[$database].[dbo].[$identifier]"))
                    ->select([
                        'F4_CODIGO',
                        'F4_TEXTO',
                        'F4_CFOP',
                    ])
                    ->whereIn('F4_CODIGO', $normalizedCodes->all())
                    ->where('D_E_L_E_T_', '<>', '*')
                    ->get();

                if ($rows->isEmpty()) {
                    continue;
                }

                return $rows->mapWithKeys(function ($row) {
                    $code = $this->normalizeString($row->F4_CODIGO ?? null);

                    if (!$code) {
                        return [];
                    }

                    return [
                        $code => [
                            'description' => $this->normalizeString($row->F4_TEXTO ?? null),
                            'cfop' => $this->normalizeString($row->F4_CFOP ?? null),
                        ],
                    ];
                });
            } catch (\Throwable $exception) {
                continue;
            }
        }

        return collect();
    }

    protected function sanitizeIdentifier(?string $identifier): string
    {
        if (empty($identifier)) {
            return '';
        }

        $identifier = strtoupper(trim($identifier));
        $identifier = preg_replace('/[^A-Z0-9_]/', '', $identifier ?? '');

        return $identifier ?? '';
    }

    protected function normalizeString(?string $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}

