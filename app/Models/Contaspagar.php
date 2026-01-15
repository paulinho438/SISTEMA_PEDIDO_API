<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contaspagar extends Model
{
    public $table = 'contaspagar';

    public $timestamps = false;

    protected $fillable = [
        'banco_id',
        'emprestimo_id',
        'fornecedor_id',
        'costcenter_id',
        'company_id',
        'status',
        'tipodoc',
        'descricao',
        'lanc',
        'venc',
        'valor',
        'dt_baixa'
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'banco_id', 'id');
    }

    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id', 'id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id', 'id');
    }

    public function costcenter()
    {
        return $this->belongsTo(Costcenter::class, 'costcenter_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }


}
