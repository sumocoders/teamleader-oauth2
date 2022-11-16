<?php

namespace Sumocoders\TeamleaderOauth2;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sumocoders\TeamleaderOauth2\Exception\TeamleaderException;

final class Teamleader
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
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
        StreamFactoryInterface $streamFactory
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
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

        // todo store tokens
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
