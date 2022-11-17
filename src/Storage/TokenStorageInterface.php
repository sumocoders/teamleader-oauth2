<?php

namespace Sumocoders\TeamleaderOauth2\Storage;

interface TokenStorageInterface
{
    public function getTokenType(): ?string;
    public function getAccessToken(): ?string;
    public function getRefreshToken(): ?string;
    public function isExpired(): bool;
    /** @param array<string> $tokens */
    public function storeTokens(array $tokens): void;
}
