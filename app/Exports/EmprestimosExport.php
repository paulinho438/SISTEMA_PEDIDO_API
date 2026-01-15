<?php
namespace App\Exports;

use App\Http\Resources\EmprestimoResource;
use App\Models\Emprestimo;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmprestimosExport implements FromCollection, WithHeadings
{
    protected $locacao;

    public function __construct($locacao)
    {
        $this->locacao = $locacao;
    }

    public function collection()
    {
        // Obter os empréstimos associados à empresa da locação
        $emprestimos = Emprestimo::where('company_id', $this->locacao->company->id)
            ->orderBy('id', 'desc')
            ->get();

        // Transformar os dados dos empréstimos usando o Resource
        $emprestimosResource = EmprestimoResource::collection($emprestimos);

        // Transformar os dados dos empréstimos em uma coleção
        $emprestimoData = $emprestimosResource->map(function ($emprestimo) {
            return [
                $emprestimo->dt_lancamento,
                $emprestimo->banco->name,
                $emprestimo->client->nome_completo,
                $emprestimo->costcenter->name,
                $emprestimo->valor,
                $emprestimo->lucro,
                $emprestimo->juros,
            ];
        });

        return new Collection($emprestimoData);
    }

    public function headings(): array
    {
        return [
            'Data de Lançamento',
            'Banco',
            'Nome do Cliente',
            'Centro de Custo',
            'Valor do Empréstimo',
            'Lucro',
            'Juros',
        ];
    }
}
