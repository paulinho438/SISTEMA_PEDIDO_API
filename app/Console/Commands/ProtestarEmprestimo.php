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

use Illuminate\Support\Str;

use Carbon\Carbon;

class ProtestarEmprestimo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'protestar:Emprestimo';

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

        $emprestimos = Emprestimo::where('protesto', 0)
            ->whereHas('parcelas', function ($query) {
                $query->whereNull('dt_baixa')
                    ->where('atrasadas', '>', 14);
            })
            ->with(['parcelas' => function ($query) {
                $query->orderByDesc('id');
            }])
            ->get()
            ->filter(function ($emprestimo) {
                $ultimaParcela = $emprestimo->parcelas->first();

                if (!$ultimaParcela) {
                    return false;
                }

                if (!is_null($ultimaParcela->dt_baixa)) {
                    return false;
                }

                if ((int)$ultimaParcela->atrasadas <= 21) {
                    return false;
                }

                return true;
            })
            ->values();

        foreach ($emprestimos as $emprestimo) {
            $emprestimo->protesto = 1;
            $emprestimo->data_protesto = date('Y-m-d');
            $emprestimo->save();
        }
    }
}
