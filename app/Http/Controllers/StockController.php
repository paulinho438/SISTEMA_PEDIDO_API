<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockResource;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class StockController extends Controller
{
    protected $service;

    public function __construct(StockService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $stocks = $this->service->list($request, auth()->user());
        return StockResource::collection($stocks);
    }

    public function show(Request $request, $id)
    {
        $stock = $this->service->find($id);
        return new StockResource($stock);
    }

    public function reservar(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $stock = $this->service->find($id);
            $stock = $this->service->reservar($stock, $request->input('quantity'), $request->input('observation'));
            
            DB::commit();
            
            return new StockResource($stock);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao reservar quantidade.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function liberar(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $stock = $this->service->find($id);
            $stock = $this->service->liberar($stock, $request->input('quantity'), $request->input('observation'));
            
            DB::commit();
            
            return new StockResource($stock);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao liberar quantidade.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function darSaida(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $stock = $this->service->find($id);
            $stock = $this->service->darSaida($stock, $request->input('quantity'), $request->input('observation'));
            
            DB::commit();
            
            return new StockResource($stock);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao dar saída do produto.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function transferirESair(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $stock = $this->service->find($id);
            $result = $this->service->transferirESair(
                $stock,
                $request->input('to_location_id'),
                $request->input('quantity'),
                $request->input('observation')
            );
            
            DB::commit();
            
            return response()->json([
                'message' => 'Transferência e saída realizadas com sucesso.',
                'data' => [
                    'stock_origem' => new StockResource($result['stock_origem']),
                    'stock_destino' => new StockResource($result['stock_destino']),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao transferir e dar saída.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function darSaidaECriarAtivo(Request $request, $id)
    {
        try {
            $stock = $this->service->find($id);
            $result = $this->service->darSaidaECriarAtivo(
                $stock,
                $request->input('quantity'),
                $request->input('asset_data', []),
                $request->input('observation')
            );
            
            return response()->json([
                'message' => 'Saída realizada e ativo criado com sucesso.',
                'data' => [
                    'stock' => new StockResource($result['stock']),
                    'asset' => $result['asset'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao dar saída e criar ativo.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function cancelarReserva(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $stock = $this->service->find($id);
            $stock = $this->service->cancelarReserva(
                $stock,
                $request->input('quantity'),
                $request->input('motivo')
            );
            
            DB::commit();
            
            return new StockResource($stock);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao cancelar reserva.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Buscar reservas agrupadas por solicitante
     */
    public function reservasPorSolicitante(Request $request)
    {
        try {
            $reservas = $this->service->listReservasPorSolicitante($request, auth()->user());
            
            return response()->json([
                'data' => $reservas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar reservas por solicitante.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Dar saída múltipla e gerar termo de responsabilidade
     */
    public function darSaidaMultipla(Request $request)
    {
        try {
            $items = $request->input('items', []);
            
            if (empty($items)) {
                return response()->json([
                    'message' => 'Nenhum item selecionado para dar saída.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->service->darSaidaMultipla($items);
            
            return response()->json([
                'message' => 'Saídas realizadas com sucesso.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao dar saída múltipla.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Gerar termo de responsabilidade PDF
     * Este método apenas gera o PDF usando os dados já processados
     * As saídas devem ser processadas antes via darSaidaMultipla
     */
    public function gerarTermoResponsabilidade(Request $request)
    {
        try {
            $items = $request->input('items', []);
            
            if (empty($items)) {
                return response()->json([
                    'message' => 'Nenhum item selecionado.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Buscar dados para PDF sem processar saídas novamente
            $result = $this->service->buscarDadosTermoResponsabilidade($items);
            
            // Gerar PDF
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            
            $pdf = Pdf::loadView('termo-responsabilidade-estoque', $result);
            $pdf->getDomPDF()->setOptions($options);
            $pdf->setPaper('A4', 'portrait');
            
            $nomeSolicitante = str_replace(' ', '_', $result['solicitante']->nome_completo ?? $result['solicitante']->name ?? 'termo');
            $nomeArquivo = "termo-responsabilidade-{$nomeSolicitante}-" . date('Y-m-d') . '.pdf';
            
            return $pdf->download($nomeArquivo);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao gerar termo de responsabilidade.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}

