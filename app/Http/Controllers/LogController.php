<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Address;
use App\Models\Movimentacaofinanceira;
use App\Models\CustomLog;
use App\Models\User;

use DateTime;
use App\Http\Resources\MovimentacaofinanceiraResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class LogController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function all(Request $request){

        return $this->custom_log->orderBy('created_at', 'desc')->get();

    }
}
