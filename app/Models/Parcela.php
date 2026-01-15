<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parcela extends Model
{
    public $table = 'parcelas';

    public $timestamps = true;

    protected $fillable = [
        'emprestimo_id',
        'parcela',
        'valor',
        'saldo',
        'venc',
        'venc_real',
        'dt_lancamento',
        'dt_baixa',
        'identificador',
        'chave_pix',
        'tentativas',
        'dt_ult_cobranca',
        'valor_recebido_pix',
        'valor_recebido',
        'ult_dt_geracao_pix',
        'nome_usuario_baixa_pix',
        'nome_usuario_baixa',
        'ult_dt_processamento_rotina',
        'venc_real_audit'
    ];

    public function emprestimo()
    {
        return $this->belongsTo(Emprestimo::class, 'emprestimo_id', 'id');
    }

    public function contasreceber()
    {
        return $this->hasOne(Contasreceber::class, 'parcela_id', 'id');
    }

    public function movimentacao()
    {
        return $this->hasMany(Movimentacaofinanceira::class, 'parcela_id', 'id');
    }

    // public function totalPago()
    // {
    //     return $this->movimentacao()->sum('valor');
    // }

    public function totalPagoEmprestimo()
    {
        return Movimentacaofinanceira::whereHas('parcela', function ($query) {
            $query->where('emprestimo_id', $this->emprestimo_id);
        })->sum('valor');
    }

    public function totalPagoParcela()
    {
        return Movimentacaofinanceira::where('parcela_id', $this->id)->sum('valor');
    }

    public function totalPendente()
    {
        $totalPendente = Parcela::where('emprestimo_id', $this->emprestimo_id)
            ->where('dt_baixa', null)
            ->sum('saldo');

        // Arredonda o valor para 2 casas decimais e retorna como float
        return round((float) $totalPendente, 2);
    }

    public function countLateParcels()
    {
        // Conta o número de parcelas que tiveram atraso
        $lateParcelsCount = Parcela::where('emprestimo_id', $this->emprestimo_id)
            ->where('atrasadas', '>', 0)
            ->count();

        return $lateParcelsCount;
    }

    public function totalPendenteHoje()
    {
        $today = now()->toDateString(); // Obtém a data de hoje no formato YYYY-MM-DD

        $totalPendente = Parcela::where('emprestimo_id', $this->emprestimo_id)
            ->where('dt_baixa', null)
            ->where('venc_real', $today)
            ->sum('saldo');

        // Arredonda o valor para 2 casas decimais e retorna como float
        return round((float) $totalPendente, 2);
    }
}
