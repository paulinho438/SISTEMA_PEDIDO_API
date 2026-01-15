<?php

namespace App\Models;

use App\Models\Permgroup;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Planos extends Model
{
    public $table = 'planos';

    protected $fillable = [
        'nome',
        'preco',
        'descricao',
        'min_contratos',
        'max_contratos'

    ];

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

}
