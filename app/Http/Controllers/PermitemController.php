<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Permitem;

class PermitemController extends Controller
{
    public function index(Request $r){
        $items = Permitem::all();
        
        // Remover duplicatas por slug dentro de cada grupo
        $grouped = $items->groupBy('group')->map(function ($groupItems) {
            $seen = [];
            return $groupItems->filter(function ($item) use (&$seen) {
                $slug = strtolower(trim($item->slug));
                if (isset($seen[$slug])) {
                    return false;
                }
                $seen[$slug] = true;
                return true;
            })->values();
        });
        
        return $grouped->toArray();
    }

    public function id(Request $r, $id){
        $res = Permitem::find($id);
        $res->groups;
        return $res;
    }
}
