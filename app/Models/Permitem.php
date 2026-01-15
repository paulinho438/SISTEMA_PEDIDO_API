<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permitem extends Model
{
    public $table = 'permitems';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'group'
    ];

    use HasFactory;

    public function groups() {
        return $this->belongsToMany(Permgroup::class);
    }
}
