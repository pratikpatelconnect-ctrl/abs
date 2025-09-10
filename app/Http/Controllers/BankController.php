<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BankController extends Controller
{
    function index(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Bank list',
            'data' => [

            ]
        ]);
    }
}
