<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetResponsible extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'matricula',
        'description',
        'active',
        'company_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'responsible_id');
    }
}
