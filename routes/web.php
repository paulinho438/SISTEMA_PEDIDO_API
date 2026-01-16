<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

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
