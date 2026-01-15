<?php

namespace App\Models;
use App\Models\Permitem;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permgroup extends Model
{
    public $table = 'permgroups';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'company_id'
    ];

    use HasFactory;

    public function items() {
        return $this->belongsToMany(Permitem::class);
    }

    public function users() {
        return $this->belongsToMany(User::class);
    }

}
