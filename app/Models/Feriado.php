<?php

namespace App\Models;

use App\Models\Permgroup;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feriado extends Model
{
    public $table = 'feriados';

    public $timestamps = false;

    protected $fillable = [
        'description',
        'data_feriado',
        'company_id',
    ];



    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
