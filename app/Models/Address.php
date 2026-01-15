<?php

namespace App\Models;

use App\Models\Permgroup;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    public $table = 'address';

    protected $fillable = [
        'description',
        'address',
        'cep',
        'number',
        'complement',
        'neighborhood',
        'city',
        'latitude',
        'longitude',
        'client_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

}
