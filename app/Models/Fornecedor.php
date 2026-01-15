<?php

namespace App\Models;

use App\Models\Permgroup;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    public $table = 'fornecedores';

    use SoftDeletes;

    protected $fillable = [
        'nome_completo',
        'cpfcnpj',
        'telefone_celular_1',
        'telefone_celular_2',
        'address',
        'cep',
        'number',
        'complement',
        'neighborhood',
        'city',
        'observation',
        'company_id',
        'pix_fornecedor'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
