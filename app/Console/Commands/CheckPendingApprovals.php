<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteApproval;
use Illuminate\Support\Facades\DB;

class CheckPendingApprovals extends Command
{
    protected $signature = 'check:pending-approvals';
    protected $description = 'Verifica aprovações pendentes no sistema';

    public function handle()
    {
        $this->info("=== VERIFICAÇÃO DE APROVAÇÕES PENDENTES ===");
        $this->newLine();
        
        // Total de aprovações pendentes
        $pendingCount = PurchaseQuoteApproval::where('required', true)
            ->where('approved', false)
            ->count();
        
        $this->info("Total de aprovações pendentes: {$pendingCount}");
        $this->newLine();
        
        // Agrupar por nível
        $byLevel = PurchaseQuoteApproval::where('required', true)
            ->where('approved', false)
            ->select('approval_level', DB::raw('count(*) as total'))
            ->groupBy('approval_level')
            ->get();
        
        $this->info("Aprovações pendentes por nível:");
        foreach ($byLevel as $item) {
            $this->line("  - {$item->approval_level}: {$item->total}");
        }
        $this->newLine();
        
        // Cotações com aprovações pendentes
        $quotes = PurchaseQuote::whereHas('approvals', function ($q) {
            $q->where('required', true)->where('approved', false);
        })->get(['id', 'quote_number', 'current_status_slug', 'company_id']);
        
        $this->info("Cotações com aprovações pendentes: {$quotes->count()}");
        foreach ($quotes->take(10) as $quote) {
            $pending = $quote->approvals()
                ->where('required', true)
                ->where('approved', false)
                ->pluck('approval_level')
                ->toArray();
            
            $this->line("  - {$quote->quote_number} (ID: {$quote->id}, Status: {$quote->current_status_slug}, Company: {$quote->company_id})");
            $this->line("    Pendentes: " . implode(', ', $pending));
        }
        
        return 0;
    }
}

