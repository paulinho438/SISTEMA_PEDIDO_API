<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notas extends Model
{
    public $table = 'notas';

    protected $fillable = [
        'conteudo',
        'client_id',
        'emprestimo_id'
    ];

    public function cliente()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');

    }

    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id', 'id');

    }

}
