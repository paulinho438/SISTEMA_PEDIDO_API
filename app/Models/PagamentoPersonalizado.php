<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagamentoPersonalizado extends Model
{
    public $table = 'pagamento_personalizado';

    public $timestamps = false;

    protected $fillable = [
        'emprestimo_id',
        'valor',
        'saldo',
        'dt_baixa',
        'identificador',
        'chave_pix',
        'ult_dt_geracao_pix'
    ];

    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id', 'id');
    }

}
