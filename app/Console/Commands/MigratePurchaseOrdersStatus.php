<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePurchaseOrdersStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-orders:migrate-status 
                            {--dry-run : Apenas simular, não aplicar mudanças}
                            {--force : Forçar migração mesmo se já tiver histórico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra pedidos existentes para o novo fluxo de status (pendente → link → link_aprovado → ...)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->info('🔍 MODO DRY-RUN: Nenhuma alteração será aplicada');
        }

        $this->info('📦 Iniciando migração de status dos pedidos...');
        $this->newLine();

        // Buscar todos os pedidos
        $orders = PurchaseOrder::with('quote')->get();
        
        $this->info("Total de pedidos encontrados: {$orders->count()}");
        $this->newLine();

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        $statusService = app(PurchaseOrderStatusService::class);

        foreach ($orders as $order) {
            try {
                $currentStatus = $order->status;
                $newStatus = $this->determineNewStatus($order, $currentStatus);

                // Se o status não mudou, pular
                if ($currentStatus === $newStatus) {
                    $skipped++;
                    continue;
                }

                // Verificar se já tem histórico (se não for force)
                if (!$force && $order->statusHistory()->exists()) {
                    $this->warn("  ⚠️  Pedido {$order->order_number} já tem histórico. Use --force para migrar.");
                    $skipped++;
                    continue;
                }

                $this->line("  📝 Pedido {$order->order_number}: {$currentStatus} → {$newStatus}");

                if (!$dryRun) {
                    DB::beginTransaction();
                    try {
                        // Atualizar status
                        $order->status = $newStatus;
                        $order->save();

                        // Criar histórico inicial se não existir
                        if (!$order->statusHistory()->exists()) {
                            $order->statusHistory()->create([
                                'old_status' => $currentStatus,
                                'new_status' => $newStatus,
                                'justification' => 'Migração automática para novo fluxo de status',
                                'changed_by' => $order->created_by ?? 1,
                            ]);
                        }

                        DB::commit();
                        $migrated++;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $this->error("  ❌ Erro ao migrar pedido {$order->order_number}: {$e->getMessage()}");
                        $errors++;
                    }
                } else {
                    $migrated++;
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Erro ao processar pedido {$order->order_number}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info('✅ Migração concluída!');
        $this->table(
            ['Tipo', 'Quantidade'],
            [
                ['Migrados', $migrated],
                ['Ignorados', $skipped],
                ['Erros', $errors],
            ]
        );

        if ($dryRun) {
            $this->warn('⚠️  Esta foi uma simulação. Execute sem --dry-run para aplicar as mudanças.');
        }

        return Command::SUCCESS;
    }

    /**
     * Determina o novo status baseado no status atual e no estado do pedido
     */
    private function determineNewStatus(PurchaseOrder $order, string $currentStatus): string
    {
        // Status novos já estão corretos
        $newStatuses = [
            'pendente',
            'link',
            'link_aprovado',
            'link_reprovado',
            'coleta',
            'em_transito',
            'atendido',
            'atendido_parcial',
            'pagamento',
            'encerrado',
        ];

        if (in_array($currentStatus, $newStatuses)) {
            return $currentStatus; // Já está no novo formato
        }

        // Mapear status antigos para novos
        $statusMapping = [
            'recebido' => 'atendido', // Se estava recebido, provavelmente foi atendido
            'parcial' => 'atendido_parcial', // Se estava parcial, provavelmente foi atendido parcialmente
            'parcialmente_recebido' => 'atendido_parcial',
            'cancelado' => 'cancelado', // Manter cancelado
        ];

        // Se tem mapeamento direto, usar
        if (isset($statusMapping[$currentStatus])) {
            return $statusMapping[$currentStatus];
        }

        // Se a cotação está aprovada e o pedido está pendente (status antigo), 
        // verificar se já foi encaminhado para PROTHEUS
        if ($order->quote && $order->quote->current_status_slug === 'aprovado') {
            // Se o pedido foi criado há mais de 1 dia, assumir que já foi encaminhado
            $daysSinceCreation = now()->diffInDays($order->created_at);
            if ($daysSinceCreation > 1) {
                return 'link'; // Provavelmente já foi encaminhado
            }
        }

        // Padrão: manter como pendente (será migrado manualmente pelo comprador)
        return 'pendente';
    }
}
