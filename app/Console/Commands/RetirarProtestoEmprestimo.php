<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Http;

use App\Models\Juros;
use App\Models\Parcela;
use App\Models\Feriado;
use App\Models\Emprestimo;

use Efi\Exception\EfiException;
use Efi\EfiPay;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Carbon\Carbon;

class RetirarProtestoEmprestimo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retirarProtesto:Emprestimo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Protestar Empréstimo após 2 semanas de atraso';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): void
    {

        $this->info('Realizando o protesto de Empréstimos com 2 semanas de atraso');

        $emprestimos = Emprestimo::where('protesto', 1)
            ->with(['parcelas' => function ($query) {
                $query->orderByDesc('id');
            }])
            ->get()
            ->filter(function ($emprestimo) {
                $ultimaParcela = $emprestimo->parcelas->first();

                if (!$ultimaParcela) {
                    return false;
                }

                return true;
            })
            ->values();

        foreach ($emprestimos as $emprestimo) {
//            Log::info(message: "ROTINA_RETIRAR_PROTESTO: Verificando se emprestimo: $emprestimo->id pode ser retirado de protesto");
            $podeCancelarProtesto = true;

            foreach ($emprestimo->parcelas as $parcela) {
                if(is_null($parcela->dt_baixa) && $parcela->atrasadas >= 21){
                    $podeCancelarProtesto = false;
//                    Log::info(message: "ROTINA_RETIRAR_PROTESTO: Emprestimo: $emprestimo->id não pode ser retirado de protesto, parcela: $parcela->id");
                }
            }

            if($podeCancelarProtesto){
//                Log::info(message: "ROTINA_RETIRAR_PROTESTO: Emprestimo: $emprestimo->id foi retirado de protesto");
                $emprestimo->protesto = 0;
                $emprestimo->save();
            }
        }
    }
}
