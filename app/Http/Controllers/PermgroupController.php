<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Permgroup;
use App\Models\Permitem;
use App\Models\CustomLog;
use App\Models\User;

use App\Http\Resources\GroupResource;
use App\Http\Resources\ItemsResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class PermgroupController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function index(Request $request){

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: '.auth()->user()->nome_completo.' acessou a tela de Permissões',
            'operation' => 'index'
        ]);

        $user = auth()->user();

        if ($user->login === 'MASTERGERAL') {
            return GroupResource::collection(Permgroup::withCount('users')->where('company_id', $request->header('Company_id'))->get());
        } else {
            return GroupResource::collection(
                Permgroup::withCount('users')
                    ->where('company_id', $request->header('Company_id'))
                    ->where('name', '!=', 'MASTERGERAL') // Excluir o nome "MASTERGERAL"
                    ->get()
            );
        }


    }

    public function insert(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        $dados = $request->all();
        if(!$validator->fails()){

            $dados['company_id'] = $request->header('Company_id');

            $newGroup = Permgroup::create($dados);

            foreach ($dados['permissions'] as $slug) {
                if (!$newGroup->items()->where('slug', $slug)->exists()) {
                    $item = Permitem::where('slug', $slug)->first();
                    $newGroup->items()->attach($item);
                }
            }

            return $array;

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => $validator->errors()->first()
            ], Response::HTTP_FORBIDDEN);
        }

    }

    public function update(Request $request, $id){


        DB::beginTransaction();

        try {
            $array = ['error' => ''];

            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'name' => 'required',
            ]);

            $dados = $request->all();
            if(!$validator->fails()){

                $EditGroup = Permgroup::find($id);

                $EditGroup->company_id = $request->header('Company_id');
                $EditGroup->name = $dados['name'];
                $EditGroup->items()->detach();

                $EditGroup->save();

                // Sincroniza os usuários com o grupo de permissões
                $usersData = collect($dados['users'])->pluck('id')->toArray();
                $EditGroup->users()->sync($usersData);

                foreach ($dados['permissions'] as $slug) {
                    // Verifica se o relacionamento já existe para evitar duplicatas
                    if (!$EditGroup->items()->where('slug', $slug)->exists()) {
                        // Adiciona o novo relacionamento
                        $item = Permitem::where('slug', $slug)->first(); // Supondo que você tenha um modelo Item
                        $EditGroup->items()->attach($item);
                    }
                }

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
                "message" => "Erro ao editar permissão.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function id(Request $r, $id){
        $res = new GroupResource(Permgroup::find($id));
        return $res;
    }

    public function getItemsForGroup(Request $r, $id){
        $res = ItemsResource::collection(Permgroup::find($id)->items);
        return $res;
    }

    public function getItemsForGroupUser(Request $r, $id){

        $user = User::find($id);

        $res = ItemsResource::collection(Permgroup::where('company_id', $r->header('Company_id'))->items);
        return $res;
    }

    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = PermGroup::withCount('users')->findOrFail($id);

            if($permGroup->name == "Consultor"){
                return response()->json([
                    "message" => "Permissão não pode ser excluída."
                ], Response::HTTP_FORBIDDEN);
            }

            if ($permGroup->users_count > 0) {
                return response()->json([
                    "message" => "Permissão ainda tem usuários associados."
                ], Response::HTTP_FORBIDDEN);
            }

            $permGroup->items()->detach();

            $permGroup->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou a permissão: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Permissão excluída com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar a permissão: '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir permissão.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
