<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\PurchaseQuote;
use App\Services\PurchaseQuote\PurchaseQuoteApprovalService;
use Illuminate\Support\Facades\DB;

class TestMyApprovalsFilter extends Command
{
    protected $signature = 'test:my-approvals-filter {user_id} {company_id?}';
    protected $description = 'Testa o filtro my_approvals para um usuário específico';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $companyId = $this->argument('company_id');
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("Usuário {$userId} não encontrado");
            return 1;
        }
        
        if (!$companyId) {
            $company = $user->companies()->first();
            if (!$company) {
                $this->error("Usuário não tem empresas associadas");
                return 1;
            }
            $companyId = $company->id;
        }
        
        $this->info("=== TESTE DO FILTRO MY_APPROVALS ===");
        $this->newLine();
        $this->info("Usuário: {$user->nome_completo} (ID: {$user->id})");
        $this->info("Empresa: {$companyId}");
        $this->newLine();
        
        // Verificar grupos do usuário
        $groups = $user->groups()->where('company_id', $companyId)->get(['id', 'name', 'company_id']);
        $this->info("Grupos do usuário nesta empresa:");
        foreach ($groups as $group) {
            $this->line("  - {$group->name} (ID: {$group->id}, Company: {$group->company_id})");
        }
        $this->newLine();
        
        // Testar getUserApprovalLevels
        $approvalService = app(PurchaseQuoteApprovalService::class);
        $userLevels = $approvalService->getUserApprovalLevels($user, $companyId);
        
        $this->info("Níveis de aprovação que o usuário pode aprovar:");
        if (empty($userLevels)) {
            $this->warn("  NENHUM nível encontrado!");
        } else {
            foreach ($userLevels as $level) {
                $this->line("  - {$level}");
            }
        }
        $this->newLine();
        
        // Buscar cotações com aprovações pendentes
        if (!empty($userLevels)) {
            $quoteIds = DB::table('purchase_quotes')
                ->select('purchase_quotes.id', 'purchase_quotes.quote_number', 'purchase_quotes.current_status_slug')
                ->join('purchase_quote_approvals', 'purchase_quotes.id', '=', 'purchase_quote_approvals.purchase_quote_id')
                ->whereIn('purchase_quote_approvals.approval_level', $userLevels)
                ->where('purchase_quote_approvals.required', true)
                ->where('purchase_quote_approvals.approved', false)
                ->where('purchase_quotes.company_id', $companyId)
                ->distinct()
                ->get();
            
            $this->info("Cotações com aprovações pendentes nos níveis do usuário:");
            if ($quoteIds->isEmpty()) {
                $this->warn("  NENHUMA cotação encontrada!");
            } else {
                foreach ($quoteIds as $quote) {
                    $this->line("  - {$quote->quote_number} (ID: {$quote->id}, Status: {$quote->current_status_slug})");
                }
            }
            $this->newLine();
            
            // Verificar quais o usuário realmente pode aprovar
            $this->info("Cotações que o usuário PODE aprovar (todos os anteriores foram aprovados):");
            $canApproveCount = 0;
            foreach ($quoteIds as $quoteData) {
                $quote = PurchaseQuote::with('approvals')->find($quoteData->id);
                if ($quote) {
                    $nextLevel = $approvalService->getNextPendingLevelForUser($quote, $user);
                    if ($nextLevel) {
                        $canApproveCount++;
                        $this->line("  ✓ {$quote->quote_number} (ID: {$quote->id}) - Próximo nível: {$nextLevel}");
                    } else {
                        $this->line("  ✗ {$quote->quote_number} (ID: {$quote->id}) - Não pode aprovar ainda");
                    }
                }
            }
            $this->newLine();
            $this->info("Total de cotações que o usuário pode aprovar: {$canApproveCount}");
        } else {
            $this->warn("Não é possível buscar cotações porque o usuário não tem níveis de aprovação.");
        }
        
        return 0;
    }
}

