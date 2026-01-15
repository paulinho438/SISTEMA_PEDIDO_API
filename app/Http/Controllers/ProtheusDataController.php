<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Protheus\ProtheusDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProtheusDataController extends Controller
{
    public function __construct(
        private readonly ProtheusDataService $protheusDataService
    ) {
    }

    public function fornecedores(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'fornecedores');
    }

    public function produtos(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'produtos');
    }

    public function centrosDeCusto(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'centros_custo');
    }

    public function condicoesPagamento(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'condicoes_pagamento');
    }

    public function pedidosCompra(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'pedidos_compra');
    }

    public function itensPedidoCompra(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'itens_pedido_compra');
    }

    public function compradores(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'compradores');
    }

    public function naturezasOperacao(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'naturezas_operacao');
    }

    public function tes(Request $request): JsonResponse
    {
        return $this->listEntity($request, 'tes');
    }
    protected function listEntity(Request $request, string $entity): JsonResponse
    {
        $config = $this->entities()[$entity] ?? null;

        if (!$config) {
            return response()->json([
                'message' => 'Entidade Protheus não suportada',
                'entity' => $entity,
            ], Response::HTTP_NOT_FOUND);
        }

        $companyId = $request->header('company-id');

        if (!$companyId) {
            return response()->json([
                'message' => 'ID da empresa não informado',
                'error' => 'Header company-id é obrigatório',
            ], Response::HTTP_BAD_REQUEST);
        }

        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'message' => 'Empresa não encontrada',
                'error' => 'Empresa não encontrada',
            ], Response::HTTP_NOT_FOUND);
        }

        $association = $company->getProtheusAssociationByDescricao($config['descricao']);

        if (!$association) {
            return response()->json([
                'message' => 'Empresa não possui associação configurada para ' . strtolower($config['descricao']),
                'data' => [],
                'tabela_protheus' => null,
            ], Response::HTTP_OK);
        }

        if (!config('database.connections.protheus')) {
            return response()->json([
                'message' => 'Conexão com o banco Protheus não configurada',
                'error' => 'Adicione a conexão "protheus" em config/database.php e configure as variáveis PROTHEUS_* no .env',
                'steps' => [
                    'Adicionar no arquivo config/database.php em connections o array "protheus" (ex: driver sqlsrv ou mysql)',
                    'No arquivo .env declarar PROTHEUS_DB_* (host, database, username, password, port)',
                    'Executar php artisan config:clear para aplicar as alterações',
                ],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $mandatoryInputs = $config['mandatory_filters'] ?? [];
        $missingInputs = collect($mandatoryInputs)
            ->filter(fn ($input) => !$request->filled($input))
            ->values()
            ->all();

        if (!empty($missingInputs)) {
            return response()->json([
                'message' => 'Parâmetros obrigatórios ausentes para consultar o Protheus',
                'missing' => $missingInputs,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filters = $this->buildFilters($request, $config['filters'] ?? []);

        try {
            $paginator = $this->protheusDataService->paginate(
                $request,
                (string) $association->tabela_protheus,
                $config['columns'],
                $filters,
                $config['order_by'] ?? null
            );
        } catch (ValidationException $validationException) {
            return response()->json([
                'message' => 'Requisição inválida para o Protheus',
                'errors' => $validationException->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $connectionException) {
            Log::error(sprintf(
                'Falha ao consultar entidade %s no Protheus: %s',
                $entity,
                $connectionException->getMessage()
            ));

            return response()->json([
                'message' => 'Não foi possível consultar dados no Protheus',
                'error' => $connectionException->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $messageLabel = $config['label'] ?? $config['descricao'];

        return response()->json([
            'message' => sprintf('%s do Protheus', $messageLabel),
            'data' => [
                'items' => $paginator->items(),
                'tabela_protheus' => $association->tabela_protheus,
                'descricao' => $association->descricao,
                'company_id' => $companyId,
                'company_name' => $company->company,
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    protected function buildFilters(Request $request, array $configFilters): array
    {
        $filters = [];

        foreach ($configFilters as $inputName => $filterConfig) {
            if (!$request->filled($inputName)) {
                continue;
            }

            $value = $request->query($inputName);
            $column = $filterConfig['column'] ?? null;

            if (isset($filterConfig['resolver'])) {
                $resolved = $this->resolveFilter($filterConfig['resolver'], $value, $filterConfig);

                if ($resolved) {
                    $filters[] = $resolved;
                }

                continue;
            }

            if (!$column) {
                continue;
            }

            $operator = $filterConfig['operator'] ?? '=';

            if (is_string($value)) {
                if (($filterConfig['trim'] ?? true) === true) {
                    $value = trim($value);
                }

                if (($filterConfig['transform'] ?? null) === 'uppercase') {
                    $value = mb_strtoupper($value, 'UTF-8');
                }
            }

            if (($filterConfig['wildcard'] ?? false) === true && is_string($value)) {
                $value = '%' . $value . '%';
            } else {
                if (($filterConfig['prefix_wildcard'] ?? false) === true && is_string($value)) {
                    $value = '%' . $value;
                }
                if (($filterConfig['suffix_wildcard'] ?? false) === true && is_string($value)) {
                    $value .= '%';
                }
            }

            $filterDefinition = [
                $column,
                $value,
                $operator,
            ];

            if (!empty($filterConfig['alternate'])) {
                $alternateFilters = $this->prepareAlternateFilters($filterConfig['alternate'], $value, $operator);

                if (!empty($alternateFilters)) {
                    $filterDefinition['alternate'] = $alternateFilters;
                }
            }

            if (!empty($filterConfig['case_insensitive'])) {
                $filterDefinition['case_insensitive'] = true;
            }

            $filters[] = $filterDefinition;
        }

        return $filters;
    }

    protected function prepareAlternateFilters(array $alternateConfigs, mixed $defaultValue, string $defaultOperator): array
    {
        $prepared = [];

        foreach ($alternateConfigs as $alternate) {
            if (!is_array($alternate) || empty($alternate['column'])) {
                continue;
            }

            $value = $alternate['value'] ?? $defaultValue;
            $operator = $alternate['operator'] ?? $defaultOperator;

            if (is_string($value)) {
                if (($alternate['trim'] ?? false) === true) {
                    $value = trim($value);
                }

                if (($alternate['transform'] ?? null) === 'uppercase') {
                    $value = mb_strtoupper($value, 'UTF-8');
                }
            }

            if (($alternate['wildcard'] ?? false) === true && is_string($value)) {
                $value = '%' . $value . '%';
            } else {
                if (($alternate['prefix_wildcard'] ?? false) === true && is_string($value)) {
                    $value = '%' . $value;
                }
                if (($alternate['suffix_wildcard'] ?? false) === true && is_string($value)) {
                    $value .= '%';
                }
            }

            if ($value === null || $value === '') {
                continue;
            }

            $prepared[] = [
                'column' => $alternate['column'],
                'value' => $value,
                'operator' => $operator,
                'case_insensitive' => $alternate['case_insensitive'] ?? false,
            ];
        }

        return $prepared;
    }

    protected function resolveFilter(string $resolver, mixed $value, array $config): ?array
    {
        return match ($resolver) {
            'fornecedor_busca' => $this->resolveFornecedorBuscaFilter($value),
            'produto_busca' => $this->resolveProdutoBuscaFilter($value),
            'centro_custo_busca' => $this->resolveCentroCustoBuscaFilter($value),
            default => null,
        };
    }

    protected function resolveFornecedorBuscaFilter(mixed $rawValue): ?array
    {
        if (!is_string($rawValue)) {
            $rawValue = (string) $rawValue;
        }

        $value = trim($rawValue);

        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        $hasLetters = preg_match('/[A-Za-z]/u', $value) === 1;
        $onlyDigits = $digits !== '' && !$hasLetters && strlen($digits) === strlen($value);

        if ($onlyDigits) {
            return [
                'A2_COD',
                $digits . '%',
                'LIKE',
                'alternate' => [
                    [
                        'column' => 'A2_CGC',
                        'operator' => 'LIKE',
                        'value' => $digits . '%',
                    ],
                ],
            ];
        }

        $uppercase = mb_strtoupper($value, 'UTF-8');

        $filter = [
            'A2_NOME',
            '%' . $uppercase . '%',
            'LIKE',
            'case_insensitive' => true,
        ];

        $alternates = [];

        if ($digits !== '') {
            $alternates[] = [
                'column' => 'A2_COD',
                'operator' => 'LIKE',
                'value' => $digits . '%',
            ];

            $alternates[] = [
                'column' => 'A2_CGC',
                'operator' => 'LIKE',
                'value' => $digits . '%',
            ];
        }

        if (!empty($alternates)) {
            $filter['alternate'] = $alternates;
        }

        return $filter;
    }

    protected function resolveProdutoBuscaFilter(mixed $rawValue): ?array
    {
        if (!is_string($rawValue)) {
            $rawValue = (string) $rawValue;
        }

        $value = trim($rawValue);

        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        $hasLetters = preg_match('/[A-Za-z]/u', $value) === 1;
        $onlyDigits = $digits !== '' && !$hasLetters && strlen($digits) === strlen($value);

        if ($onlyDigits) {
            return [
                'B1_COD',
                '%' . $digits . '%',
                'LIKE',
                'alternate' => [
                    [
                        'column' => 'B1_DESC',
                        'operator' => 'LIKE',
                        'value' => '%' . mb_strtoupper($value, 'UTF-8') . '%',
                        'case_insensitive' => true,
                    ],
                ],
            ];
        }

        $uppercase = mb_strtoupper($value, 'UTF-8');

        $filter = [
            'B1_DESC',
            '%' . $uppercase . '%',
            'LIKE',
            'case_insensitive' => true,
        ];

        if ($digits !== '') {
            $filter['alternate'] = [
                [
                    'column' => 'B1_COD',
                    'operator' => 'LIKE',
                    'value' => '%' . $digits . '%',
                ],
            ];
        }

        return $filter;
    }

    protected function resolveCentroCustoBuscaFilter(mixed $rawValue): ?array
    {
        if (!is_string($rawValue)) {
            $rawValue = (string) $rawValue;
        }

        $value = trim($rawValue);

        if ($value === '') {
            return null;
        }

        $uppercase = mb_strtoupper($value, 'UTF-8');
        $digits = preg_replace('/\D+/', '', $value);

        $filter = [
            'CTT_DESC01',
            '%' . $uppercase . '%',
            'LIKE',
            'case_insensitive' => true,
        ];

        if ($digits !== '') {
            $filter['alternate'] = [
                [
                    'column' => 'CTT_CUSTO',
                    'operator' => 'LIKE',
                    'value' => '%' . $value . '%',
                ],
            ];
        }

        return $filter;
    }

    protected function entities(): array
    {
        return [
            'fornecedores' => [
                'descricao' => 'Fornecedor',
                'label' => 'Fornecedores',
                'columns' => ['A2_COD', 'A2_NOME', 'A2_CGC', 'A2_END', 'A2_MUN', 'A2_CEP'],
                'order_by' => 'A2_NOME',
                'filters' => [
                    'codigo' => ['column' => 'A2_COD', 'operator' => '='],
                    'nome' => [
                        'column' => 'A2_NOME',
                        'operator' => 'LIKE',
                        'wildcard' => true,
                        'case_insensitive' => true,
                        'transform' => 'uppercase',
                    ],
                    'cnpj' => ['column' => 'A2_CGC', 'operator' => '='],
                    'busca' => [
                        'resolver' => 'fornecedor_busca',
                    ],
                ],
            ],
            'produtos' => [
                'descricao' => 'Produto',
                'label' => 'Produtos',
                'columns' => ['B1_COD', 'B1_DESC', 'B1_TIPO', 'B1_UM', 'B1_GRUPO', 'B1_SEGUM'],
                'order_by' => 'B1_DESC',
                'filters' => [
                    'codigo' => ['column' => 'B1_COD', 'operator' => '='],
                    'descricao' => [
                        'column' => 'B1_DESC',
                        'operator' => 'LIKE',
                        'wildcard' => true,
                        'case_insensitive' => true,
                        'transform' => 'uppercase',
                    ],
                    'grupo' => ['column' => 'B1_GRUPO', 'operator' => '='],
                    'busca' => [
                        'resolver' => 'produto_busca',
                    ],
                ],
            ],
            'centros_custo' => [
                'descricao' => 'Centro de Custo',
                'label' => 'Centros de Custo',
                'columns' => ['CTT_CUSTO', 'CTT_DESC01', 'CTT_CLASSE'],
                'order_by' => 'CTT_DESC01',
                'filters' => [
                    'codigo' => ['column' => 'CTT_CUSTO', 'operator' => '='],
                    'descricao' => [
                        'column' => 'CTT_DESC01',
                        'operator' => 'LIKE',
                        'wildcard' => true,
                        'case_insensitive' => true,
                        'transform' => 'uppercase',
                    ],
                    'busca' => [
                        'resolver' => 'centro_custo_busca',
                    ],
                ],
            ],
            'condicoes_pagamento' => [
                'descricao' => 'Condição de Pagamento',
                'label' => 'Condições de Pagamento',
                'columns' => ['E4_CODIGO', 'E4_COND', 'E4_DESCRI'],
                'order_by' => 'E4_CODIGO',
                'filters' => [
                    'codigo' => ['column' => 'E4_CODIGO', 'operator' => '='],
                    'descricao' => ['column' => 'E4_DESCRI', 'operator' => 'LIKE', 'wildcard' => true]
                ],
            ],
            'pedidos_compra' => [
                'descricao' => 'Pedido de Compra',
                'label' => 'Pedidos de Compra',
                'columns' => ['C5_NUM', 'C5_FILIAL', 'C5_EMISSAO', 'C5_FORNECE', 'C5_LOJA', 'C5_COMPRADOR', 'C5_CONDPAG', 'C5_NATUREZ', 'C5_TOTNF', 'C5_STATUS'],
                'order_by' => 'C5_NUM',
                'filters' => [
                    'numero' => ['column' => 'C5_NUM', 'operator' => '='],
                    'fornecedor' => ['column' => 'C5_FORNECE', 'operator' => '='],
                    'comprador' => ['column' => 'C5_COMPRADOR', 'operator' => '='],
                    'condicao_pagamento' => ['column' => 'C5_CONDPAG', 'operator' => '='],
                    'status' => ['column' => 'C5_STATUS', 'operator' => '='],
                ],
            ],
            'itens_pedido_compra' => [
                'descricao' => 'Item de Pedido de Compra',
                'label' => 'Itens de Pedido de Compra',
                'columns' => ['C6_NUM', 'C6_ITEM', 'C6_PRODUTO', 'C6_DESCRI', 'C6_UM', 'C6_QTDORI', 'C6_PRCORI', 'C6_CC', 'C6_CONTA', 'C6_NATUREZ', 'C6_CF'],
                'order_by' => 'C6_ITEM',
                'filters' => [
                    'numero' => ['column' => 'C6_NUM', 'operator' => '='],
                    'item' => ['column' => 'C6_ITEM', 'operator' => '='],
                    'produto' => ['column' => 'C6_PRODUTO', 'operator' => '='],
                ],
                'mandatory_filters' => ['numero'],
            ],
            'compradores' => [
                'descricao' => 'Comprador',
                'label' => 'Compradores',
                'columns' => ['AE8_COD', 'AE8_NOME', 'AE8_EMAIL', 'AE8_USER', 'AE8_FILIAL'],
                'order_by' => 'AE8_NOME',
                'filters' => [
                    'codigo' => ['column' => 'AE8_COD', 'operator' => '='],
                    'nome' => ['column' => 'AE8_NOME', 'operator' => 'LIKE', 'wildcard' => true],
                    'usuario' => ['column' => 'AE8_USER', 'operator' => '='],
                ],
            ],
            'naturezas_operacao' => [
                'descricao' => 'Natureza de Operação',
                'label' => 'Naturezas de Operação',
                'columns' => ['F4_CODIGO', 'F4_TEXTO', 'F4_TIPO', 'F4_EST', 'F4_CFOP'],
                'order_by' => 'F4_CODIGO',
                'filters' => [
                    'codigo' => ['column' => 'F4_CODIGO', 'operator' => '='],
                    'descricao' => ['column' => 'F4_TEXTO', 'operator' => 'LIKE', 'wildcard' => true],
                    'cfop' => ['column' => 'F4_CFOP', 'operator' => '='],
                ],
            ],
            'tes' => [
                'descricao' => 'TES',
                'label' => 'TES',
                'columns' => ['F4_CODIGO', 'F4_TEXTO', 'F4_CFOP', 'F4_TIPO'],
                'order_by' => 'F4_CODIGO',
                'filters' => [
                    'codigo' => ['column' => 'F4_CODIGO', 'operator' => '='],
                    'descricao' => ['column' => 'F4_TEXTO', 'operator' => 'LIKE', 'wildcard' => true],
                    'cfop' => ['column' => 'F4_CFOP', 'operator' => '='],
                    'tipo' => ['column' => 'F4_TIPO', 'operator' => '='],
                ],
            ],
        ];
    }
}

