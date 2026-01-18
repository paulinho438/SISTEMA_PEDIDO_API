<?php

namespace App\Services\PurchaseQuote;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseQuoteApprovalService
{
    /**
     * Helper para atualizar modelos com timestamps como strings (compatível com SQL Server)
     */
    private function updateModelWithStringTimestamps($model, array $data)
    {
        // Adicionar updated_at como string
        $data['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Usar DB::statement() para garantir que updated_at seja string
        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            if ($column === 'updated_at' || $column === 'approved_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }
        
        $values[] = $id; // Para o WHERE
        
        $sql = "UPDATE [{$table}] SET " . implode(', ', $placeholders) . " WHERE [{$idColumn}] = ?";
        
        DB::statement($sql, $values);
        
        // Recarregar o modelo com relacionamentos para ter os valores atualizados
        // Se for PurchaseQuoteApproval, carregar o relacionamento approver
        if ($model instanceof \App\Models\PurchaseQuoteApproval) {
            $model->refresh();
            $model->load('approver');
        } else {
            $model->refresh();
        }
        
        return $model;
    }

    /**
     * Helper para inserir registros com timestamps como strings (compatível com SQL Server)
     */
    private function insertWithStringTimestamps($table, $data)
    {
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
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas (ex: order)
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [{$table}] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }
    /**
     * Ordem hierárquica padrão de aprovação
     */
    public function getApprovalOrder(): array
    {
        return [
            'COMPRADOR' => 1,
            'ENGENHEIRO' => 2, // Engenheiro vem ANTES do Gerente Local
            'GERENTE_LOCAL' => 3,
            'GERENTE_GERAL' => 4,
            'DIRETOR' => 5,
            'PRESIDENTE' => 6,
        ];
    }

    /**
     * Níveis de aprovação disponíveis
     */
    public function getAvailableLevels(): array
    {
        return array_keys($this->getApprovalOrder());
    }

    /**
     * Seleciona os níveis de aprovação requeridos para uma cotação
     */
    public function selectRequiredApprovals(PurchaseQuote $quote, array $levels): void
    {
        $order = $this->getApprovalOrder();
        $validLevels = $this->getAvailableLevels();

        // Validar níveis
        $levels = array_intersect($levels, $validLevels);

        if (empty($levels)) {
            throw new \InvalidArgumentException('Pelo menos um nível de aprovação deve ser selecionado.');
        }

        DB::beginTransaction();

        try {
            // Remover aprovações existentes (se houver)
            $quote->approvals()->delete();

            // Criar novas aprovações usando helper para timestamps como strings
            foreach ($levels as $level) {
                $this->insertWithStringTimestamps('purchase_quote_approvals', [
                    'purchase_quote_id' => $quote->id,
                    'approval_level' => $level,
                    'required' => true,
                    'approved' => false,
                    'order' => $order[$level] ?? 999,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao selecionar aprovações requeridas', [
                'quote_id' => $quote->id,
                'levels' => $levels,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verifica se um usuário pode aprovar um determinado nível
     */
    public function canApproveLevel(PurchaseQuote $quote, string $level, User $user): bool
    {
        // Verificar se o nível é requerido
        $approval = $quote->approvals()
            ->byLevel($level)
            ->required()
            ->first();

        if (!$approval || $approval->approved) {
            return false;
        }

        // Se o status é "analisada" ou "analisada_aguardando", ENGENHEIRO, GERENTE_LOCAL e GERENTE_GERAL
        // podem aprovar simultaneamente (sem necessidade de ordem)
        $simultaneousLevels = ['ENGENHEIRO', 'GERENTE_LOCAL', 'GERENTE_GERAL'];
        $currentStatus = $quote->current_status_slug ?? '';

        if (in_array($currentStatus, ['analisada', 'analisada_aguardando'], true) && 
            in_array($level, $simultaneousLevels, true)) {
            // Verificar apenas se o usuário pertence ao grupo/perfil correspondente
            return $this->userHasLevelPermission($user, $level, $quote->company_id);
        }

        // Para outros casos, verificar se todos os níveis anteriores foram aprovados
        $order = $this->getApprovalOrder();
        $currentOrder = $order[$level] ?? 999;

        $previousLevels = array_filter($order, function ($orderValue) use ($currentOrder) {
            return $orderValue < $currentOrder;
        });

        foreach ($previousLevels as $prevLevel => $prevOrder) {
            $prevApproval = $quote->approvals()
                ->byLevel($prevLevel)
                ->required()
                ->first();

            // Se o nível anterior é requerido mas não foi aprovado, não pode aprovar este
            if ($prevApproval && !$prevApproval->approved) {
                return false;
            }
        }

        // Verificar se o usuário pertence ao grupo/perfil correspondente
        return $this->userHasLevelPermission($user, $level, $quote->company_id);
    }

    /**
     * Verifica se o usuário tem permissão para o nível
     */
    protected function userHasLevelPermission(User $user, string $level, ?int $companyId): bool
    {
        if (!$companyId) {
            return false;
        }

        // Mapear nível para nome de grupo/perfil
        $levelToGroupMap = [
            'COMPRADOR' => ['Comprador', 'COMPRADOR'],
            'GERENTE_LOCAL' => ['Gerente Local', 'GERENTE LOCAL'],
            'ENGENHEIRO' => ['Engenheiro', 'ENGENHEIRO'],
            'GERENTE_GERAL' => ['Gerente Geral', 'GERENTE GERAL'],
            'DIRETOR' => ['Diretor', 'DIRETOR'],
            'PRESIDENTE' => ['Presidente', 'PRESIDENTE'],
        ];

        $groupNames = $levelToGroupMap[$level] ?? [];

        return $user->groups()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($groupNames) {
                foreach ($groupNames as $groupName) {
                    $query->orWhere('name', 'LIKE', "%{$groupName}%");
                }
            })
            ->exists();
    }

    /**
     * Processa a aprovação de um nível
     */
    public function approveLevel(PurchaseQuote $quote, string $level, User $user, ?string $notes = null): PurchaseQuoteApproval
    {
        if (!$this->canApproveLevel($quote, $level, $user)) {
            throw new \Exception("Usuário não pode aprovar o nível {$level} nesta cotação.");
        }

        $approval = $quote->approvals()
            ->byLevel($level)
            ->required()
            ->first();

        if (!$approval) {
            throw new \Exception("Nível de aprovação {$level} não encontrado ou não é requerido.");
        }

        DB::beginTransaction();

        try {
            // Usar helper para atualizar com timestamps como strings
            $this->updateModelWithStringTimestamps($approval, [
                'approved' => true,
                'approved_by' => $user->id,
                'approved_by_name' => $user->nome_completo ?? $user->name,
                'approved_at' => now()->format('Y-m-d H:i:s'),
                'notes' => $notes,
            ]);

            DB::commit();

            // Recarregar o modelo com o relacionamento approver para garantir que está disponível
            return $approval->fresh(['approver']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao aprovar nível', [
                'quote_id' => $quote->id,
                'level' => $level,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Retorna o próximo nível de aprovação pendente
     */
    public function getNextApprovalLevel(PurchaseQuote $quote): ?PurchaseQuoteApproval
    {
        return $quote->getNextApprovalLevel();
    }

    /**
     * Verifica se todas as aprovações foram concluídas
     */
    public function checkAllApproved(PurchaseQuote $quote): bool
    {
        return $quote->isAllApproved();
    }

    /**
     * Retorna os níveis selecionados para uma cotação
     */
    public function getSelectedLevels(PurchaseQuote $quote): array
    {
        return $quote->approvals()
            ->required()
            ->ordered()
            ->pluck('approval_level')
            ->toArray();
    }

    /**
     * Retorna os níveis aprovados para uma cotação
     */
    public function getApprovedLevels(PurchaseQuote $quote): array
    {
        return $quote->approvals()
            ->required()
            ->approved()
            ->ordered()
            ->pluck('approval_level')
            ->toArray();
    }

    /**
     * Retorna os níveis de aprovação que o usuário pode aprovar
     */
    public function getUserApprovalLevels(User $user, ?int $companyId = null): array
    {
        $levels = [];
        $order = $this->getApprovalOrder();

        foreach ($order as $level => $orderValue) {
            if ($this->userHasLevelPermission($user, $level, $companyId)) {
                $levels[] = $level;
            }
        }

        // Se não encontrou níveis, tentar buscar por grupos diretamente (fallback)
        if (empty($levels) && $companyId) {
            $userGroups = $user->groups()->where('company_id', $companyId)->pluck('name')->toArray();
            
            // Mapear grupos para níveis de aprovação
            $groupToLevelMap = [
                'Comprador' => 'COMPRADOR',
                'COMPRADOR' => 'COMPRADOR',
                'Gerente Local' => 'GERENTE_LOCAL',
                'GERENTE LOCAL' => 'GERENTE_LOCAL',
                'Engenheiro' => 'ENGENHEIRO',
                'ENGENHEIRO' => 'ENGENHEIRO',
                'Gerente Geral' => 'GERENTE_GERAL',
                'GERENTE GERAL' => 'GERENTE_GERAL',
                'Diretor' => 'DIRETOR',
                'DIRETOR' => 'DIRETOR',
                'Presidente' => 'PRESIDENTE',
                'PRESIDENTE' => 'PRESIDENTE',
            ];
            
            foreach ($userGroups as $groupName) {
                foreach ($groupToLevelMap as $mapGroup => $level) {
                    if (stripos($groupName, $mapGroup) !== false) {
                        if (!in_array($level, $levels)) {
                            $levels[] = $level;
                        }
                    }
                }
            }
        }

        return $levels;
    }

    /**
     * Retorna o próximo nível de aprovação pendente que o usuário pode aprovar
     */
    public function getNextPendingLevelForUser(PurchaseQuote $quote, User $user): ?string
    {
        $userLevels = $this->getUserApprovalLevels($user, $quote->company_id);

        if (empty($userLevels)) {
            return null;
        }

        $pendingApprovals = $quote->approvals()
            ->required()
            ->pending()
            ->ordered()
            ->get();

        foreach ($pendingApprovals as $approval) {
            if (in_array($approval->approval_level, $userLevels)) {
                // Verificar se pode aprovar (todos os anteriores foram aprovados)
                if ($this->canApproveLevel($quote, $approval->approval_level, $user)) {
                    return $approval->approval_level;
                }
            }
        }

        return null;
    }
}

