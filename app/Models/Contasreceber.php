<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contasreceber extends Model
{
    public $table = 'contasreceber';

    public $timestamps = false;

    protected $fillable = [
        'banco_id',
        'parcela_id',
        'client_id',
        'company_id',
        'forma_recebto',
        'descricao',
        'status',
        'tipodoc',
        'lanc',
        'venc',
        'valor',
        'dt_baixa'
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'banco_id', 'id');
    }

    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'parcela_id', 'id');
    }

    public function cliente()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }


}
