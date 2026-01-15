<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\UsersToken;
use Symfony\Component\HttpFoundation\Response;

use App\Http\Resources\LoginClienteResource;

use App\Models\User;

class AuthClienteController extends Controller
{
    public function unauthorized()
    {
        return response()->json([
            'error' => 'Não autorizado'
        ], 401);
    }
    public function login(Request $request)
    {
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'usuario' => 'required',
            'password' => 'required'
        ]);

        if (!$validator->fails()) {

            $credentials = [
                'usuario' => $request->usuario,
                'password' => $request->password
            ];

            if (!$token = Auth::guard('clientes')->attempt($credentials)) {
                return response()->json([
                    "message" => "Seus dados estão incorretos!"
                ], Response::HTTP_FORBIDDEN);
            }


            $array['token'] = $token;
            $array['user'] = new LoginClienteResource(auth('clientes')->user());


            if (auth('clientes')->user()->status == 'I') {
                return response()->json([
                    "message" => 'Sua conta está desativada, se você acabou de realizar o cadastro acesse seu e-mail e clique no link para ativa-la!'
                ], Response::HTTP_FORBIDDEN);
            }
        } else {

            return response()->json([
                "message" => $validator->errors()->first()
            ], Response::HTTP_FORBIDDEN);
        }

        return $array;
    }

    public function validateToken()
    {
        $array = ['error' => ''];

        $array['user'] = auth()->user();
        $array['user']['data_nascimento'] = substr($array['user']['data_nascimento'], 8, 10) . '/' . substr($array['user']['data_nascimento'], 5, 2) . '/' . substr($array['user']['data_nascimento'], 0, 4);

        return $array;
    }

    public function logout()
    {
        $array = ['error' => ''];

        auth()->logout();

        return $array;
    }
}
