<?php

namespace App\Services;

use App\Models\Company;
use App\Services\Protheus\ProtheusDataService;
use App\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CostcenterService
{
    public function __construct(
        private readonly ProtheusDataService $protheusDataService
    ) {
    }

    /**
     * Busca todos os centros de custo do Protheus para uma empresa
     *
     * @param Request $request
     * @return Collection
     * @throws BusinessException
     */
    public function getAllFromProtheus(Request $request): Collection
    {
        $companyId = $request->header('company-id');

        if (!$companyId) {
            throw new BusinessException('ID da empresa não informado');
        }

        $company = Company::find($companyId);

        if (!$company) {
            throw new BusinessException('Empresa não encontrada');
        }

        $association = $company->getProtheusAssociationByDescricao('Centro de Custo');

        if (!$association) {
            throw new BusinessException('Empresa não possui associação configurada para Centro de Custo');
        }

        if (!config('database.connections.protheus')) {
            throw new BusinessException('Conexão com o banco Protheus não configurada');
        }

        try {
            // Criar uma nova request com per_page alto para buscar todos os registros
            $allRequest = clone $request;
            $allRequest->merge(['per_page' => 10000, 'page' => 1]);
            
            $paginator = $this->protheusDataService->paginate(
                $allRequest,
                (string) $association->tabela_protheus,
                ['CTT_CUSTO', 'CTT_DESC01', 'CTT_CLASSE'],
                [],
                'CTT_DESC01'
            );

            // Transformar dados do Protheus para o formato esperado pelo frontend
            return collect($paginator->items())->map(function ($item) {
                return [
                    'id' => $item->CTT_CUSTO ?? null,
                    'name' => $item->CTT_CUSTO ?? '',
                    'description' => $item->CTT_DESC01 ?? '',
                    'created_at' => null, // Centros de custo do Protheus não têm data de criação
                ];
            });
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $errorMessages = [];
            foreach ($errors as $field => $messages) {
                $errorMessages[] = implode(', ', $messages);
            }
            throw new BusinessException('Erro ao consultar Protheus: ' . implode('; ', $errorMessages));
        } catch (\Exception $e) {
            throw new BusinessException('Não foi possível consultar centros de custo no Protheus: ' . $e->getMessage());
        }
    }
}

