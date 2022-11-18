# Teamleader oauth2

## Installation

`composer require sumocoders/teamleader-oauth2`

## Setup

This package uses PSR-17 and PSR-18. You can use any implementation you want. 

For saving the access we provide a TokenStorageInterface, where you'll need to implement storing and fetching of the tokens:

```php
interface TokenStorageInterface
{
    public function getTokenType(): string;
    public function getAccessToken(): ?string;
    public function getRefreshToken(): ?string;
    public function isExpired(): bool;
    public function storeTokens(array $tokens): void;
}
```

See [example](https://github.com/sumocoders/teamleader-oauth2/blob/main/src/examples/FilesystemTokenStorage.php) how to do it with filesystem.

The Teamleader class will need a clientId and clientSecret. Which you'll need to obtain at the [Teamleader marketplace](https://marketplace.teamleader.eu/).

## Usage

To obtain an access token you'll need to call:

```php
$teamleader->acquireAccessToken($redirectUrl, $code);
```

Where `$redirectUrl` is the url you want Teamleader to come back to after Oauth2 authentication and `$code` is for the return when Teamleader comes back to your site and validate the authentication.

After that you can use the Teamleader class to make calls to the Teamleader API. When the access token is expired the Teamleader class will automatically refresh the token.

## Getting data

```php
$teamleader->get('users.me');
$teamleader->get('departments.list');
$teamleader->get('companies.list');
```

## Posting data

```php
$teamleader->post(
    'contacts.add',
    [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]
);
$teamleader->post(
    'contacts.update',
    [
        'id' => 'xxx',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'emails' => [
            ['type' => 'primary', 'email' => 'john@doe.com'],
        ],
    ]
)
```
