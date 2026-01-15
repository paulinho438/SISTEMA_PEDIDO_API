<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagamentoSaldoPendente extends Model
{
    public $table = 'pagamento_saldo_pendente';

    public $timestamps = false;

    protected $fillable = [
        'emprestimo_id',
        'valor',
        'identificador',
        'chave_pix',
        'ult_dt_geracao_pix'
    ];

    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id', 'id');

    }

}
