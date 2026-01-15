<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movimentacaofinanceira extends Model
{
    public $table = 'movimentacaofinanceira';

    public $timestamps = false;

    protected $fillable = [
        'banco_id',
        'company_id',
        'descricao',
        'tipomov',
        'dt_movimentacao',
        'valor',
        'parcela_id',
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'banco_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'parcela_id', 'id');
    }


}
