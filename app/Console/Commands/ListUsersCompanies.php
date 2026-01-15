<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListUsersCompanies extends Command
{
    protected $signature = 'users:list-companies';
    protected $description = 'Lista todos os usuários e suas empresas associadas';

    public function handle()
    {
        $this->info('=== USUÁRIOS E SUAS EMPRESAS ===');
        $this->newLine();

        $users = DB::table('users')
            ->whereIn('login', ['supervisor', 'comprador', 'gerente_local', 'engenheiro', 'gerente_geral', 'diretor', 'presidente'])
            ->get(['id', 'login', 'nome_completo']);

        foreach ($users as $user) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Usuário: {$user->nome_completo} (Login: {$user->login})");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            $companies = DB::table('company_user')
                ->where('user_id', $user->id)
                ->join('companies', 'company_user.company_id', '=', 'companies.id')
                ->get(['companies.id', 'companies.company']);

            if ($companies->isEmpty()) {
                $this->warn("  Nenhuma empresa associada");
            } else {
                foreach ($companies as $company) {
                    $this->line("  • Empresa ID {$company->id}: {$company->company}");
                }
            }
            $this->newLine();
        }

        // Listar todas as empresas disponíveis
        $this->info('=== EMPRESAS DISPONÍVEIS NO SISTEMA ===');
        $this->newLine();
        $allCompanies = DB::table('companies')->get(['id', 'company']);
        foreach ($allCompanies as $company) {
            $this->line("  ID {$company->id}: {$company->company}");
        }

        return Command::SUCCESS;
    }
}

