<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Rota de teste de conexão do Laravel
Route::get('/test-connection', function () {
    try {
        // Testar conexão com banco de dados principal
        DB::connection()->getPdo();
        $dbStatus = 'Conectado';
        $dbName = DB::connection()->getDatabaseName();
    } catch (\Exception $e) {
        $dbStatus = 'Erro: ' . $e->getMessage();
        $dbName = 'N/A';
    }
    
    // Testar conexão com Protheus
    $protheusStatus = 'Não configurado';
    $protheusName = 'N/A';
    $protheusError = null;
    $protheusConfig = null;
    
    if (config('database.connections.protheus')) {
        $protheusConfig = config('database.connections.protheus');
        try {
            $protheusConnection = DB::connection('protheus');
            $protheusConnection->getPdo();
            $protheusStatus = 'Conectado';
            $protheusName = $protheusConnection->getDatabaseName();
            
            // Tentar uma query simples para verificar
            $protheusConnection->select('SELECT 1 as test');
        } catch (\Exception $e) {
            $protheusStatus = 'Erro na conexão';
            $protheusError = $e->getMessage();
        }
    }
    
    return response()->json([
        'status' => 'OK',
        'laravel_version' => app()->version(),
        'php_version' => PHP_VERSION,
        'server_time' => now()->format('Y-m-d H:i:s'),
        'timezone' => config('app.timezone'),
        'environment' => config('app.env'),
        'database' => [
            'status' => $dbStatus,
            'name' => $dbName,
            'driver' => config('database.default'),
        ],
        'protheus' => [
            'status' => $protheusStatus,
            'name' => $protheusName,
            'error' => $protheusError,
            'config' => $protheusConfig ? [
                'driver' => $protheusConfig['driver'] ?? 'N/A',
                'host' => $protheusConfig['host'] ?? 'N/A',
                'port' => $protheusConfig['port'] ?? 'N/A',
                'database' => $protheusConfig['database'] ?? 'N/A',
                'username' => $protheusConfig['username'] ?? 'N/A',
                'password_set' => !empty($protheusConfig['password'] ?? ''),
            ] : null,
        ],
        'storage' => [
            'public_exists' => is_dir(storage_path('app/public')),
            'signatures_exists' => is_dir(storage_path('app/public/signatures')),
            'signatures_count' => is_dir(storage_path('app/public/signatures'))
                ? count(glob(storage_path('app/public/signatures/*.png')))
                : 0,
        ],
        'routes' => [
            'web_routes' => count(Route::getRoutes()->getRoutesByMethod()['GET'] ?? []),
        ],
    ], 200);
});

// Rota específica para testar apenas a conexão com Protheus
Route::get('/test-protheus', function () {
    if (!config('database.connections.protheus')) {
        return response()->json([
            'status' => 'error',
            'message' => 'Conexão Protheus não configurada',
            'steps' => [
                'Adicione no arquivo .env as variáveis:',
                'PROTHEUS_DB_CONNECTION=sqlsrv (ou mysql)',
                'PROTHEUS_DB_HOST=seu_host',
                'PROTHEUS_DB_PORT=1433 (ou 3306 para mysql)',
                'PROTHEUS_DB_DATABASE=nome_do_banco',
                'PROTHEUS_DB_USERNAME=usuario',
                'PROTHEUS_DB_PASSWORD=senha',
                '',
                'Execute: php artisan config:clear',
            ],
        ], 200);
    }
    
    $config = config('database.connections.protheus');
    
    try {
        $connection = DB::connection('protheus');
        $connection->getPdo(); // Testar conexão
        
        // Testar query simples
        $result = $connection->select('SELECT 1 as test, GETDATE() as server_time');
        
        // Tentar listar algumas tabelas (se SQL Server)
        $tables = [];
        if ($config['driver'] === 'sqlsrv') {
            try {
                $tablesQuery = $connection->select("SELECT TOP 5 TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                $tables = array_column($tablesQuery, 'TABLE_NAME');
            } catch (\Exception $e) {
                // Ignorar erro ao listar tabelas
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Conexão com Protheus estabelecida com sucesso!',
            'connection' => [
                'driver' => $config['driver'],
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $connection->getDatabaseName(),
                'username' => $config['username'],
            ],
            'test_query' => $result[0] ?? null,
            'sample_tables' => $tables,
            'server_time' => now()->format('Y-m-d H:i:s'),
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Erro ao conectar com Protheus',
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'config' => [
                'driver' => $config['driver'] ?? 'N/A',
                'host' => $config['host'] ?? 'N/A',
                'port' => $config['port'] ?? 'N/A',
                'database' => $config['database'] ?? 'N/A',
                'username' => $config['username'] ?? 'N/A',
            ],
            'troubleshooting' => [
                'Verifique se o servidor está acessível',
                'Verifique se as credenciais estão corretas',
                'Verifique se o banco de dados existe',
                'Para SQL Server, verifique se a porta está correta (padrão: 1433)',
                'Verifique se o driver PDO está instalado (sqlsrv ou mysql)',
            ],
        ], 200);
    }
});

// Rota para servir arquivos de storage (especialmente assinaturas)
// Necessário porque o link simbólico pode não funcionar no Windows
Route::get('/storage/{path}', function ($path) {
    // Decodificar o path caso tenha sido codificado
    $path = urldecode($path);
    
    // Construir o caminho completo do arquivo
    $filePath = storage_path('app/public/' . $path);
    
    // Verificar se o arquivo existe
    if (!file_exists($filePath) || !is_file($filePath)) {
        abort(404, 'Arquivo não encontrado: ' . $path);
    }
    
    // Ler o conteúdo do arquivo
    $file = file_get_contents($filePath);
    
    // Detectar o tipo MIME
    $type = mime_content_type($filePath);
    if (!$type) {
        // Fallback para PNG se não conseguir detectar
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $type = $extension === 'png' ? 'image/png' : 'application/octet-stream';
    }
    
    return Response::make($file, 200, [
        'Content-Type' => $type,
        'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');
