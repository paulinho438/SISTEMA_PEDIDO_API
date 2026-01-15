<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientLocation extends Model
{
    use HasFactory;

    public $table = 'client_locations';

    protected $fillable = [
        'client_id',
        'latitude',
        'longitude',
        'timestamp',
        'company_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
