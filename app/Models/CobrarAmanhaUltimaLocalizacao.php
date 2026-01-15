<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobrarAmanhaUltimaLocalizacao extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cobrar_amanha_ultima_localizacao';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'parcela_id',
        'latitude',
        'longitude',
        'timestamp',
        'company_id',
    ];

    /**
     * Relationships
     */

    // Relacionamento com o usuário
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relacionamento com a empresa
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parcela()
    {
        return $this->belongsTo(Parcela::class);
    }
}
