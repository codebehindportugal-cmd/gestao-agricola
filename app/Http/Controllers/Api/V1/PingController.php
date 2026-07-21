<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    use RespondeJson;

    public function __invoke(): JsonResponse
    {
        return $this->ok(['mensagem' => 'API v1 autenticada']);
    }
}
