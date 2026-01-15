<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Address;
use App\Models\Fornecedor;
use App\Models\CustomLog;
use App\Models\User;
use App\Models\Company;
use App\Services\Protheus\ProtheusDataService;

use DateTime;
use App\Http\Resources\FornecedorResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
class FornecedorController extends Controller
{

    protected $custom_log;
    protected ProtheusDataService $protheusDataService;

    public function __construct(Customlog $custom_log, ProtheusDataService $protheusDataService){
        $this->custom_log = $custom_log;
        $this->protheusDataService = $protheusDataService;
    }

    public function id(Request $r, $id){
        return new FornecedorResource(Fornecedor::find($id));
    }

    public function all(Request $request){

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: '.auth()->user()->nome_completo.' acessou a tela de Fornecedores',
            'operation' => 'index'
        ]);

        return FornecedorResource::collection(Fornecedor::where('company_id', $request->header('company-id'))->get());
    }

    /**
     * Buscar informações da associação Protheus para fornecedores da empresa
     * Retorna o código da tabela Protheus que deve ser usada para buscar fornecedores
     */
    public function getProtheusAssociation(Request $request)
    {
        $companyId = $request->header('company-id');
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'message' => 'Empresa não encontrada',
                'error' => 'Empresa não encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        $association = $company->getFornecedoresProtheusAssociation();

        if (!$association) {
            return response()->json([
                'message' => 'Empresa não possui associação com tabela de fornecedores do Protheus',
                'data' => null
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => 'Associação encontrada',
            'data' => [
                'tabela_protheus' => $association->tabela_protheus,
                'descricao' => $association->descricao,
                'company_id' => $association->company_id,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Listar fornecedores do Protheus usando a associação da empresa
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listFromProtheus(Request $request)
    {
        try {
            $companyId = $request->header('company-id');
            
            if (!$companyId) {
                return response()->json([
                    'message' => 'ID da empresa não informado',
                    'error' => 'Header company-id é obrigatório'
                ], Response::HTTP_BAD_REQUEST);
            }

            $company = Company::find($companyId);

            if (!$company) {
                return response()->json([
                    'message' => 'Empresa não encontrada',
                    'error' => 'Empresa não encontrada'
                ], Response::HTTP_NOT_FOUND);
            }

            // Verificar se a empresa tem associação com Protheus
            if (!$company->hasFornecedoresProtheus()) {
                return response()->json([
                    'message' => 'Empresa não possui associação com tabela de fornecedores do Protheus',
                    'data' => [],
                    'tabela_protheus' => null
                ], Response::HTTP_OK);
            }

            // Obter a associação completa
            $association = $company->getFornecedoresProtheusAssociation();

            // Log de acesso
            // $this->custom_log->create([
            //     'user_id' => auth()->user()->id,
            //     'content' => 'O usuário: ' . auth()->user()->nome_completo . ' listou fornecedores do Protheus (tabela: ' . $tabelaProtheus . ')',
            //     'operation' => 'list_protheus'
            // ]);

            // Garantir que a conexão com o Protheus esteja configurada
            if (!config('database.connections.protheus')) {
                return response()->json([
                    'message' => 'Conexão com o banco Protheus não configurada',
                    'error' => 'Adicione a conexão "protheus" em config/database.php e configure as variáveis PROTHEUS_* no .env',
                    'steps' => [
                        'Adicionar no arquivo config/database.php em connections o array "protheus" (ex: driver sqlsrv ou mysql)',
                        'No arquivo .env declarar PROTHEUS_DB_* (host, database, username, password, port)',
                        'Executar php artisan config:clear para aplicar as alterações'
                    ]
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            try {
                $fornecedores = $this->protheusDataService->paginate(
                    $request,
                    (string) $association->tabela_protheus,
                    ['A2_COD', 'A2_NOME', 'A2_CGC', 'A2_END', 'A2_MUN', 'A2_CEP'],
                    [],
                    'A2_NOME'
                );
            } catch (ValidationException $validationException) {
                return response()->json([
                    'message' => 'Requisição inválida para o Protheus',
                    'errors' => $validationException->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            } catch (\Throwable $connectionException) {
                Log::error('Falha ao conectar no Protheus: ' . $connectionException->getMessage());

                return response()->json([
                    'message' => 'Não foi possível conectar ao banco Protheus',
                    'error' => $connectionException->getMessage()
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            return response()->json([
                'message' => 'Fornecedores do Protheus',
                'data' => [
                    'items' => $fornecedores->items(),
                    'tabela_protheus' => $association->tabela_protheus,
                    'descricao' => $association->descricao,
                    'company_id' => $companyId,
                    'company_name' => $company->company,
                ],
                'pagination' => [
                    'current_page' => $fornecedores->currentPage(),
                    'per_page' => $fornecedores->perPage(),
                    'last_page' => $fornecedores->lastPage(),
                    'total' => $fornecedores->total(),
                    'from' => $fornecedores->firstItem(),
                    'to' => $fornecedores->lastItem(),
                    'links' => [
                        'first' => $fornecedores->url(1),
                        'last' => $fornecedores->url($fornecedores->lastPage()),
                        'prev' => $fornecedores->previousPageUrl(),
                        'next' => $fornecedores->nextPageUrl(),
                    ]
                ],
                'info' => [
                    'tabela_utilizada' => $association->tabela_protheus,
                    'nota' => 'Caso deseje filtrar campos específicos do Protheus, ajuste a consulta no controller'
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Erro ao listar fornecedores do Protheus: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Erro ao listar fornecedores do Protheus',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function insert(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'nome_completo' => 'required',
            'cpfcnpj' => 'required',
            'telefone_celular_1' => 'required',
            'telefone_celular_2' => 'required',
            'address' => 'required',
            'cep' => 'required',
            'number' => 'required',
            'neighborhood' => 'required',
            'city' => 'required',
        ]);

        $dados = $request->all();
        if(!$validator->fails()){

            $dados['company_id'] = $request->header('company-id');

            $newGroup = Fornecedor::create($dados);

            return $array;

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => ""
            ], Response::HTTP_FORBIDDEN);
        }

        return $array;
    }

    public function update(Request $request, $id){


        DB::beginTransaction();

        try {
            $array = ['error' => ''];

            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'nome_completo' => 'required',
                'cpfcnpj' => 'required',
                'telefone_celular_1' => 'required',
                'telefone_celular_2' => 'required',
                'address' => 'required',
                'cep' => 'required',
                'number' => 'required',
                'neighborhood' => 'required',
                'city' => 'required',
            ]);

            $dados = $request->all();
            if(!$validator->fails()){

                $EditFornecedor = Fornecedor::find($id);

                $EditFornecedor->nome_completo = $dados['nome_completo'];
                $EditFornecedor->cpfcnpj = $dados['cpfcnpj'];
                $EditFornecedor->telefone_celular_1 = $dados['telefone_celular_1'];
                $EditFornecedor->telefone_celular_2 = $dados['telefone_celular_2'];
                $EditFornecedor->address = $dados['address'];
                $EditFornecedor->cep = $dados['cep'];
                $EditFornecedor->number = $dados['number'];
                $EditFornecedor->complement = $dados['complement'];
                $EditFornecedor->neighborhood = $dados['neighborhood'];
                $EditFornecedor->city = $dados['city'];
                $EditFornecedor->observation = $dados['observation'];
                $EditFornecedor->pix_fornecedor = $dados['pix_fornecedor'];
                $EditFornecedor->save();

            } else {
                return response()->json([
                    "message" => $validator->errors()->first(),
                    "error" => $validator->errors()->first()
                ], Response::HTTP_FORBIDDEN);
            }

            DB::commit();

            return $array;

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                "message" => "Erro ao editar Fornecedor.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }



    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = Fornecedor::findOrFail($id);

            $permGroup->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou o Fornecedor: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Fornecedor excluída com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar o Fornecedor: '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir Fornecedor.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
