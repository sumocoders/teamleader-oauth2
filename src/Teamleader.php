<?php

namespace Sumocoders\TeamleaderOauth2;

use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sumocoders\TeamleaderOauth2\Exception\TeamleaderException;
use Sumocoders\TeamleaderOauth2\Storage\TokenStorageInterface;

final class Teamleader
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private TokenStorageInterface $tokenStorage;
    private string $apiUrl = 'https://api.focus.teamleader.eu';
    private string $authorizationUrl = 'https://focus.teamleader.eu/oauth2/authorize';
    private string $tokenUrl = 'https://focus.teamleader.eu/oauth2/access_token';
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
            ->createRequest('POST', $this->tokenUrl)
            ->withBody($body)
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new TeamleaderException('Could not acquire tokens');
        }

        try {
            $tokens = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TeamleaderException(
                'Could not acquire tokens, json decode failed. Got response: ' . $response->getBody()->getContents()
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

        $url = $this->authorizationUrl . '?' . http_build_query($parameters);

        header('Location: ' . $url);
        exit;
    }

    public function makeRequest(RequestInterface $request): ResponseInterface
    {
        // todo error handling

        return $this->client->sendRequest($request);
    }
}