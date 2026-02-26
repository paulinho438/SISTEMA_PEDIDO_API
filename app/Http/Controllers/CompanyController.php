<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use Carbon\Carbon;

use App\Models\Company;
use App\Models\Client;


use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\EmpresaResource;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use DateTime;

class CompanyController extends Controller
{
    public function index(Request $r)
    {
        $companies = Company::all();
        return $companies;
    }
    public function get(Request $request, $id)
    {
        $companies = Company::find($id != 'undefined' ? $id : $request->header('company-id'));
        return $companies;
    }

    public function getId(Request $request, $id)
    {
        $companies = Company::find($id);
        return $companies;
    }

    public function getAll(Request $request)
    {
        $companies = EmpresaResource::collection(Company::all());
        return $companies;
    }

    public function insert(Request $request)
    {
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'company' => 'required',
        ]);

        $dados = $request->all();
        if (!$validator->fails()) {

            // Apenas campos permitidos no model (evita campos extras do front)
            $empresaData = array_intersect_key($dados, array_flip((new Company)->getFillable()));
            $empresa = Company::create($empresaData);

            // Garantir que exista um usuário MASTERGERAL (evita "Attempt to read property 'id' on null")
            $masterGeral = User::firstOrCreate(
                ['login' => 'MASTERGERAL'],
                [
                    'nome_completo' => 'MASTER GERAL',
                    'cpf' => null,
                    'rg' => null,
                    'data_nascimento' => null,
                    'sexo' => null,
                    'telefone_celular' => '(00) 0 0000-0000',
                    'email' => 'mastergeral@sistema.local',
                    'status' => 'A',
                    'status_motivo' => null,
                    'tentativas' => 0,
                    'password' => bcrypt('1234'),
                ]
            );

            // Tabelas de módulo legado (usuários, permissões) - podem não existir
            $usuario = null;
            if (Schema::hasTable('company_user')) {
                $usuario = User::create([
                    "nome_completo"             => "MASTER" . $empresa->id,
                    "cpf"                       => "MASTER" . $empresa->id,
                    "rg"                        => "MASTER" . $empresa->id,
                    "login"                     => "MASTER" . $empresa->id,
                    "data_nascimento"           => Carbon::now(),
                    "sexo"                      => "M",
                    "telefone_celular"          => "(61) 9 9999-9999",
                    "email"                     => "MASTER" . $empresa->id . "@rjemprestimos.combr",
                    "status"                    => "A",
                    "status_motivo"             => "",
                    "tentativas"                => "0",
                    "password"                  => bcrypt("1234")
                ]);

                DB::table("company_user")->insert([
                    "company_id" => $empresa->id,
                    "user_id"    => $usuario->id,
                ]);
                DB::table("company_user")->insert([
                    "company_id" => $empresa->id,
                    "user_id"    => $masterGeral->id,
                ]);
            }

            // Alguns ambientes não possuem a tabela 'costcenter' (módulo legado).
            // Evitar erro "Nome de objeto inválido".
            if (Schema::hasTable('costcenter')) {
                DB::table('costcenter')->insert([
                    'name' => 'Default',
                    'description' => 'Default',
                    'company_id' => $empresa->id,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }

            // Alguns ambientes não possuem a tabela 'juros' (módulo legado).
            if (Schema::hasTable('juros')) {
                DB::table('juros')->insert([
                    'juros' => 0.3,
                    'company_id' => $empresa->id,
                ]);
            }

            if (Schema::hasTable('permgroups')) {
                $id = DB::table('permgroups')->insertGetId([
                    'name' => 'Super Administrador',
                    'company_id' => $empresa->id,
                ]);

                if (Schema::hasTable('permitems') && Schema::hasTable('permgroup_permitem')) {
                    $permitemIds = DB::table('permitems')->pluck('id')->all();
                    if (!empty($permitemIds)) {
                        $rows = [];
                        foreach ($permitemIds as $permitemId) {
                            $rows[] = [
                                'permgroup_id' => $id,
                                'permitem_id' => (int) $permitemId,
                            ];
                        }
                        foreach (array_chunk($rows, 500) as $chunk) {
                            DB::table('permgroup_permitem')->insert($chunk);
                        }
                    }
                }

                if (Schema::hasTable('permgroup_user') && $usuario) {
                    DB::table("permgroup_user")->insert([
                        "permgroup_id" => $id,
                        "user_id"      => $usuario->id,
                    ]);
                    DB::table("permgroup_user")->insert([
                        "permgroup_id" => $id,
                        "user_id"      => $masterGeral->id,
                    ]);
                }

                DB::table("permgroups")->insert(["name" => "Administrador", "company_id" => $empresa->id]);
                DB::table("permgroups")->insert(["name" => "Gerente", "company_id" => $empresa->id]);
                DB::table("permgroups")->insert(["name" => "Operador", "company_id" => $empresa->id]);
                DB::table("permgroups")->insert(["name" => "Consultor", "company_id" => $empresa->id]);
            }



            // Tabelas de módulos legados (podem não existir no Sistema de Pedidos)
            if (Schema::hasTable('bancos')) {
                DB::table('bancos')->insert([
                    'name' => 'Banco ITAU',
                    'agencia' => '1234-1',
                    'conta' => '1234-2',
                    'saldo' => 10000,
                    'company_id' => $empresa->id,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }
            if (Schema::hasTable('categories')) {
                DB::table('categories')->insert([
                    'name' => 'PIX',
                    'description' => 'Pagamento Pix',
                    'company_id' => $empresa->id,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'standard' => true,
                ]);
            }
            if (Schema::hasTable('clients')) {
                DB::table('clients')->insert([
                    'nome_completo' => 'Paulo Henrique',
                    'cpf' => '055.463.561-54',
                    'rg' => '2.834.868',
                    'data_nascimento' => '1994-12-09',
                    'sexo' => 'M',
                    'telefone_celular_1' => '(61) 9330-5267',
                    'telefone_celular_2' => '(61) 9330-5268',
                    'email' => 'paulo.peixoto@gmail.com',
                    'limit' => 1000,
                    'company_id' => $empresa->id,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'password' => '1234',
                ]);
            }






            return $array;
        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => ""
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function update(Request $request, $id)
    {

        try {
            $array = ['error' => ''];

            $user = auth()->user();

            $dados = $request->all();

            $EditCompany = Company::find($id);

            if (!$EditCompany) {
                return response()->json([
                    "message" => "Empresa não encontrada.",
                    "error" => "Company not found"
                ], Response::HTTP_NOT_FOUND);
            }

            // Apenas campos permitidos no model (evita campos extras do front)
            $fillable = (new Company)->getFillable();
            $updateData = array_intersect_key($dados, array_flip($fillable));

            // Normalizar ativo (front pode enviar 0, 1, "0", "1")
            if (array_key_exists('ativo', $dados)) {
                $updateData['ativo'] = $dados['ativo'] === 1 || $dados['ativo'] === '1' || $dados['ativo'] === true ? 1 : 0;
            }
            // plano_id pode ser null se não selecionado
            if (array_key_exists('plano_id', $dados)) {
                $updateData['plano_id'] = !empty($dados['plano_id']) ? (int) $dados['plano_id'] : null;
            }

            $EditCompany->fill($updateData);
            $EditCompany->save();

            $array['data'] = $EditCompany;

            return $array;
        } catch (\Exception $e) {

            return response()->json([
                "message" => "Erro ao editar empresa.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function destroy($id)
    {
        try {
            $company = Company::find($id);

            if (!$company) {
                return response()->json([
                    "message" => "Empresa não encontrada.",
                    "error" => "Company not found"
                ], Response::HTTP_NOT_FOUND);
            }

            DB::beginTransaction();

            // Remover vínculos antes de excluir (evita erro de FK)
            if (Schema::hasTable('company_user')) {
                DB::table('company_user')->where('company_id', $id)->delete();
            }
            if (Schema::hasTable('permgroups')) {
                $permgroupIds = DB::table('permgroups')->where('company_id', $id)->pluck('id');
                if ($permgroupIds->isNotEmpty()) {
                    if (Schema::hasTable('permgroup_user')) {
                        DB::table('permgroup_user')->whereIn('permgroup_id', $permgroupIds)->delete();
                    }
                    if (Schema::hasTable('permgroup_permitem')) {
                        DB::table('permgroup_permitem')->whereIn('permgroup_id', $permgroupIds)->delete();
                    }
                    DB::table('permgroups')->where('company_id', $id)->delete();
                }
            }
            if (Schema::hasTable('costcenter')) {
                DB::table('costcenter')->where('company_id', $id)->delete();
            }
            if (Schema::hasTable('juros')) {
                DB::table('juros')->where('company_id', $id)->delete();
            }
            if (Schema::hasTable('bancos')) {
                DB::table('bancos')->where('company_id', $id)->delete();
            }
            if (Schema::hasTable('categories')) {
                DB::table('categories')->where('company_id', $id)->delete();
            }
            if (Schema::hasTable('clients')) {
                DB::table('clients')->where('company_id', $id)->delete();
            }

            $company->delete();

            DB::commit();

            return response()->json([
                "message" => "Empresa excluída com sucesso."
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "message" => "Erro ao excluir empresa.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function getEnvioAutomaticoRenovacao(Request $request)
    {
        $company = Company::find($request->header('company-id'));

        $company->envio_automatico_renovacao = (bool) $company->envio_automatico_renovacao;

        return $company;
    }

    public function getMensagemAudioAutomatico(Request $request)
    {
        $company = Company::find($request->header('company-id'));

        $company->mensagem_audio = (bool) $company->mensagem_audio;

        return $company;
    }

    public function alterEnvioAutomaticoRenovacao(Request $request)
    {
        $company = Company::find($request->header('company-id'));
        $company->envio_automatico_renovacao = !$company->envio_automatico_renovacao;
        $company->save();

        return $company;
    }

    public function alterMensagemAudioAutomatico(Request $request)
    {
        $company = Company::find($request->header('company-id'));
        $company->mensagem_audio = !$company->mensagem_audio;
        $company->save();

        return $company;
    }

    public function testarAutomacaoRenovacao() {
        // Buscar clientes e seus empréstimos
        $clients = Client::whereDoesntHave('emprestimos', function ($query) {
            $query->whereHas('parcelas', function ($query) {
                $query->whereNull('dt_baixa'); // Filtra empréstimos com parcelas pendentes
            });
        })
            ->with(['emprestimos' => function ($query) {
                $query->whereDoesntHave('parcelas', function ($query) {
                    $query->whereNull('dt_baixa'); // Carrega apenas empréstimos sem parcelas pendentes
                });
            }])
            ->get();



        foreach ($clients as $client) {
            if($client->emprestimos){
                foreach ($client->emprestimos as $emprestimo) {
                    if ($client->company->envio_automatico_renovacao == 1 && $emprestimo->mensagem_renovacao == 0) {
                        if ($emprestimo->count_late_parcels <= 2) {
                            // $this->enviarMensagem($client, 'Olá ' . $client->nome_completo . ', estamos entrando em contato para informar sobre seu empréstimo. Temos uma ótima notícia: você possui um valor pré-aprovado de R$ ' . ($emprestimo->valor + 100) . ' Gostaria de contratar?');
                        } elseif ($emprestimo->count_late_parcels >= 3 && $emprestimo->count_late_parcels <= 5) {
                            // $this->enviarMensagem($client, 'Olá ' . $client->nome_completo . ', estamos entrando em contato para informar sobre seu empréstimo. Temos uma ótima notícia: você possui um valor pré-aprovado de R$ ' . ($emprestimo->valor) . ' Gostaria de contratar?');
                        } elseif ($emprestimo->count_late_parcels >= 6) {
                            // $this->enviarMensagem($client, 'Olá ' . $client->nome_completo . ', estamos entrando em contato para informar sobre seu empréstimo. Temos uma ótima notícia: você possui um valor pré-aprovado de R$ ' . ($emprestimo->valor - 100) . ' Gostaria de contratar?');
                        }


                    }
                }
            }

        }
    }
}
