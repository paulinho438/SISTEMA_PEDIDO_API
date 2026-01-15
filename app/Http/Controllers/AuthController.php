<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\UsersToken;
use Symfony\Component\HttpFoundation\Response;

use App\Http\Resources\LoginResource;

use App\Models\User;

class AuthController extends Controller
{
    public function unauthorized(){
        return response()->json([
            'error' => 'Não autorizado'
        ], 401);
    }

    public function alterUsuario(Request $request){
        $array = ['error' => ''];

        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'data_nascimento' => 'required',
            'sexo' => 'required',
            'rg' => 'required',
            'telefone_celular' => 'required',
            'pcd' => 'required',
            'password' => 'required'
        ]);

        $dados = $request->all();
        if(!$validator->fails()){
            if($dados['password'] != '******'){
                $dados['password'] = password_hash($dados['password'], PASSWORD_DEFAULT);
            }else{
                $dados['password'] = $user->password;
            }

            $dados['data_nascimento'] = substr($dados['data_nascimento'], 6, 4).'-'.substr($dados['data_nascimento'], 3, 2).'-'.substr($dados['data_nascimento'], 0, 2);
            $EditUser = User::find($user->id);

            $EditUser->nome_social = $dados['nome_social'];
            $EditUser->data_nascimento = $dados['data_nascimento'];
            $EditUser->sexo = $dados['sexo'];
            $EditUser->telefone_celular = $dados['telefone_celular'];
            $EditUser->pcd = $dados['pcd'];
            $EditUser->rg = $dados['rg'];
            $EditUser->pcd_tipo = $dados['pcd_tipo'];
            $EditUser->usar_social = $dados['usar_social'];
            $EditUser->pcd = $dados['pcd'];
            $EditUser->password = $dados['password'];
            $EditUser->save();

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => ""
            ], Response::HTTP_FORBIDDEN);
        }

        return $array;
    }

    public function register(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'nome_completo' => 'required',
            'cpf' => 'required|unique:users,cpf',
            'rg' => 'required|unique:users,rg',
            'data_nascimento' => 'required',
            'sexo' => 'required',
            'telefone_celular' => 'required',
            'email' => 'required|email|unique:users,email',
            'pcd' => 'required',
            'password' => 'required'
        ]);

        $dados = $request->all();
        if(!$validator->fails()){

            $dados['password'] = password_hash($dados['password'], PASSWORD_DEFAULT);
            $dados['status'] = 'I';
            $dados['data_nascimento'] = substr($dados['data_nascimento'], 6, 4).'-'.substr($dados['data_nascimento'], 3, 2).'-'.substr($dados['data_nascimento'], 0, 2);
            $newUser = User::create($dados);

            $token = Auth::attempt([
                'cpf' => $request->cpf,
                'password' => $request->password
            ]);


            if(!$token){
                $array['error'] = 'Ocorreu um erro.';
                return $array;
            }

            // $array['token'] = $token;
            $array['user'] = auth()->user();

            $token = md5(time().rand(0, 99999).rand(0, 999999));
            $newUsersToken = new UsersToken;
            $newUsersToken->user_id = $array['user']->id;
            $newUsersToken->hash = $token;
            $newUsersToken->expirado_em = date('Y-m-d H:i', strtotime('+2 months'));
            $newUsersToken->save();

            $link = url('ativarconta?token='.$token);

            $dados = new \stdClass();
            $dados->nomeUsuario = $array['user']->nome_completo;
            $dados->email = $array['user']->email;
            $dados->mensagem = "Sua conta foi criada com sucesso. Por favor, clique no botão abaixo para ativar sua conta.";
            $dados->link = $link;
            $dados->mensagem_botao = "Ativar Conta";
            $dados->assunto = "Ativar conta APP190";

            \Illuminate\Support\Facades\Mail::send(new \App\Mail\newLaravelTips($dados));

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => ""
            ], Response::HTTP_FORBIDDEN);
        }

        return $array;
    }

    public function login(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'usuario' => 'required',
            'password' => 'required'
        ]);

        if(!$validator->fails()){

            $validTentativas = User::where('login', $request->usuario)->first();

//            if($validTentativas && $validTentativas->tentativas == '5'){
//                return response()->json([
//                    "message" => 'Sua conta está desativada pois ultrapassou 4 tentativas, recupere sua senha para continuar utilizando o aplicativo!'
//                ], Response::HTTP_FORBIDDEN);
//            }
//
//            if(!$validTentativas){
//
//                return response()->json([
//                    "message" => 'E-mail ou CPF não existe!'
//                ], Response::HTTP_FORBIDDEN);
//            }

            $token = Auth::attempt([
                'login' => $request->usuario,
                'password' => $request->password
            ]);

            if(!$token){
                return response()->json([
                    "message" => "Seus dados estão incorretos!"
                ], Response::HTTP_FORBIDDEN);
            }

            $array['token'] = $token;
            $array['user'] = new LoginResource(auth()->user());

            auth()->user()->update(['device_token'=>$request->device_token]);
            auth()->user()->update(['tentativas'  => 0 ]);

            if(auth()->user()->status == 'I'){
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

    public function validateToken(){
        $array = ['error' => ''];

        $array['user'] = auth()->user();
        $array['user']['data_nascimento'] = substr($array['user']['data_nascimento'], 8, 10).'/'.substr($array['user']['data_nascimento'], 5, 2).'/'.substr($array['user']['data_nascimento'], 0, 4);

        return $array;
    }

    public function myInfo(){
        $array = ['error' => ''];

        $array['user'] = auth()->user();
        $array['user']['data_nascimento'] = substr($array['user']['data_nascimento'], 8, 10).'/'.substr($array['user']['data_nascimento'], 5, 2).'/'.substr($array['user']['data_nascimento'], 0, 4);

        return $array;
    }

    public function logout(){
        $array = ['error' => ''];

        auth()->logout();

        return $array;
    }

    public function esqueci(Request $request){
        $array = ['error'=>'', 'erro' => ''];

        if($request->input('token')){
            $has = false;


            $userToken = UsersToken::where('hash', $request->input('token'))->first();
            if($userToken){
                $has = true;
            }

            $userToken = UsersToken::where('hash', $request->input('token'))
                                    ->where('used', 0)
                                    ->whereDate('expirado_em', '>', NOW())->first();

            if(!$userToken){
                if($has){
                    $array['error'] = 'Sua senha foi alterada com sucesso e este token acaba de ser inativado!';
                }else{
                    $array['error'] = 'Token inválido!';
                }

            }
            $array['token'] = $request->input('token');
        }
        return view('esqueci', $array);
    }

    public function ativarconta(Request $request){
        $array = ['error'=>'', 'erro' => ''];

        if($request->input('token')){
            $userToken = UsersToken::where('hash', $request->input('token'))
                                    ->where('used', 0)
                                    ->whereDate('expirado_em', '>', NOW())->first();
            if($userToken){
                $userToken->used = 1;
                $userToken->save();

                $user = User::find($userToken->user_id);
                $user->status = 'A';
                $user->tentativas = 0;
                $user->save();

                $array['error'] = 'Conta ativada com sucesso !';
            }else{
                $array['error'] = 'Este Token já foi usado ou está inativo!';
            }



        }
        return view('ativarconta', $array);
    }

    public function esqueciAction(Request $request){
        $array = ['error'=>''];
        $array['token'] = $request->input('token');
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'senha' => 'required',
            'confirmar_senha' => 'required_with:senha|same:senha'
        ]);

        if(!$validator->fails()) {
            $userToken = UsersToken::where('hash', $request->input('token'))
                                    ->where('used', 0)
                                    ->whereDate('expirado_em', '>', NOW())->first();
            if(!$userToken){
                $array['error'] = 'Token inválido ou usado!';
                return view('esqueci', $array);
            }

                $userToken->used = 1;
                $userToken->save();

                $user = User::find($userToken->user_id);
                $user->password = password_hash($request->input('senha'), PASSWORD_DEFAULT);
                $user->status = 'A';
                $user->tentativas = 0;
                $user->save();

                $array['error'] = 'Senha alterada com sucesso !';

        }else{
            return redirect('/esqueciminhasenha?token='.$request->input('token'))->withErrors($validator);
        }


        return redirect('/esqueciminhasenha?token='.$request->input('token'))->withErrors($validator);
    }

    public function esquecisenha(Request $request){
        $array = ['error' => ''];

        $user = User::where('cpf', $request->input('cpf'))->first();
        if($user){
            $token = md5(time().rand(0, 99999).rand(0, 999999));
            $newUsersToken = new UsersToken;
            $newUsersToken->user_id = $user->id;
            $newUsersToken->hash = $token;
            $newUsersToken->expirado_em = date('Y-m-d H:i', strtotime('+2 months'));
            $newUsersToken->save();

            $link = url('esqueciminhasenha?token='.$token);

            $dados = new \stdClass();
            $dados->nomeUsuario = $user->nome_completo;
            $dados->email = $user->email;
            $dados->mensagem = "Você está tentando redefinir sua senha. Por favor, clique no botão abaixo para redefinir.";
            $dados->link = $link;
            $dados->mensagem_botao = "Redefinir Senha";
            $dados->assunto = "Redefinir Senha APP190";

            \Illuminate\Support\Facades\Mail::send(new \App\Mail\newLaravelTips($dados));


            $array['result'] = $user->email;
        }else{
            $array['error'] = 'Não existe nenhum usuário cadastrado com este CPF!';
        }

        return $array;
    }

    public function sendnotific() {

        $firebaseToken = User::whereNotNull('device_token')->pluck('device_token')->all();

        $SERVER_API_KEY = 'AAAA07zCmac:APA91bHOO6EsTLfsGCYquPhqs4U6e5P-E_jx7wJtUQmPpMqX2UIKl402NCuQta6zYLFaQR4Cj9iUMQXt9yc4wPh8Q6En17ZJCj61s9LGgNQoHSUByCFEUbVm9Fb92DvhjyivBGbVc3L_';

        $data = [
            "registration_ids" => $firebaseToken,
            "notification" => [
                "title" => 'Atenção',
                "body" => 'Teste de Envio de Notificações HOMOLOG!',
            ],
            "priority" => 'high'
        ];

        $dataString = json_encode($data);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);

        dd($response);
    }
}
