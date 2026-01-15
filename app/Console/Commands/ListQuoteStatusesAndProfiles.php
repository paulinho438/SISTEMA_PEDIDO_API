<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListQuoteStatusesAndProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cotacoes:list-status-profiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista todos os status de cotações e quais perfis são responsáveis por cada um';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== FLUXO DE STATUS E PERFIS DE APROVAÇÃO ===');
        $this->newLine();

        $statuses = DB::table('purchase_quote_statuses')
            ->orderBy('order')
            ->get(['slug', 'label', 'description', 'required_profile', 'order']);

        $this->info('Status e Perfis Responsáveis:');
        $this->newLine();

        foreach ($statuses as $status) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Ordem: {$status->order} | Status: {$status->label} ({$status->slug})");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("  Descrição: {$status->description}");
            $this->line("  Perfil Responsável: " . ($status->required_profile ?? 'Não definido'));
            $this->newLine();
        }

        $this->info('=== RESUMO DO FLUXO ===');
        $this->newLine();
        $this->line('1. SOLICITANTE (Colaborador) → Cria solicitação → Status: "aguardando"');
        $this->line('2. SUPERVISOR DE COMPRAS → Analisa e seleciona níveis → Status: "em_analise_supervisor" → "autorizado"');
        $this->line('3. SUPERVISOR DE COMPRAS → Atribui comprador → Status: "cotacao"');
        $this->line('4. COMPRADOR → Elabora cotação → Status: "compra_em_andamento" → "finalizada"');
        $this->line('5. SUPERVISOR DE COMPRAS → Analisa cotação → Status: "analisada" ou "analisada_aguardando"');
        $this->line('6. GERÊNCIA → Analisa → Status: "analise_gerencia"');
        $this->line('7. DIRETORIA → Aprova → Status: "aprovado"');
        $this->newLine();
        $this->info('=== NOVO SISTEMA DE APROVAÇÃO HIERÁRQUICA ===');
        $this->newLine();
        $this->line('Após o Supervisor selecionar os níveis, as aprovações seguem a hierarquia:');
        $this->line('  → COMPRADOR (se selecionado)');
        $this->line('  → GERENTE LOCAL (se selecionado)');
        $this->line('  → ENGENHEIRO (se selecionado)');
        $this->line('  → GERENTE GERAL (se selecionado)');
        $this->line('  → DIRETOR (se selecionado)');
        $this->line('  → PRESIDENTE (se selecionado)');
        $this->line('');
        $this->line('Quando todas as aprovações são concluídas → Status: "aprovado"');

        return Command::SUCCESS;
    }
}

