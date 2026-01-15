<?php

namespace App\Models;
use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Costcenter extends Model
{
    public $table = 'costcenter';

    protected $fillable = [
        'name',
        'description',
        'company_id',
    ];

    use HasFactory;

    public function emprestimos()
    {
        return $this->hasMany(Emprestimo::class, 'costcenter_id', 'id');
    }


}
