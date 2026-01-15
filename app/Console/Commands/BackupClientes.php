<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Http;

use App\Models\Juros;
use App\Models\Parcela;

use Efi\Exception\EfiException;
use Efi\EfiPay;

use App\Models\CustomLog;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class BackupClientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rotinas:BackupClientes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realiza o backup dos clientes e envia por e-mail';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $this->info('Enviando email teste');


        Mail::send('mail.teste', ['teste' => 'aa'], function ($message) {
            $message->from('paulinho483@gmail.com'); // email do destinatário
            $message->to('paulo_henrique500@hotmail.com'); // email do destinatário
            $message->subject('Teste de Email');
        });

        $this->info('Email enviado com sucesso');

        exit;
    }
}
