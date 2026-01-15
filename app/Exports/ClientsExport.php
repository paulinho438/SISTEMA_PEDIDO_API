<?php
namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientsExport implements FromCollection, WithHeadings
{
    protected $locacao;

    public function __construct($locacao)
    {
        $this->locacao = $locacao;
    }
    public function collection()
    {
         // Obter todos os clientes associados à locação

         $clients = Client::leftJoin('address', 'clients.id', '=', 'address.client_id')
             ->select('*')
             ->where('company_id', $this->locacao->company->id)
             ->get();

         // Transformar os dados dos clientes em uma coleção
         $clientData = $clients->map(function ($client) {
             return [
                 $client->nome_completo,
                 $client->cpf,
                 $client->rg,
                 $client->data_nascimento,
                 $client->telefone_celular_1,
                 $client->telefone_celular_2,
                 $client->email,
                 $client->status,
                 $client->created_at->format('d/m/Y H:i:s'),
                 $client->pix_cliente,
                 $client->description,
                 $client->address,
                 $client->cep,
                 $client->number,
                 $client->neighborhood,
                 $client->city,
                 // Adicione mais campos conforme necessário
             ];
         });

         return collect($clientData);
    }

    public function headings(): array
    {
        return [
            'Nome do Cliente',
            'CPF',
            'RG',
            'Data de Nascimento',
            'Telefone celular 1',
            'Telefone celular 2',
            'E-mail',
            'STATUS',
            'DATA DE CADASTRO',
            'Chave PIX',
            'Descrição',
            'Endereço',
            'CEP',
            'Número',
            'Bairro',
            'Cidade',
        ];
    }
}
