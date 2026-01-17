<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
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
        return $this->hasMany(Asset::class, 'branch_id');
    }
}

