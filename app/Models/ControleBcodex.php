<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ControleBcodex extends Model
{
    public $table = 'controle_bcodex';

    protected $fillable = [
        'identificador',
        'data_pagamento'
    ];

}
