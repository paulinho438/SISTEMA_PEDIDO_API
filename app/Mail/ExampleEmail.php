<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ClientsExport;
use App\Exports\EmprestimosExport;

class ExampleEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;
    public $locacao;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details, $locacao)
    {
        $this->details = $details;
        $this->locacao = $locacao;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $excelFile = Excel::raw(new ClientsExport($this->locacao), \Maatwebsite\Excel\Excel::XLSX);

        $excelFile2 = Excel::raw(new EmprestimosExport($this->locacao), \Maatwebsite\Excel\Excel::XLSX);

        $clients = $this->locacao->company->clients;

        // Transformar os dados dos clientes em uma coleção
        $clientData = $clients->map(function ($client) {
            return [
                $client->nome_completo,
                $client->email,
                $client->created_at->format('d/m/Y'),
                // Adicione mais campos conforme necessário
            ];
        });

        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->subject('Relatório de Empréstimos Finalizados')
                    ->view('emails.example', compact('clientData'))
                    ->attachData($excelFile, 'clientes.xlsx', [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->attachData($excelFile2, 'emprestimos_finalizados.xlsx', [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
    }
}
