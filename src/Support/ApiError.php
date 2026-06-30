<?php

declare(strict_types=1);

namespace Statisty\Support;

final class ApiError
{
    private const MAPPINGS = [
        'invalid_model' => 404,
        'invalid_column' => 400,
        'unauthorized' => 403,
        'invalid_request' => 422,
        'server_error' => 500,
    ];

    public static function response(string $code, ?int $status = null, array $extra = [])
    {
        $status = $status ?? (self::MAPPINGS[$code] ?? 400);

        $payload = array_merge(['error' => $code], $extra);

        return response()->json($payload, $status);
    }

    public static function validation(array $errors)
    {
        return self::response('invalid_request', 422, ['errors' => $errors]);
    }
}
