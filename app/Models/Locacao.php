<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Locacao extends Model
{
    public $table = 'locacao';

    protected $fillable = [
        'id',
        'type',
        'data_vencimento',
        'data_pagamento',
        'valor',
        'company_id',
        'chave_pix',
        'identificador',
        'ult_dt_geracao_pix'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
