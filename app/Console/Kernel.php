<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\EnvioManual::class,
        Commands\EnvioManualQuitacao::class,
        Commands\EnvioManualPagamentoMinimo::class,
        Commands\RecalcularParcelas::class,
        Commands\CobrancaAutomaticaABotao::class,
        Commands\CobrancaAutomaticaBBotao::class,
        Commands\CobrancaAutomaticaCBotao::class,
        Commands\CobrancaAutomaticaA::class,
        Commands\CobrancaAutomaticaB::class,
        Commands\CobrancaAutomaticaC::class,
        Commands\EnvioMensagemRenovacao::class,
        Commands\MensagemAutomaticaRenovacao::class,
        Commands\BackupClientes::class,
        Commands\ProcessarWebhookCobranca::class,
        Commands\RetirarProtestoEmprestimo::class

    ];


    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('baixa:Automatica')->everyMinute();
        $schedule->command('baixa:AutomaticaQuitacao')->everyMinute();
        $schedule->command('baixa:AutomaticaPagamentoMinimo')->everyMinute();

        $schedule->command('cobranca:AutomaticaABotao')->everyMinute();
        $schedule->command('cobranca:AutomaticaBBotao')->everyMinute();
        $schedule->command('cobranca:AutomaticaCBotao')->everyMinute();

        $schedule->command('mensagem:AutomaticaRenovacao')->everyMinute();
        $schedule->command('webhook:baixaBcodex')->everyMinute();

        //$schedule->command('recalcular:Parcelas')->dailyAt('00:00');

        $schedule->command('recalcular:Parcelas')->everyTenMinutes()->withoutOverlapping();

        $schedule->command('cobranca:AutomaticaA')->dailyAt('08:00');
        $schedule->command('cobranca:AutomaticaB')->dailyAt('13:00');
        $schedule->command('cobranca:AutomaticaC')->dailyAt('16:30');

        $schedule->command('rotinas:BackupClientes')->dailyAt('00:00');

        $schedule->command('protestar:Emprestimo')->dailyAt('08:00');
        $schedule->command('retirarProtesto:Emprestimo')->everyMinute()->withoutOverlapping();

        $schedule->command('rotina:envioMensagemRenovacao')->everyMinute()->withoutOverlapping();


    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
