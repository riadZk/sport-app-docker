<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;



class ApiController extends Controller
{

    protected function jsonResponse($result, $message = '', $status = 200)
    {
        // Delegates to the base Controller implementation which sets the
        // real HTTP status code alongside the {status, message, results}
        // envelope (previously the HTTP status was always 200).
        return parent::jsonResponse($result, $message, $status);
    }
}