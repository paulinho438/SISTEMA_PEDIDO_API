<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposito extends Model
{
    public $table = 'depositos';

    protected $fillable = [
        'banco_id',
        'valor',
        'identificador',
        'company_id',
        'data_pagamento',
        'chave_pix',
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'banco_id', 'id');

    }

}
