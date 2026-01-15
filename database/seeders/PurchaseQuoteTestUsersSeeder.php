<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PurchaseQuoteTestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $companyId = 1; // GRUPO RIALMA
        $password = Hash::make('1234');
        $now = Carbon::now();

        // Criar grupos de permissão se não existirem
        $groups = [
            'Supervisor de Compras' => null,
            'Comprador' => null,
            'Gerente Local' => null,
            'Engenheiro' => null,
            'Gerente Geral' => null,
            'Diretor' => null,
            'Presidente' => null,
        ];

        foreach ($groups as $groupName => $groupId) {
            $existing = DB::table('permgroups')
                ->where('name', $groupName)
                ->where('company_id', $companyId)
                ->first();

            if (!$existing) {
                $id = DB::table('permgroups')->insertGetId([
                    'name' => $groupName,
                    'company_id' => $companyId,
                ]);
                $groups[$groupName] = $id;
            } else {
                $groups[$groupName] = $existing->id;
            }
        }

        // Criar usuários
        $users = [
            [
                'nome_completo' => 'Supervisor de Compras',
                'login' => 'supervisor',
                'email' => 'supervisor@gruporialma.com.br',
                'cpf' => '11111111111',
                'rg' => '1111111',
            ],
            [
                'nome_completo' => 'Comprador Teste',
                'login' => 'comprador',
                'email' => 'comprador@gruporialma.com.br',
                'cpf' => '22222222222',
                'rg' => '2222222',
            ],
            [
                'nome_completo' => 'Gerente Local Teste',
                'login' => 'gerente_local',
                'email' => 'gerente.local@gruporialma.com.br',
                'cpf' => '33333333333',
                'rg' => '3333333',
            ],
            [
                'nome_completo' => 'Engenheiro Teste',
                'login' => 'engenheiro',
                'email' => 'engenheiro@gruporialma.com.br',
                'cpf' => '44444444444',
                'rg' => '4444444',
            ],
            [
                'nome_completo' => 'Gerente Geral Teste',
                'login' => 'gerente_geral',
                'email' => 'gerente.geral@gruporialma.com.br',
                'cpf' => '55555555555',
                'rg' => '5555555',
            ],
            [
                'nome_completo' => 'Diretor Teste',
                'login' => 'diretor',
                'email' => 'diretor@gruporialma.com.br',
                'cpf' => '66666666666',
                'rg' => '6666666',
            ],
            [
                'nome_completo' => 'Presidente Teste',
                'login' => 'presidente',
                'email' => 'presidente@gruporialma.com.br',
                'cpf' => '77777777777',
                'rg' => '7777777',
            ],
        ];

        $userGroupMap = [
            'supervisor' => 'Supervisor de Compras',
            'comprador' => 'Comprador',
            'gerente_local' => 'Gerente Local',
            'engenheiro' => 'Engenheiro',
            'gerente_geral' => 'Gerente Geral',
            'diretor' => 'Diretor',
            'presidente' => 'Presidente',
        ];

        foreach ($users as $userData) {
            // Verificar se usuário já existe
            $existingUser = DB::table('users')
                ->where('login', $userData['login'])
                ->first();

            if ($existingUser) {
                $userId = $existingUser->id;
                
                // Atualizar senha
                DB::table('users')
                    ->where('id', $userId)
                    ->update(['password' => $password]);
            } else {
                // Criar novo usuário
                $userId = DB::table('users')->insertGetId([
                    'nome_completo' => $userData['nome_completo'],
                    'login' => $userData['login'],
                    'email' => $userData['email'],
                    'cpf' => $userData['cpf'],
                    'rg' => $userData['rg'],
                    'sexo' => 'M',
                    'telefone_celular' => '(61) 99999-9999',
                    'status' => 'A',
                    'status_motivo' => '',
                    'tentativas' => 0,
                    'password' => $password,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Associar usuário à empresa
            $companyUserExists = DB::table('company_user')
                ->where('user_id', $userId)
                ->where('company_id', $companyId)
                ->exists();

            if (!$companyUserExists) {
                DB::table('company_user')->insert([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                ]);
            }

            // Associar usuário ao grupo
            $groupName = $userGroupMap[$userData['login']] ?? null;
            if ($groupName && isset($groups[$groupName])) {
                $groupId = $groups[$groupName];
                
                $permGroupUserExists = DB::table('permgroup_user')
                    ->where('user_id', $userId)
                    ->where('permgroup_id', $groupId)
                    ->exists();

                if (!$permGroupUserExists) {
                    DB::table('permgroup_user')->insert([
                        'permgroup_id' => $groupId,
                        'user_id' => $userId,
                    ]);
                }
            }
        }

        $this->command->info('Usuários de teste criados com sucesso!');
        $this->command->info('Todos os usuários têm a senha: 1234');
        $this->command->info('');
        $this->command->info('Usuários criados:');
        foreach ($users as $user) {
            $this->command->info("  - Login: {$user['login']} | Nome: {$user['nome_completo']}");
        }
    }
}

