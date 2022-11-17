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

            return;
        }

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUrl,
        ];
        $body = $this->streamFactory->createStream(json_encode($data));
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
        $body = $this->streamFactory->createStream(json_encode($data));
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

    public function get(string $uri, array $parameters = []): array
    {
        $fullUrl = self::API_URL . '/' . $uri;
        if (!empty($parameters)) {
            $fullUrl .= '?' . http_build_query($parameters);
        }

        $request = $this
            ->requestFactory
            ->createRequest('GET', $fullUrl)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(
                'Authorization',
                $this->tokenStorage->getTokenType() . ' ' . $this->getAccessToken()
            );

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new TeamleaderException(
                'Could not get data from Teamleader. Got response: ' . $response->getBody()->getContents()
            );
        }

        try {
            $decodedResponse = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TeamleaderException(
                'Could not get data from Teamleader, json decode failed. Got response: '
                . $response->getBody()->getContents()
            );
        }

        return $decodedResponse['data'];
    }

    public function post(string $uri, array $parameters = []): ?array
    {
        $body = $this->streamFactory->createStream(json_encode($parameters));
        $request = $this
            ->requestFactory
            ->createRequest('POST', self::API_URL . '/' . $uri)
            ->withBody($body)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(
                'Authorization',
                $this->tokenStorage->getTokenType() . ' ' . $this->getAccessToken()
            );

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
}
