<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Juros extends Model
{
    public $table = 'juros';

    public $timestamps = false;

    protected $fillable = [
        'juros',
        'company_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
