<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use App\Models\Permgroup;
use Illuminate\Support\Facades\DB;

class ListUsersAndPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:list-permissions {--company= : Filtrar por ID da empresa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista todos os usuários cadastrados e suas permissões por empresa';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $companyId = $this->option('company');

        $this->info('=== LISTAGEM DE USUÁRIOS E PERMISSÕES ===');
        $this->newLine();

        // Buscar usuários
        $usersQuery = User::with(['companies', 'groups.items']);
        
        if ($companyId) {
            $usersQuery->whereHas('companies', function ($query) use ($companyId) {
                $query->where('companies.id', $companyId);
            });
        }

        $users = $usersQuery->get();

        if ($users->isEmpty()) {
            $this->warn('Nenhum usuário encontrado.');
            return Command::SUCCESS;
        }

        $this->info("Total de usuários: {$users->count()}");
        $this->newLine();

        foreach ($users as $index => $user) {
            $this->displayUser($user, $index + 1, $users->count());
            $this->newLine();
        }

        // Resumo de permissões
        $this->displayPermissionsSummary();

        return Command::SUCCESS;
    }

    /**
     * Exibe informações de um usuário
     */
    private function displayUser(User $user, int $current, int $total)
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Usuário #{$current}/{$total} - ID: {$user->id}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $this->line("  Nome:      {$user->nome_completo}");
        $this->line("  Login:     {$user->login}");
        $this->line("  Email:     " . ($user->email ?? 'N/A'));
        $this->line("  Status:    " . ($user->status ?? 'N/A'));

        // Empresas
        $companies = $user->companies;
        if ($companies->isNotEmpty()) {
            $this->newLine();
            $this->line("  Empresas:");
            foreach ($companies as $company) {
                $this->line("    • ID {$company->id}: {$company->company}");
            }
        } else {
            $this->newLine();
            $this->warn("  Empresas: Nenhuma empresa associada");
        }

        // Grupos de permissão por empresa
        $this->newLine();
        $groupsByCompany = $user->groups->groupBy('company_id');
        
        if ($groupsByCompany->isNotEmpty()) {
            $this->line("  Permissões por Empresa:");
            
            foreach ($groupsByCompany as $companyId => $groups) {
                $company = Company::find($companyId);
                $companyName = $company ? $company->company : "Empresa ID: {$companyId}";
                
                $this->newLine();
                $this->line("    ┌─ Empresa: {$companyName} (ID: {$companyId})");
                
                foreach ($groups as $group) {
                    $this->line("    ├─ Grupo: {$group->name} (ID: {$group->id})");
                    
                    $items = $group->items;
                    if ($items->isNotEmpty()) {
                        $permissions = $items->pluck('slug')->toArray();
                        $permissionsCount = count($permissions);
                        $this->line("    │  Permissões ({$permissionsCount}):");
                        
                        // Agrupar permissões por grupo (campo 'group' do permitem)
                        $itemsByGroup = $items->groupBy('group');
                        
                        foreach ($itemsByGroup as $permissionGroup => $groupItems) {
                            $this->line("    │    • {$permissionGroup}:");
                            foreach ($groupItems as $item) {
                                $this->line("    │      - {$item->slug} ({$item->name})");
                            }
                        }
                    } else {
                        $this->warn("    │  Nenhuma permissão atribuída a este grupo");
                    }
                }
                $this->line("    └─");
            }
        } else {
            $this->warn("  Permissões: Nenhum grupo de permissão associado");
        }
    }

    /**
     * Exibe resumo de todas as permissões disponíveis no sistema
     */
    private function displayPermissionsSummary()
    {
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("RESUMO DE PERMISSÕES DISPONÍVEIS NO SISTEMA");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $permissions = DB::table('permitems')
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        if ($permissions->isEmpty()) {
            $this->warn('Nenhuma permissão cadastrada no sistema.');
            return;
        }

        $permissionsByGroup = $permissions->groupBy('group');

        foreach ($permissionsByGroup as $group => $items) {
            $this->newLine();
            $this->line("  Grupo: {$group} ({$items->count()} permissões)");
            foreach ($items as $item) {
                $this->line("    • {$item->slug} - {$item->name}");
            }
        }

        $this->newLine();
        $this->info("Total de permissões disponíveis: {$permissions->count()}");
    }
}

