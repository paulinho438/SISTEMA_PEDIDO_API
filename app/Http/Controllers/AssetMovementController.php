<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Http\Resources\AssetMovementResource;
use Illuminate\Http\Request;

class AssetMovementController extends Controller
{
    public function index(Request $request, $assetId)
    {
        $asset = Asset::findOrFail($assetId);
        $movements = $asset->movements()->with(['user', 'toBranch', 'toLocation', 'toResponsible'])->orderByDesc('movement_date')->get();
        
        return AssetMovementResource::collection($movements);
    }
}

