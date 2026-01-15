<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Resources\UserResource;

use App\Models\User;

class UserController extends Controller
{
    public function index(Request $r){
        return UserResource::collection(User::all());
    }

    public function id(Request $r, $id){
        // return User::where('id', $id)->where(this->group->name, 'Super')->get();
        return User::whereHas('groups', function ($query) {
            $query->where('name', 'Consultor');
        })
        ->whereHas('companies', function ($query) {
            $query->where('id', 3);
        })
        ->get();
    }

}
