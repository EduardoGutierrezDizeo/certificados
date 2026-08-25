<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    public function heartbeat(): JsonResponse
    {
        return response()->json(['active' => true]);
    }
}
