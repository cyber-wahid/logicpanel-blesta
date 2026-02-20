<?php

/**
 * LogicPanel API Client for Blesta Module
 */
class LogicPanelApiClient
{
    private $connectTo;
    private $hostHeader;
    private $port;
    private $apiKey;
    private $secure;
    private $timeout;

    public function __construct(string $connectTo, int $port, string $apiKey, bool $secure = true, string $hostHeader = '', int $timeout = 30)
    {
        $this->connectTo  = rtrim($connectTo, '/');
        $this->hostHeader = $hostHeader ?: $connectTo;
        $this->port       = $port;
        $this->apiKey     = $apiKey;
        $this->secure     = $secure;
        $this->timeout    = $timeout;
    }

    private function baseUrl(): string
    {
        $protocol = $this->secure ? 'https' : 'http';
        return "{$protocol}://{$this->connectTo}:{$this->port}/v1/api";
    }

    public function get(string $endpoint, array $query = []): array
    {
        $url = $this->baseUrl() . $endpoint;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $this->baseUrl() . $endpoint, $data);
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $this->baseUrl() . $endpoint, $data);
    }

    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $this->baseUrl() . $endpoint);
    }

    private function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();

        $headers = [
            'X-API-Key: '     . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'Host: '          . $this->hostHeader,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            return ['success' => false, 'http_code' => 0, 'data' => [], 'error' => "cURL Error ({$errno}): {$error}"];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'http_code' => $httpCode, 'data' => [], 'error' => "Invalid JSON response (HTTP {$httpCode})"];
        }

        return [
            'success'   => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'data'      => $decoded,
            'error'     => $decoded['message'] ?? $decoded['error'] ?? '',
        ];
    }
}
