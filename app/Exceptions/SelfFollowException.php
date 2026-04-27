<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SelfFollowException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'You cannot follow yourself.'], 403);
    }
}
