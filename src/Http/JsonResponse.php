<?php

declare(strict_types=1);

namespace Libxa\Http;

class JsonResponse extends Response
{
    /**
     * Create a new JSON response instance.
     */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $headers = array_merge([
            'Content-Type' => 'application/json',
        ], $headers);

        parent::__construct($status, $headers, $content ?: '');
    }

    public static function success(mixed $data = [], string $message = 'OK', int $status = 200): static
    {
        return new static(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): static
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        return new static($body, $status);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): static
    {
        return new static([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }
}
