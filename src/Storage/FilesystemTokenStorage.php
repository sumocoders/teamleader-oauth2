<?php

namespace Sumocoders\TeamleaderOauth2\Storage;

final class FilesystemTokenStorage implements TokenStorageInterface
{
    public function getTokenType(): string
    {
        return $this->getData('token_type', 'Bearer');
    }

    public function getAccessToken(): ?string
    {
        return $this->getData('access_token');
    }

    public function getRefreshToken(): ?string
    {
        return $this->getData('refresh_token');
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->getData('expires_at', time());

        return $expiresAt < time();
    }

    public function storeTokens(array $tokens): void
    {
        $tokens['expires_at'] = time() + (int) $tokens['expires_in'];

        file_put_contents($this->getFile(), json_encode($tokens, JSON_THROW_ON_ERROR));
    }

    private function getFile(): string
    {
        return sys_get_temp_dir() . '/' . md5('teamleader_tokens');
    }

    /**
     * @param mixed|null $defaultValue
     */
    private function getData(string $key, $defaultValue = null): ?string
    {
        $content = file_get_contents($this->getFile());
        if ($content === false) {
            return $defaultValue;
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $data[$key] ?? $defaultValue;
    }
}
