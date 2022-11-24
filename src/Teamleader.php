<?php

namespace Sumocoders\TeamleaderOauth2;

use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sumocoders\TeamleaderOauth2\Exception\TeamleaderException;
use Sumocoders\TeamleaderOauth2\Storage\TokenStorageInterface;

final class Teamleader
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private TokenStorageInterface $tokenStorage;
    private const API_URL = 'https://api.focus.teamleader.eu';
    private const AUTHORIZATION_URL = 'https://focus.teamleader.eu/oauth2/authorize';
    private const TOKEN_URL = 'https://focus.teamleader.eu/oauth2/access_token';
    private string $clientId;
    private string $clientSecret;

    public function __construct(
        string $clientId,
        string $clientSecret,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        TokenStorageInterface $tokenStorage
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->tokenStorage = $tokenStorage;
    }

    public function acquireAccessToken(string $redirectUrl, ?string $code = null): void
    {
        if (!$code) {
            $this->redirectToAuthorizationPage($redirectUrl);
        }

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUrl,
        ];

        $encodedData = json_encode($data);
        if (!$encodedData) {
            throw new TeamleaderException(
                'Could not encode data.'
            );
        }

        $body = $this->streamFactory->createStream($encodedData);
        $request = $this
            ->requestFactory
            ->createRequest('POST', self::TOKEN_URL)
            ->withBody($body)
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new TeamleaderException('Could not acquire access token');
        }

        try {
            $tokens = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TeamleaderException(
                'Could not acquire access token, json decode failed. Got response: '
                . $response->getBody()->getContents()
            );
        }

        $this->tokenStorage->storeTokens($tokens);
    }

    public function acquireRefreshToken(): void
    {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->tokenStorage->getRefreshToken(),
            'grant_type' => 'refresh_token',
        ];

        $encodedData = json_encode($data);
        if (!$encodedData) {
            throw new TeamleaderException(
                'Could not encode data.'
            );
        }

        $body = $this->streamFactory->createStream($encodedData);
        $request = $this
            ->requestFactory
            ->createRequest('POST', self::TOKEN_URL)
            ->withBody($body)
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new TeamleaderException('Could not acquire refresh token');
        }

        try {
            $tokens = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TeamleaderException(
                'Could not acquire refresh token, json decode failed. Got response: '
                . $response->getBody()->getContents()
            );
        }

        $this->tokenStorage->storeTokens($tokens);
    }

    private function redirectToAuthorizationPage(string $redirectUrl): void
    {
        $parameters = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUrl,
            'response_type' => 'code',
        ];

        $url = self::AUTHORIZATION_URL . '?' . http_build_query($parameters);

        header('Location: ' . $url);
        exit;
    }

    private function getAccessToken(): ?string
    {
        $accesstoken = $this->tokenStorage->getAccessToken();
        if ($accesstoken && !$this->tokenStorage->isExpired()) {
            return $accesstoken;
        }

        $refreshToken = $this->tokenStorage->getRefreshToken();
        if ($refreshToken) {
            $this->acquireRefreshToken();

            return $this->getAccessToken();
        }

        return null;
    }

    /**
     * @param array<mixed> $parameters
     * @return array<mixed>
     */
    private function doRequest(string $method, string $uri, array $parameters = []): ?array
    {
        $url = self::API_URL . '/' . $uri;
        if ($method === 'GET' && !empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        $request = $this
            ->requestFactory
            ->createRequest($method, $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(
                'Authorization',
                sprintf(
                    '%s %s',
                    $this->tokenStorage->getTokenType(),
                    $this->getAccessToken()
                ),
            );

        if ($method === 'POST') {
            $encodedParameters = json_encode($parameters);
            if (!$encodedParameters) {
                throw new TeamleaderException(
                    'Could not encode parameters.'
                );
            }

            $body = $this->streamFactory->createStream($encodedParameters);
            $request = $request->withBody($body);
        }

        $response = $this->client->sendRequest($request);

        if (!in_array($response->getStatusCode(), [200, 201, 204])) {
            throw new TeamleaderException(
                'Could not get data from Teamleader. Got response: ' . $response->getBody()->getContents()
            );
        }

        $responseContent = $response->getBody()->getContents();
        if ($responseContent === '') {
            return null;
        }

        try {
            $decodedResponse = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TeamleaderException(
                'Could not get data from Teamleader, json decode failed. Got response: '
                . $response->getBody()->getContents()
            );
        }

        return $decodedResponse['data'];
    }

    /**
     * @param array<mixed> $parameters
     * @return array<mixed>
     */
    public function get(string $uri, array $parameters = []): array
    {
        return $this->doRequest('GET', $uri, $parameters);
    }

    /**
     * @param array<mixed> $parameters
     * @return array<mixed>
     */
    public function post(string $uri, array $parameters = []): ?array
    {
        return $this->doRequest('POST', $uri, $parameters);
    }
}
