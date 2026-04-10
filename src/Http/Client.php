<?php

declare(strict_types=1);

namespace Libxa\Http;

/**
 * LibxaFrame HTTP Client
 * 
 * A simple, framework-native wrapper for CURL to perform external API requests.
 */
class Client
{
    protected array $headers = [];
    protected int $timeout = 30;
    protected ?string $baseUrl = null;

    public function __construct(array $options = [])
    {
        $this->baseUrl = $options['base_url'] ?? null;
        $this->headers = $options['headers'] ?? [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function setBaseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function get(string $url, array $query = []): array
    {
        return $this->request('GET', $url, ['query' => $query]);
    }

    public function post(string $url, array $data = []): array
    {
        return $this->request('POST', $url, ['json' => $data]);
    }

    public function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init();

        $fullUrl = $this->buildUrl($url, $options['query'] ?? []);
        
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $headers = $this->headers;
        if (isset($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $formattedHeaders = [];
        foreach ($headers as $key => $value) {
            $formattedHeaders[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);

        if (isset($options['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
        } elseif (isset($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("HTTP Request failed: $error");
        }

        $decoded = json_decode($response, true);

        return [
            'status' => $status,
            'body' => $decoded !== null ? $decoded : $response,
            'raw' => $response,
            'ok' => $status >= 200 && $status < 300,
        ];
    }

    protected function buildUrl(string $url, array $query): string
    {
        if ($this->baseUrl && !str_starts_with($url, 'http')) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }

    /**
     * Run multiple HTTP requests concurrently.
     * 
     * @param array $requests Array of closures that execute HTTP requests.
     * @return array
     */
    public static function pool(array $requests): array
    {
        // For Fiber environments, we can yield inside a curl_multi_select loop
        // Here we build a pure curl_multi structural concurrency model
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        // Assuming $requests are closures that return configured but un-executed CURL handles or simulated Client configurations.
        // To simplify for the framework scale, we execute standard structural concurrency via fibers:
        return \Libxa\Async\Parallel::run($requests);
    }
}
