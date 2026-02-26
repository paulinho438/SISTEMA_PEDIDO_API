<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Permgroup;

use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $dates = ['deleted_at'];
    /**
     * Formato "seguro" para SQL Server (independe de DATEFORMAT).
     * Ex.: 20260226 103439
     */
    protected $dateFormat = 'Ymd H:i:s';

    public function setDataNascimentoAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['data_nascimento'] = null;
            return;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                $this->attributes['data_nascimento'] = $value->format('Ymd'); // seguro no SQL Server
                return;
            }

            if (is_string($value)) {
                $value = trim($value);
                // dd/mm/yyyy
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                    $dt = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                    $this->attributes['data_nascimento'] = $dt->format('Ymd');
                    return;
                }
                // yyyy-mm-dd
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $dt = \Carbon\Carbon::createFromFormat('Y-m-d', $value);
                    $this->attributes['data_nascimento'] = $dt->format('Ymd');
                    return;
                }
            }

            $dt = \Carbon\Carbon::parse($value);
            $this->attributes['data_nascimento'] = $dt->format('Ymd');
        } catch (\Throwable $e) {
            // Se vier algo inválido, não derrubar o insert/update
            $this->attributes['data_nascimento'] = null;
        }
    }

    protected $hidden = [
        'password'
    ];

    public $fillable = [
        'nome_completo',
        'rg',
        'cpf',
        'login',
        'data_nascimento',
        'sexo',
        'telefone_celular',
        'email',
        'signature_path',
        'status',
        'status_motivo',
        'tentativas',
        'password'
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function companies(){
        return $this->belongsToMany(Company::class);
    }



    public function groups() {
        return $this->belongsToMany(Permgroup::class);
    }

    public function getCompaniesAsString()
    {
        return $this->companies()->pluck('company')->implode(', ');
    }

    public function hasPermission($permission)
    {
        return $this->groups()->whereHas('items', function ($query) use ($permission) {
            $query->where('slug', $permission);
        })->exists();
    }

    // Método para obter o nome do grupo pelo ID da empresa
    public function getGroupNameByEmpresaId($empresaId)
    {
        $group = $this->groups()->where('company_id', $empresaId)->first();

        return $group ? $group->name : null;
    }

    public function getGroupByEmpresaId($empresaId)
    {
        $group = $this->groups()->where('company_id', $empresaId)->first();

        return $group ? $group : null;
    }
    public function emprestimos()
    {
        return $this->hasMany(Emprestimo::class, 'user_id', 'id');
    }

    public function locations()
    {
        return $this->hasMany(UserLocation::class);
    }
}

