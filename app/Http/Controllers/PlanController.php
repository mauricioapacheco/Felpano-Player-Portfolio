<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function confirmPix(Request $request)
    {
        return response()->json([
            'message' => 'Pagamentos desabilitados nesta versão pública.'
        ], 403);
    }
}
