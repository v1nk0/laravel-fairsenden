<?php

namespace V1nk0\LaravelFairsenden;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use V1nk0\LaravelFairsenden\Exceptions\ResourceNotFoundException;
use V1nk0\LaravelFairsenden\Exceptions\TokenMissingException;

class Client
{
    public string $baseUrl;

    public string $tokenUrl;

    public function __construct(private string $clientId, private string $clientSecret, public $sandbox = false)
    {
        $this->baseUrl = ($sandbox) ? 'https://api.dev.fairsenden.com' : 'https://api.fairsenden.com';
        $this->tokenUrl = ($sandbox) ? 'https://admin-dev-fairsenden.auth.eu-central-1.amazoncognito.com/oauth2/token' : 'https://api-fairsenden.auth.eu-central-1.amazoncognito.com/oauth2/token';
    }

    /** @throws ResourceNotFoundException|ConnectionException|TokenMissingException */
    public function request(HttpMethod $method, string $path, string|array $data = []): Response
    {
        $url = $this->baseUrl.'/'.$path;

        $token = $this->_getToken();

        if(!$token) {
            throw new TokenMissingException('Could not get a token');
        }

        $payload =json_encode($data);
        $contentType = 'application/json';

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => $contentType,
            'Accept' => 'application/json',
        ];

        try {
            $response = Http::withHeaders($headers)
                ->withBody($payload, $contentType)
                ->timeout(5)
                ->send($method->value, $url);

            if($response->status() === 404) {
                throw new ResourceNotFoundException();
            }
        }
        catch(ResourceNotFoundException $e) {
            throw $e;
        }
        catch(Exception $e) {
            throw new ConnectionException($e->getMessage());
        }

        return $response;
    }

    private function _getToken(): ?string
    {
        $token = cache('fairsenden.token', null);

        if($token) {
            return $token;
        }

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if($response->ok()) {
            $data = $response->json();
            cache()->set('fairsenden.token', $data['access_token'], $data['expires_in']);

            return $data['access_token'];
        }

        return null;
    }
}
