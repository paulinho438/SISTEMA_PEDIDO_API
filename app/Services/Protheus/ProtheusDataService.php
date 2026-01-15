<?php

namespace App\Services\Protheus;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProtheusDataService
{
    /**
     * Paginate data from a Protheus table using the configured connection.
     *
     * @param Request $request
     * @param string $tableCode
     * @param array $columns
     * @param array $filters
     * @param string|null $orderBy
     * @return LengthAwarePaginator
     */
    public function paginate(Request $request, string $tableCode, array $columns, array $filters = [], ?string $orderBy = 'R_E_C_N_O_'): LengthAwarePaginator
    {
        $connection = DB::connection('protheus');
        $databaseName = $connection->getDatabaseName();

        if (empty($databaseName)) {
            throw ValidationException::withMessages([
                'database' => 'Defina PROTHEUS_DB_DATABASE no arquivo .env',
            ]);
        }

        $tableIdentifier = $this->sanitizeIdentifier($tableCode);
        if (empty($tableIdentifier)) {
            throw ValidationException::withMessages([
                'table' => 'Tabela Protheus inválida. Verifique as associações da empresa.',
            ]);
        }

        $query = $connection
            ->query()
            ->from(DB::raw("[$databaseName].[dbo].[$tableIdentifier]"));

        $selectColumns = $this->sanitizeColumnList($columns);
        if (empty($selectColumns)) {
            $selectColumns = ['*'];
        }

        $query->select($selectColumns);

        // Filtro padrão do Protheus para registros não deletados
        $query->where('D_E_L_E_T_', '<>', '*');

        foreach ($filters as $filter) {
            if (!is_array($filter) || count($filter) < 2) {
                continue;
            }

            $column = $this->sanitizeIdentifier($filter[0]);
            $value = $filter[1];
            $operator = $filter[2] ?? '=';
            $caseInsensitive = ($filter['case_insensitive'] ?? false) === true;

            if (empty($column)) {
                continue;
            }

            if (isset($filter['alternate']) && is_array($filter['alternate']) && count($filter['alternate'])) {
                $query->where(function ($subQuery) use ($column, $operator, $value, $caseInsensitive, $filter) {
                    $this->applyCondition($subQuery, $column, $operator, $value, $caseInsensitive);

                    foreach ($filter['alternate'] as $alternate) {
                        $this->applyAlternateCondition($subQuery, $alternate, $operator, $value, $caseInsensitive);
                    }
                });
            } else {
                $this->applyCondition($query, $column, $operator, $value, $caseInsensitive);
            }
        }

        $orderColumn = $this->sanitizeIdentifier($orderBy ?? 'R_E_C_N_O_');
        if (!empty($orderColumn)) {
            $query->orderBy($orderColumn, 'asc');
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;
        $page = max((int) $request->query('page', 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Sanitize a list of columns to avoid SQL injection via identifiers.
     *
     * @param array $columns
     * @return array
     */
    protected function sanitizeColumnList(array $columns): array
    {
        return collect($columns)
            ->map(function ($column) {
                return $this->sanitizeIdentifier($column);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Allow only alphanumeric characters and underscore for identifiers.
     *
     * @param string|null $identifier
     * @return string
     */
    protected function sanitizeIdentifier(?string $identifier): string
    {
        if (empty($identifier)) {
            return '';
        }

        $identifier = strtoupper(trim($identifier));
        $identifier = preg_replace('/[^A-Z0-9_]/', '', $identifier ?? '');

        return $identifier ?? '';
    }

    protected function applyCondition($query, string $column, string $operator, mixed $value, bool $caseInsensitive = false, bool $isOr = false): void
    {
        if ($caseInsensitive && is_string($value)) {
            $value = mb_strtoupper($value, 'UTF-8');
            $columnExpression = DB::raw('UPPER([' . $column . '])');
        } else {
            $columnExpression = $column;
        }

        if ($isOr) {
            $query->orWhere($columnExpression, $operator, $value);
        } else {
            $query->where($columnExpression, $operator, $value);
        }
    }

    protected function applyAlternateCondition($query, array $alternate, string $defaultOperator, mixed $defaultValue, bool $baseCaseInsensitive): void
    {
        $alternateColumn = $this->sanitizeIdentifier($alternate['column'] ?? $alternate[0] ?? '');

        if (empty($alternateColumn)) {
            return;
        }

        $alternateOperator = $alternate['operator'] ?? ($alternate[2] ?? $defaultOperator);
        $alternateValue = $alternate['value'] ?? ($alternate[1] ?? $defaultValue);

        if ($alternateValue === null || $alternateValue === '') {
            return;
        }

        $alternateCaseInsensitive = ($alternate['case_insensitive'] ?? false) === true;

        // inherit case-insensitive flag when alternate doesn't specify
        if (!$alternateCaseInsensitive) {
            $alternateCaseInsensitive = $baseCaseInsensitive;
        }

        $this->applyCondition($query, $alternateColumn, $alternateOperator, $alternateValue, $alternateCaseInsensitive, true);
    }
}

