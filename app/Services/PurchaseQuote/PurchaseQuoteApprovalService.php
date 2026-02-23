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
     *
     * COMPRADOR: ordem 1
     * ENGENHEIRO, GERENTE_LOCAL, GERENTE_GERAL: ordem 2 (sem ordem entre eles - simultâneos)
     * DIRETOR: ordem 3
     * PRESIDENTE: ordem 4
     */
    public function getApprovalOrder(): array
    {
        return [
            'COMPRADOR' => 1,
            'ENGENHEIRO' => 2, // Sem ordem entre os três intermediários
            'GERENTE_LOCAL' => 2, // Sem ordem entre os três intermediários
            'GERENTE_GERAL' => 2, // Sem ordem entre os três intermediários
            'DIRETOR' => 3,
            'PRESIDENTE' => 4,
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

        // Se o status é "finalizada", "analisada" ou "analisada_aguardando", ENGENHEIRO, GERENTE_LOCAL e GERENTE_GERAL
        // podem aprovar simultaneamente (sem necessidade de ordem)
        $simultaneousLevels = ['ENGENHEIRO', 'GERENTE_LOCAL', 'GERENTE_GERAL'];
        $currentStatus = $quote->current_status_slug ?? '';

        if (in_array($currentStatus, ['finalizada', 'analisada', 'analisada_aguardando'], true) &&
            in_array($level, $simultaneousLevels, true)) {
            // Para ENGENHEIRO, verificar se o usuário é o engenheiro atribuído à cotação
            if ($level === 'ENGENHEIRO') {
                // Se a cotação tem um engenheiro atribuído, apenas ele pode aprovar
                if ($quote->engineer_id) {
                    // Converter ambos para int para garantir comparação correta
                    return (int) $quote->engineer_id === (int) $user->id;
                }
                // Se não tem engenheiro atribuído, verificar se o usuário tem a permissão
                return $this->checkUserHasLevelPermission($user, $level);
            }
            // Para outros níveis intermediários, verificar apenas a permissão
            return $this->checkUserHasLevelPermission($user, $level);
        }
        
        // Se o status é "analise_gerencia", DIRETOR e PRESIDENTE podem aprovar diretamente.
        // ENGENHEIRO, GERENTE_LOCAL e GERENTE_GERAL também podem aprovar nesse status (não exigir
        // que o diretor aprove antes): o engenheiro pode assinar mesmo com a cotação aguardando o diretor.
        if ($currentStatus === 'analise_gerencia') {
            if (in_array($level, ['DIRETOR', 'PRESIDENTE'], true)) {
                return $this->checkUserHasLevelPermission($user, $level);
            }
            if (in_array($level, $simultaneousLevels, true)) {
                if ($level === 'ENGENHEIRO') {
                    if ($quote->engineer_id) {
                        return (int) $quote->engineer_id === (int) $user->id;
                    }
                    return $this->checkUserHasLevelPermission($user, $level);
                }
                return $this->checkUserHasLevelPermission($user, $level);
            }
        }

        // Para outros casos, verificar se todos os níveis anteriores foram aprovados
        $order = $this->getApprovalOrder();
        $currentOrder = $order[$level] ?? 999;
        
        // Níveis intermediários simultâneos (todos têm ordem 2)
        $intermediateLevels = ['ENGENHEIRO', 'GERENTE_LOCAL', 'GERENTE_GERAL'];

        // Se o nível atual é DIRETOR ou PRESIDENTE, verificar:
        // 1. Níveis de ordem menor (COMPRADOR)
        // 2. Todos os níveis intermediários (ordem 2) devem estar aprovados
        if (in_array($level, ['DIRETOR', 'PRESIDENTE'], true)) {
            // Verificar níveis de ordem menor (apenas COMPRADOR tem ordem 1)
            $previousOrderLevels = array_filter($order, function ($orderValue) use ($currentOrder) {
                return $orderValue < $currentOrder;
            });
            
            foreach ($previousOrderLevels as $prevLevel => $prevOrder) {
                $prevApproval = $quote->approvals()
                    ->byLevel($prevLevel)
                    ->required()
                    ->first();

                // Se o nível anterior é requerido mas não foi aprovado, não pode aprovar este
                if ($prevApproval && !$prevApproval->approved) {
                    return false;
                }
            }
            
            // Verificar se todos os níveis intermediários requeridos foram aprovados
            $requiredIntermediateApprovals = $quote->approvals()
                ->required()
                ->whereIn('approval_level', $intermediateLevels)
                ->get();
            
            foreach ($requiredIntermediateApprovals as $intermediateApproval) {
                if (!$intermediateApproval->approved) {
                    return false;
                }
            }
        } else {
            // Para outros níveis (COMPRADOR), verificar apenas níveis de ordem menor
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
        }

        // Verificar se o usuário tem a permissão específica para o nível
        return $this->checkUserHasLevelPermission($user, $level);
    }

    /**
     * Verifica se o usuário tem permissão para o nível (método público para uso externo)
     */
    public function userHasLevelPermission(User $user, string $level): bool
    {
        return $this->checkUserHasLevelPermission($user, $level);
    }
    
    /**
     * Verifica se o usuário tem permissão para o nível (método protegido interno)
     * Verifica as permissões específicas do usuário, não os grupos
     */
    protected function checkUserHasLevelPermission(User $user, string $level): bool
    {
        // Mapear nível para slug de permissão
        $levelToPermissionMap = [
            'COMPRADOR' => 'cotacoes_aprovar_comprador',
            'ENGENHEIRO' => 'cotacoes_aprovar_engenheiro',
            'GERENTE_LOCAL' => 'cotacoes_aprovar_gerente_local',
            'GERENTE_GERAL' => 'cotacoes_aprovar_gerente_geral',
            'DIRETOR' => 'cotacoes_aprovar_diretor',
            'PRESIDENTE' => 'cotacoes_aprovar_presidente',
        ];

        $permissionSlug = $levelToPermissionMap[$level] ?? null;
        
        if (!$permissionSlug) {
            return false;
        }

        // Verificar se o usuário tem a permissão específica
        return $user->hasPermission($permissionSlug);
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
     * Baseado nas permissões específicas do usuário, não nos grupos
     */
    public function getUserApprovalLevels(User $user, ?int $companyId = null): array
    {
        $levels = [];
        
        // Mapear permissões para níveis de aprovação
        // Ordem importa: verificar níveis de maior hierarquia primeiro
        $permissionToLevelMap = [
            'cotacoes_aprovar_presidente' => 'PRESIDENTE',
            'cotacoes_aprovar_diretor' => 'DIRETOR',
            'cotacoes_aprovar_gerente_geral' => 'GERENTE_GERAL',
            'cotacoes_aprovar_gerente_local' => 'GERENTE_LOCAL',
            'cotacoes_aprovar_engenheiro' => 'ENGENHEIRO',
            'cotacoes_aprovar_comprador' => 'COMPRADOR',
        ];
        
        // Verificar cada permissão e adicionar o nível correspondente se o usuário tiver a permissão
        foreach ($permissionToLevelMap as $permissionSlug => $level) {
            if ($user->hasPermission($permissionSlug)) {
                $levels[] = $level;
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

        $currentStatus = $quote->current_status_slug ?? '';
        $simultaneousLevels = ['ENGENHEIRO', 'GERENTE_LOCAL', 'GERENTE_GERAL'];
        
        $pendingApprovals = $quote->approvals()
            ->required()
            ->pending()
            ->ordered()
            ->get();

        // Priorizar o nível de maior hierarquia quando o usuário tem múltiplos níveis
        // Ordem de prioridade: GERENTE_GERAL > GERENTE_LOCAL > ENGENHEIRO
        $priorityOrder = ['GERENTE_GERAL' => 1, 'GERENTE_LOCAL' => 2, 'ENGENHEIRO' => 3, 'DIRETOR' => 4, 'PRESIDENTE' => 5];
        $sortedApprovals = $pendingApprovals->sortBy(function ($approval) use ($priorityOrder) {
            return $priorityOrder[$approval->approval_level] ?? 999;
        });
        
        foreach ($sortedApprovals as $approval) {
            if (in_array($approval->approval_level, $userLevels)) {
                // Se o status é "finalizada", "analisada" ou "analisada_aguardando" e o nível é um dos simultâneos,
                // verificar apenas se o usuário tem a permissão específica
                if (in_array($currentStatus, ['finalizada', 'analisada', 'analisada_aguardando'], true) &&
                    in_array($approval->approval_level, $simultaneousLevels, true)) {
                    // Para ENGENHEIRO, verificar se o usuário é o engenheiro atribuído
                    if ($approval->approval_level === 'ENGENHEIRO') {
                        // Se a cotação tem um engenheiro atribuído, apenas ele pode aprovar
                        if ($quote->engineer_id) {
                            // Converter ambos para int para garantir comparação correta
                            if ((int) $quote->engineer_id === (int) $user->id) {
                                return $approval->approval_level;
                            }
                            continue; // Não é o engenheiro atribuído, pular
                        }
                        // Se não tem engenheiro atribuído, verificar se o usuário tem a permissão
                        if ($this->checkUserHasLevelPermission($user, $approval->approval_level)) {
                            return $approval->approval_level;
                        }
                    } else {
                        // Para outros níveis intermediários, verificar apenas a permissão
                        if ($this->checkUserHasLevelPermission($user, $approval->approval_level)) {
                            return $approval->approval_level;
                        }
                    }
                } elseif ($currentStatus === 'analise_gerencia') {
                    // Em "analise_gerencia", DIRETOR/PRESIDENTE e também ENGENHEIRO/GERENTE_LOCAL/GERENTE_GERAL
                    // podem aprovar (engenheiro pode assinar mesmo sem aprovação do diretor)
                    if ($approval->approval_level === 'ENGENHEIRO') {
                        if ($quote->engineer_id && (int) $quote->engineer_id === (int) $user->id) {
                            return 'ENGENHEIRO';
                        }
                        if (!$quote->engineer_id && $this->checkUserHasLevelPermission($user, 'ENGENHEIRO')) {
                            return 'ENGENHEIRO';
                        }
                        continue;
                    }
                    if ($this->checkUserHasLevelPermission($user, $approval->approval_level)) {
                        return $approval->approval_level;
                    }
                } else {
                    // Para outros casos, verificar se pode aprovar (todos os anteriores foram aprovados)
                    if ($this->canApproveLevel($quote, $approval->approval_level, $user)) {
                        return $approval->approval_level;
                    }
                }
            }
        }

        return null;
    }
}

