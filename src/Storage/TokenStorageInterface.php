<?php
namespace Sumocoders\TeamleaderOauth2\Storage;

interface TokenStorageInterface
{
    public function getAccessToken(): ?string;
    public function getRefreshToken(): ?string;
    public function storeTokens(array $tokens): void;
}
