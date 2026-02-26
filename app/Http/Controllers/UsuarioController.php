<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Address;
use App\Models\User;
use App\Models\Parcela;
use App\Models\CustomLog;
use App\Models\Permgroup;
use App\Models\UserLocation;
use App\Models\ClientLocation;

use Illuminate\Support\Facades\Storage;


use DateTime;
use App\Http\Resources\UsuarioResource;
use App\Http\Resources\ParcelaResource;
use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsuarioController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log)
    {
        $this->custom_log = $custom_log;
    }

    /**
     * Helper para inserir registros com timestamps como strings (compatível com SQL Server)
     */
    private function insertWithStringTimestamps($table, $data)
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        // Filtrar apenas valores escalares (remover arrays e objetos)
        $filteredData = array_filter($data, function($value) {
            return is_scalar($value) || is_null($value);
        });
        
        $columns = array_keys($filteredData);
        $placeholders = array_fill(0, count($filteredData), '?');
        $values = array_values($filteredData);
        
        // Adicionar campos de data com CONVERT (style fixo, independe de DATEFORMAT)
        $columns[] = 'created_at';
        $placeholders[] = "CONVERT(DATETIME2, ?, 120)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CONVERT(DATETIME2, ?, 120)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [{$table}] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Helper para atualizar modelos com timestamps como strings (compatível com SQL Server)
     */
    private function updateModelWithStringTimestamps($model, array $data)
    {
        // Adicionar updated_at como string
        $data['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Usar DB::statement() para garantir que updated_at seja string
        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            // Campos de data precisam de CONVERT com style fixo
            if ($column === 'updated_at' || $column === 'deleted_at') {
                $placeholders[] = "[{$column}] = CONVERT(DATETIME2, ?, 120)";
            } elseif ($column === 'data_nascimento') {
                $placeholders[] = "[{$column}] = CONVERT(DATE, ?, 23)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }
        
        $values[] = $id; // Para o WHERE
        
        $sql = "UPDATE [{$table}] SET " . implode(', ', $placeholders) . " WHERE [{$idColumn}] = ?";
        
        DB::statement($sql, $values);
        
        // Recarregar o modelo
        $model->refresh();
        
        return $model;
    }

    public function id(Request $r, $id)
    {
        return new UsuarioResource(User::find($id));
    }

    public function parcelasAtrasadas(Request $request)
    {

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: ' . auth()->user()->nome_completo . ' acessou a tela de Usuarios',
            'operation' => 'index'
        ]);

        $companyId = $request->header('company-id');

        return ParcelaResource::collection(Parcela::where('atrasadas', '>', 0)
            ->where('dt_baixa', null)
            ->where('valor_recebido', null)
            ->where(function ($query) {
                $today = Carbon::now()->toDateString();
                $query->whereNull('dt_ult_cobranca')
                    ->orWhereDate('dt_ult_cobranca', '!=', $today);
            })
            ->whereHas('emprestimo', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->get()->unique('emprestimo_id'));
    }

    public function all(Request $request)
    {

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: ' . auth()->user()->nome_completo . ' acessou a tela de Usuarios',
            'operation' => 'index'
        ]);

        $user = auth()->user();

        if ($user->login === 'MASTERGERAL') {
            return UsuarioResource::collection(User::all());
        } else {
            $companyId = $request->header('company-id');

            return UsuarioResource::collection(
                User::whereHas('companies', function ($query) use ($companyId) {
                    $query->where('id', $companyId);
                })
                    ->where('login', '!=', 'MASTERGERAL') // Excluir o login "MASTERGERAL"
                    ->get()
            );
        }
    }

    public function allCompany(Request $request)
    {
        $companyId = $request->header('company-id');

        return UsuarioResource::collection(User::whereHas('companies', function ($query) use ($companyId) {
            $query->where('id', $companyId);
        })->get());
    }

    public function insert(Request $request)
    {
        $array = ['error' => ''];

                $validator = Validator::make($request->all(), [
            'login' => 'required|unique:users,login',
            'nome_completo' => 'required',
            'cpf' => 'nullable|string',
            'rg' => 'nullable|string',
            'data_nascimento' => 'nullable|string',
            'sexo' => 'nullable|string',
            'email' => 'nullable|email',
            'telefone_celular' => 'required',
            'signature' => 'nullable|image|mimes:png|max:2048',
        ]);

        $dados = $request->all();
        if (!$validator->fails()) {
            
            // Processar campos que podem vir como JSON string (do FormData)
            if (isset($dados['empresas']) && is_string($dados['empresas'])) {
                $dados['empresas'] = json_decode($dados['empresas'], true);
            }
            if (isset($dados['permissao']) && is_string($dados['permissao'])) {
                $dados['permissao'] = json_decode($dados['permissao'], true);
            }

            // Processar CPF apenas se fornecido
            if (!empty($dados['cpf'])) {
                $cpf = preg_replace('/[^0-9]/', '', $dados['cpf']);
                $dados['cpf'] = $cpf;
            } else {
                $dados['cpf'] = null;
            }

            // Processar data_nascimento apenas se fornecido
            if (!empty($dados['data_nascimento'])) {
                try {
                    $dados['data_nascimento'] = (DateTime::createFromFormat('d/m/Y', $dados['data_nascimento']))->format('Y-m-d');
                } catch (\Exception $e) {
                    // Se falhar ao parsear, manter como está ou definir como null
                    $dados['data_nascimento'] = null;
                }
            } else {
                $dados['data_nascimento'] = null;
            }

            // Garantir que campos opcionais sejam null se não fornecidos
            if (!isset($dados['rg']) || empty($dados['rg'])) {
                $dados['rg'] = null;
            }
            if (!isset($dados['sexo']) || empty($dados['sexo'])) {
                $dados['sexo'] = null;
            }
            if (!isset($dados['email']) || empty($dados['email'])) {
                $dados['email'] = null;
            }

            $dados['password'] = password_hash($dados['password'], PASSWORD_DEFAULT);

            // Processar upload de assinatura se houver
            if ($request->hasFile('signature')) {
                $dados['signature_path'] = $this->uploadSignatureFile($request->file('signature'), $dados['login'] ?? 'user_' . time());
            }

            // Filtrar apenas campos válidos do modelo User (remover arrays como 'empresas' e 'permissao')
            $fillable = [
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
            
            $userData = array_intersect_key($dados, array_flip($fillable));
            
            // Remover campos null ou arrays (exceto email que pode ser null)
            $userData = array_filter($userData, function($value, $key) {
                if ($key === 'email') {
                    // Email pode ser null, então manter mesmo se null
                    return !is_array($value);
                }
                return !is_array($value) && $value !== null;
            }, ARRAY_FILTER_USE_BOTH);

            // Usar helper para inserir com timestamps como strings
            $userId = $this->insertWithStringTimestamps('users', $userData);
            $newGroup = User::findOrFail($userId);

            $user = auth()->user();

            if ($user->login === 'MASTERGERAL') {

                if ($dados['empresas'] == null) {
                    return response()->json([
                        "message" => "O usuário deve pertencer a pelo menos uma empresa.",
                        "error" => "O usuário deve pertencer a pelo menos uma empresa."
                    ], Response::HTTP_FORBIDDEN);
                }

                $companyIds = array_map(function ($company) {
                    return $company['id'];
                }, $dados['empresas']);

                // Sincronize as empresas com o usuário
                $newGroup->companies()->sync($companyIds);
            } else {

                $companyId = $request->header('company-id');
                $newGroup->companies()->sync([$companyId]);
            }

            if ($dados['permissao']) {
                // Obter o grupo
                $group = Permgroup::findOrFail($dados['permissao']['id']);
                $group->users()->attach($newGroup->id);
            }



            return $array;
        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => $validator->errors()->first()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function informarLocalizacao(Request $request)
    {
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => $validator->errors()->first()
            ], Response::HTTP_FORBIDDEN);
        }

        $dados = $request->all();

        $userLocation = UserLocation::create([
            'user_id' => $dados['user_id'],
            'latitude' => $dados['latitude'],
            'longitude' => $dados['longitude'],
            'company_id' => $request->header('company-id')
        ]);

        $array['data'] = $userLocation;

        return response()->json($array, Response::HTTP_OK);
    }

    public function informarLocalizacaoApp(Request $request)
    {
        $array = ['error' => ''];

        $data = $request->json()->all();

        // Nome do arquivo
        $file = 'webhooklocalizacao.txt';

        // Verifica se o arquivo existe, se não, cria-o
        if (!Storage::exists($file)) {
            Storage::put($file, '');
        }

        // Lê o conteúdo atual do arquivo
        $current = Storage::get($file);

        // Adiciona os novos dados ao conteúdo atual
        $current .= json_encode($data) . PHP_EOL;

        // Salva o conteúdo atualizado no arquivo
        Storage::put($file, $current);

        $dados = $request->all();

        if (isset($dados['tipoUsuario']) && $dados['tipoUsuario'] == 'cliente') {
            $userLocation = ClientLocation::create([
                'client_id' => $dados['user_id'],
                'latitude' => $dados['location']['coords']['latitude'],
                'longitude' => $dados['location']['coords']['longitude'],
                'company_id' => $dados['company_id']
            ]);
        } else {
            $userLocation = UserLocation::create([
                'user_id' => $dados['user_id'],
                'latitude' => $dados['location']['coords']['latitude'],
                'longitude' => $dados['location']['coords']['longitude'],
                'company_id' => $dados['company_id']
            ]);
        }

        $array['data'] = $userLocation;

        return response()->json($array, Response::HTTP_OK);

        // $userLocation = [];

        // foreach($dados['location'] as $location){
        //     $userLocation[] = UserLocation::create([
        //         'user_id' => $dados['user_id'],
        //         'latitude' => $location['coords']['latitude'],
        //         'longitude' => $location['coords']['longitude'],
        //         'company_id' => $dados['company_id']
        //     ]);
        // }

        // $array['data'] = $userLocation;
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $array = ['error' => ''];

            $user = auth()->user();

            // Ler dados - $request->all() deve funcionar para ambos JSON e multipart
            $dados = $request->all();
            
            // Se não conseguiu ler dados (pode acontecer com multipart sem arquivo mal formado)
            // tentar ler campo por campo
            if (empty($dados) || (!isset($dados['nome_completo']) && !isset($dados['email']))) {
                $dados = [];
                $dados['nome_completo'] = $request->input('nome_completo', '');
                $dados['cpf'] = $request->input('cpf', '');
                $dados['rg'] = $request->input('rg', '');
                $dados['data_nascimento'] = $request->input('data_nascimento', '');
                $dados['sexo'] = $request->input('sexo', '');
                $dados['telefone_celular'] = $request->input('telefone_celular', '');
                $dados['email'] = $request->input('email', '');
                $dados['id'] = $request->input('id');
                $dados['login'] = $request->input('login');
                $dados['status'] = $request->input('status');
                $dados['status_motivo'] = $request->input('status_motivo');
                $dados['tentativas'] = $request->input('tentativas');
                $dados['password'] = $request->input('password');
            }
            
            // Processar campos que podem vir como JSON string (do FormData)
            if (isset($dados['empresas'])) {
                if (is_string($dados['empresas'])) {
                    $decoded = json_decode($dados['empresas'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $dados['empresas'] = $decoded;
                    }
                }
            }
            
            if (isset($dados['permissao'])) {
                if (is_string($dados['permissao'])) {
                    $decoded = json_decode($dados['permissao'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $dados['permissao'] = $decoded;
                    }
                }
            }
            
            // Validar os campos
            $validationRules = [
                'nome_completo' => 'required|string',
                'cpf' => 'nullable|string',
                'rg' => 'nullable|string',
                'data_nascimento' => 'nullable|string',
                'sexo' => 'nullable|string',
                'telefone_celular' => 'required|string',
                'email' => 'nullable|email',
            ];
            
            // Adicionar validação do arquivo apenas se for enviado
            if ($request->hasFile('signature')) {
                $validationRules['signature'] = 'nullable|image|mimes:png|max:2048';
            }

            $validator = Validator::make($dados, $validationRules);
            
            if ($validator->fails()) {
                \Log::error('Validação falhou no update:', [
                    'errors' => $validator->errors()->toArray(),
                    'dados_recebidos' => $dados,
                    'has_file' => $request->hasFile('signature'),
                ]);
                
                return response()->json([
                    "message" => $validator->errors()->first(),
                    "error" => $validator->errors()->first(),
                    "errors" => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Processar CPF apenas se fornecido
            $cpf = null;
            if (!empty($dados['cpf'])) {
                $cpf = preg_replace('/[^0-9]/', '', $dados['cpf']);
            }

            $EditUser = User::find($id);

            if (isset($dados['password']) && !empty($dados['password'])) {
                $dados['password'] = password_hash($dados['password'], PASSWORD_DEFAULT);
            } else {
                $dados['password'] = $EditUser->password;
            }

            // Processar data_nascimento apenas se fornecido
            $dataNascimento = null;
            if (!empty($dados['data_nascimento'])) {
                try {
                    $dataNascimento = (DateTime::createFromFormat('d/m/Y', $dados['data_nascimento']))->format('Y-m-d');
                } catch (\Exception $e) {
                    // Se falhar ao parsear, manter como null
                    $dataNascimento = null;
                }
            }

            // Preparar dados para atualização
            $updateData = [
                'nome_completo' => $dados['nome_completo'],
                'cpf' => $cpf,
                'rg' => $dados['rg'] ?? null,
                'data_nascimento' => $dataNascimento,
                'sexo' => $dados['sexo'] ?? null,
                'telefone_celular' => $dados['telefone_celular'],
                'password' => $dados['password'],
            ];
            
            // Incluir email apenas se fornecido e não vazio
            if (isset($dados['email']) && !empty(trim($dados['email']))) {
                $updateData['email'] = trim($dados['email']);
            }
            
            // Processar upload/remoção de assinatura
            if ($request->hasFile('signature') && $request->file('signature')->isValid()) {
                // Deletar assinatura antiga se existir
                if ($EditUser->signature_path && Storage::disk('public')->exists($EditUser->signature_path)) {
                    Storage::disk('public')->delete($EditUser->signature_path);
                }
                $updateData['signature_path'] = $this->uploadSignatureFile($request->file('signature'), $EditUser->login ?? 'user_' . $EditUser->id);
            } elseif (isset($dados['signature_path']) && $dados['signature_path'] === '') {
                // Remover assinatura se signature_path estiver vazio
                if ($EditUser->signature_path && Storage::disk('public')->exists($EditUser->signature_path)) {
                    Storage::disk('public')->delete($EditUser->signature_path);
                }
                $updateData['signature_path'] = null;
            }
            
            // Usar helper para atualizar com timestamps como strings
            $this->updateModelWithStringTimestamps($EditUser, $updateData);

            // Processar empresas se for MASTERGERAL
            if (isset($dados['empresas']) && is_array($dados['empresas']) && !empty($dados['empresas'])) {
                if ($EditUser->login === 'MASTERGERAL') {
                    $companyIds = array_map(function ($company) {
                        return $company['id'];
                    }, $dados['empresas']);

                    // Sincronize as empresas com o usuário
                    $EditUser->companies()->sync($companyIds);
                }
            }

            // Processar permissão se fornecida
            if (isset($dados['permissao']) && !empty($dados['permissao'])) {
                $companyId = $request->header('company-id');
                $isAlmoxarife = isset($dados['permissao']['name'])
                    && trim((string) $dados['permissao']['name']) === 'Almoxarife';

                if ($isAlmoxarife) {
                    // Almoxarife: sempre excluir todas as permissões (grupos) da empresa e salvar só o que o front manda
                    $groupIdsDaEmpresa = Permgroup::where('company_id', $companyId)->pluck('id');
                    if ($groupIdsDaEmpresa->isNotEmpty()) {
                        DB::table('permgroup_user')
                            ->where('user_id', $EditUser->id)
                            ->whereIn('permgroup_id', $groupIdsDaEmpresa)
                            ->delete();
                    }
                } else {
                    // Demais perfis: remover apenas os grupos da empresa atual e anexar o novo
                    $groupsDaEmpresa = $EditUser->groups()->where('company_id', $companyId)->get();
                    foreach ($groupsDaEmpresa as $grupo) {
                        $grupo->users()->detach($EditUser->id);
                    }
                }

                $group = Permgroup::findOrFail($dados['permissao']['id']);
                $group->users()->attach($EditUser->id);
            }

            DB::commit();

            return $array;
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                "message" => "Erro ao editar Usere.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }



    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            // Verificar se o usuário tem empréstimos associados
            // Usar try-catch para caso a tabela não exista
            try {
                $emprestimosCount = DB::table('emprestimos')
                    ->where('user_id', $id)
                    ->count();
                
                if ($emprestimosCount > 0) {
                    DB::rollBack();
                    return response()->json([
                        "message" => "Usuário ainda tem empréstimos associados."
                    ], Response::HTTP_FORBIDDEN);
                }
            } catch (\Exception $e) {
                // Se a tabela não existir, apenas logar e continuar
                \Log::warning('Tabela emprestimos não encontrada ao verificar empréstimos do usuário: ' . $e->getMessage());
            }

            // Soft delete usando update manual para garantir compatibilidade com SQL Server
            $now = now()->format('Y-m-d H:i:s');
            $this->updateModelWithStringTimestamps($user, [
                'deleted_at' => $now,
            ]);

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: ' . auth()->user()->nome_completo . ' deletou o Usere: ' . $id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Usere excluída com sucesso.']);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: ' . auth()->user()->nome_completo . ' tentou deletar o Usere: ' . $id . ' ERROR: ' . $e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir Usere.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Upload de assinatura do usuário
     */
    public function uploadSignature(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'signature' => 'required|image|mimes:png|max:2048', // Apenas PNG, máximo 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Arquivo inválido',
                'error' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = User::findOrFail($id);

            // Deletar assinatura antiga se existir
            if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
                Storage::disk('public')->delete($user->signature_path);
            }

            $signaturePath = $this->uploadSignatureFile($request->file('signature'), $user->login ?? 'user_' . $user->id);

            $user->signature_path = $signaturePath;
            $user->save();

            return response()->json([
                'message' => 'Assinatura enviada com sucesso',
                'data' => [
                    'signature_path' => $signaturePath,
                    'signature_url' => $request->getSchemeAndHttpHost() . '/storage/' . $signaturePath
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao fazer upload da assinatura',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Método privado para fazer upload do arquivo de assinatura
     */
    private function uploadSignatureFile($file, $userIdentifier)
    {
        $filename = 'signature_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $userIdentifier) . '_' . time() . '.png';
        $path = 'signatures/' . $filename;
        
        // Criar diretório se não existir
        Storage::disk('public')->makeDirectory('signatures');
        
        // Salvar arquivo
        $file->storeAs('signatures', $filename, 'public');
        
        return $path;
    }

    /**
     * Buscar assinaturas por perfil (para quadro resumo da cotação)
     * Se quote_id for fornecido, retorna apenas assinaturas de aprovações aprovadas
     */
    /**
     * Retorna a ordem de exibição das assinaturas (diferente da ordem de aprovação)
     */
    private function getSignatureDisplayOrder(): array
    {
        return [
            'COMPRADOR' => 1,
            'GERENTE LOCAL' => 2,
            'GERENTE GERAL' => 3,
            'ENGENHEIRO' => 4,
            'DIRETOR' => 5,
            'PRESIDENTE' => 6,
        ];
    }

    /**
     * Ordena assinaturas pela ordem de exibição
     */
    private function sortSignaturesByDisplayOrder(array $signatures): array
    {
        $displayOrder = $this->getSignatureDisplayOrder();
        
        uksort($signatures, function ($a, $b) use ($displayOrder) {
            $orderA = $displayOrder[$a] ?? 999;
            $orderB = $displayOrder[$b] ?? 999;
            return $orderA <=> $orderB;
        });
        
        return $signatures;
    }

    public function getSignaturesByProfile(Request $request)
    {
        $companyId = (int) $request->header('company-id');
        $quoteId = $request->query('quote_id');
        
        $profiles = [
            'COMPRADOR',
            'GERENTE LOCAL',
            'GERENTE GERAL',
            'ENGENHEIRO',
            'DIRETOR',
            'PRESIDENTE'
        ];

        $signatures = [];

        // Se há quote_id, buscar apenas níveis de aprovação selecionados para esta cotação
        if ($quoteId) {
            $quote = \App\Models\PurchaseQuote::find($quoteId);
            
            if ($quote) {
                // Recarregar a cotação para garantir que as aprovações estejam atualizadas
                $quote->refresh();
                
                // Buscar APENAS os níveis de aprovação que foram SELECIONADOS (required = true) para esta cotação
                // IMPORTANTE: Carregar o relacionamento 'approver' para ter acesso à assinatura
                $requiredApprovals = $quote->approvals()
                    ->with('approver')
                    ->where('required', true)
                    ->get();

                // Mapear nível de aprovação para nome do perfil
                $levelToProfileMap = [
                    'COMPRADOR' => 'COMPRADOR',
                    'GERENTE_LOCAL' => 'GERENTE LOCAL',
                    'ENGENHEIRO' => 'ENGENHEIRO',
                    'GERENTE_GERAL' => 'GERENTE GERAL',
                    'DIRETOR' => 'DIRETOR',
                    'PRESIDENTE' => 'PRESIDENTE',
                ];

                // Para cada nível selecionado, verificar se foi aprovado e adicionar assinatura
                foreach ($requiredApprovals as $approval) {
                    $profileName = $levelToProfileMap[$approval->approval_level] ?? $approval->approval_level;
                    
                    // Se a aprovação foi realmente aprovada, adicionar assinatura
                    if ($approval->approved && $approval->approved_by) {
                        // Se o relacionamento approver não estiver carregado, carregar agora
                        if (!$approval->relationLoaded('approver')) {
                            $approval->load('approver');
                        }
                        
                        $user = $approval->approver;
                        
                        // Se não encontrou o usuário pelo relacionamento, buscar diretamente
                        if (!$user && $approval->approved_by) {
                            $user = \App\Models\User::find($approval->approved_by);
                        }
                        
                        if ($user && $user->signature_path) {
                            $signatures[$profileName] = [
                                'user_id' => $user->id,
                                'user_name' => $user->nome_completo ?? $approval->approved_by_name,
                                'signature_path' => $user->signature_path,
                                'signature_url' => $request->getSchemeAndHttpHost() . '/storage/' . $user->signature_path
                            ];
                        } else {
                            // Nível selecionado mas sem assinatura ainda (aguardando aprovação ou usuário sem assinatura)
                            $signatures[$profileName] = null;
                        }
                    } else {
                        // Nível selecionado mas ainda não aprovado (aguardando aprovação)
                        $signatures[$profileName] = null;
                    }
                }
                
                // Ordenar assinaturas pela ordem de exibição
                $signatures = $this->sortSignaturesByDisplayOrder($signatures);
                
                return response()->json([
                    'data' => $signatures
                ], Response::HTTP_OK);
            }
        }

        // Fallback: buscar por grupo/perfil (método antigo - apenas se não houver quote_id)
        foreach ($profiles as $profileName) {
            // Buscar usuário com esse perfil na empresa
            $user = User::whereHas('companies', function ($query) use ($companyId) {
                $query->where('id', $companyId);
            })
            ->whereHas('groups', function ($query) use ($profileName, $companyId) {
                $query->where(function ($groupQuery) use ($profileName) {
                    $groupQuery->where('name', 'LIKE', "%{$profileName}%")
                               ->orWhere('name', '=', $profileName);
                })
                ->where('company_id', $companyId);
            })
            ->whereNotNull('signature_path')
            ->first();

            if ($user && $user->signature_path) {
                $signatures[$profileName] = [
                    'user_id' => $user->id,
                    'user_name' => $user->nome_completo,
                    'signature_path' => $user->signature_path,
                    'signature_url' => $request->getSchemeAndHttpHost() . '/storage/' . $user->signature_path
                ];
            } else {
                $signatures[$profileName] = null;
            }
        }

        return response()->json([
            'data' => $signatures
        ], Response::HTTP_OK);
    }
}
