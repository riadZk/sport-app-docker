<?php

namespace App\Http\Controllers;
abstract class Controller
{
    protected function jsonResponse($result, $message = '', $status = 200)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'results' => $result,
        ], $status);
    }
}
