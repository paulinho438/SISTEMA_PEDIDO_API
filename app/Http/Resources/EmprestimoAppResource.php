<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

use App\Models\Emprestimo;
use DateTime;

class EmprestimoAppResource extends JsonResource
{
    public function porcent($vl1, $vl2)
    {
        if ($vl1 != 0) {
            return number_format(($vl2 / $vl1) * 100, 1);
        } else {
            return 0;
        }
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $parcelas = $this->parcelas;

        $saldoareceber = $parcelas->where('dt_baixa', null)->sum('saldo');
        $saldoatrasado = $parcelas->where('dt_baixa', null)->where('venc_real', now()->toDateString())->sum('saldo');
        $porcentagem = $this->porcent($parcelas->sum('valor'), $parcelas->where('dt_baixa', '<>', null)->sum('valor'));
        $saldo_total_parcelas_pagas = $parcelas->where('dt_baixa', '<>', null)->sum('valor');
        $parcelas_vencidas = ParcelaResource::collection($parcelas->where('dt_baixa', null));
        $parcelas_pagas = $parcelas->where('dt_baixa', '<>', null)->values()->all();
        $totalParcelas = $this->parcelas->count();
        $parcelasPagas = $this->parcelas->whereNotNull('dt_baixa')->count();
        $mensalidade = $this->parcelas->first() ? $this->parcelas->first()->valor : 0;

        return [
            "id" => $this->id,
            "dt_lancamento" => (new DateTime($this->dt_lancamento))->format('d/m/Y'),
            "valor" => $this->valor,
            "valor_deposito" => $this->valor_deposito,
            "lucro" => $this->lucro,
            "juros" => $this->juros,
            "cliente_cadastrado" => $this->client->nome_usuario_criacao,
            "saldoareceber" => $saldoareceber,
            "saldoatrasado" => $saldoatrasado,
            "porcentagem" => $porcentagem,
            "saldo_total_parcelas_pagas" => $saldo_total_parcelas_pagas,
            "costcenter" => $this->costcenter,
            // "banco" => new BancosResource($this->banco),
            // "cliente" => new ClientResource($this->client),
            "consultor" => $this->user,
            "parcelas_vencidas" => $parcelas_vencidas,
            "parcelas" => ParcelaResource::collection($parcelas->sortBy('parcela')),
            "quitacao" => new QuitacaoResource($this->quitacao),
            "pagamentominimo" => new PagamentoMinimoResource($this->pagamentominimo),
            "pagamentosaldopendente" => new PagamentoSaldoPendenteResource($this->pagamentosaldopendente),
            "parcelas_pagas" => $parcelas_pagas,
            "status" => $this->getStatus(),
            "telefone_empresa" => $this->company->numero_contato,
            "dt_envio_mensagem_renovacao" => $this->dt_envio_mensagem_renovacao,
            "historico_formatado" => $this->formatHistorico(),
            "codigo_emprestimo" => $this->id,
            "valor_total" => number_format($this->valor, 2, ',', '.'),
            "mensalidade" => number_format($mensalidade, 2, ',', '.'),
            "progresso_meses" => "{$parcelasPagas} de {$totalParcelas} meses",
        ];
    }

    // Método para calcular o status das parcelas
    private function getStatus()
    {
        $status = 'Em Dias'; // Padrão
        $qtParcelas = count($this->parcelas);
        $qtPagas = 0;
        $qtAtrasadas = 0;

        foreach ($this->parcelas as $parcela) {
            if ($parcela->atrasadas > 0 && $parcela->saldo > 0) {
                $qtAtrasadas++;
            }
        }

        if ($qtAtrasadas > 0) {
            if ($this->isMaiorQuatro($qtAtrasadas, $qtParcelas)) {
                $status = 'Muito Atrasado';
            } else {
                $status = 'Atrasado';
            }

            if ($qtAtrasadas == $qtParcelas) {
                $status = 'Vencido';
            }
        }

        foreach ($this->parcelas as $parcela) {
            if ($parcela->dt_baixa != null) {
                $qtPagas++;
            }
        }

        if ($qtParcelas == $qtPagas) {
            $status = 'Pago';
        }

        return $status;
    }

    private function isMaiorQuatro($x, $y)
    {
        return $x > 5;
    }

    private function formatHistorico()
    {
        $totalParcelas = count($this->parcelas);
        $historico = [];

        foreach ($this->parcelas->sortBy('parcela') as $index => $parcela) {
            $numero = intval($parcela->parcela);
            $sub = "{$numero} de {$totalParcelas} meses";
            $data = Carbon::parse($parcela->venc_real)->format('d M');
            $venc_real = Carbon::parse($parcela->venc_real)->format('d/M/y');
            $valor = number_format($parcela->valor, 2, ',', '.');

            if ($parcela->dt_baixa) {
                $historico[] = [
                    'title' => 'Mensalidade paga',
                    'sub' => $sub,
                    'data' => strtoupper($data),
                    'valor' => "R$ {$valor}",
                    'riscado' => false,
                    'tag' => true,
                    'isPago' => true,
                ];
            } else {
                $historico[] = [
                    'title' => 'Mensalidade pendente',
                    'sub' => $sub,
                    'data' => strtoupper($data),
                    'vencimento' => $venc_real,
                    'valor' => "R$ {$valor}",
                    'riscado' => false,
                    'tag' => false,
                    'chave_pix' => ($parcela->chave_pix != null) ? $parcela->chave_pix : $parcela->emprestimo->banco->chavepix,
                    'msgPagamento' => ($parcela->chave_pix != null)
                    ? 'Copie o código Pix e pague no aplicativo do seu banco. O pagamento é confirmado na hora.'
                    : "Faça o pagamento de R$ {$valor} para a chave pix abaixo e envie o comprovante para o nosso whatsapp.",
                    'noAvancar' => true,
                ];
            }
        }

        // Adiciona o evento de início do empréstimo
        $historico[] = [
            'title' => 'Início do empréstimo realizado',
            'sub' => 'Plano #' . $this->id,
            'data' => strtoupper(Carbon::parse($this->dt_lancamento)->format('d M')),
            'icon' => 'login',
            'noValor' => true,
        ];

        return $historico;
    }
}
