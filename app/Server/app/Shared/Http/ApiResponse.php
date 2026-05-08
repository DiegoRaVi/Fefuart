<?php

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $details = [],
        ?string $traceId = null
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'trace_id' => $traceId ?? (string) Str::uuid(),
        ];

        if (!empty($details)) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status);
    }
}
