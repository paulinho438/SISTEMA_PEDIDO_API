<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockProductsTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['REF-001', 'Produto Exemplo 1', 'UN', 'ALMOXARIFADO JAÍBA-MG', '10.00', '25.50', 'Importação em massa'],
            ['REF-002', 'Produto Exemplo 2', 'UN', 'ALMOXARIFADO JAÍBA-MG', '5.00', '15.75', ''],
            ['REF-003', 'Produto Exemplo 3', 'KG', 'ALMOXARIFADO ESPRAIADO-BA', '2.50', '', 'Sem custo unitário'],
        ];
    }

    public function headings(): array
    {
        return [
            'Referência',
            'Descrição',
            'Unidade',
            'Local de Estoque',
            'Quantidade',
            'Custo Unitário',
            'Observação'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

