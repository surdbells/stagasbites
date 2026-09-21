<?php

declare(strict_types=1);

namespace StagasBites\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use StagasBites\Entity\User;
use StagasBites\Exception\ApiException;

final class JwtService
{
    /**
     * @param array{secret: string, access_ttl: int, refresh_ttl: int, algorithm: string, issuer: string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function issueAccessToken(User $user): string
    {
        $this->assertConfigured();
        $now = time();

        return JWT::encode([
            'iss' => $this->config['issuer'],
            'sub' => $user->getId(),
            'role' => $user->getRole()->value,
            'email' => $user->getEmail(),
            'type' => 'access',
            'iat' => $now,
            'exp' => $now + $this->config['access_ttl'],
        ], $this->config['secret'], $this->config['algorithm']);
    }

    /**
     * @return array{sub: string, role: string, email: string}
     */
    public function validateAccessToken(string $token): array
    {
        $this->assertConfigured();

        try {
            $claims = (array) JWT::decode($token, new Key($this->config['secret'], $this->config['algorithm']));
        } catch (\Throwable) {
            throw ApiException::unauthorized('Invalid or expired token.');
        }

        if (($claims['type'] ?? null) !== 'access' || ($claims['iss'] ?? null) !== $this->config['issuer']) {
            throw ApiException::unauthorized('Invalid token.');
        }

        return [
            'sub' => (string) $claims['sub'],
            'role' => (string) $claims['role'],
            'email' => (string) $claims['email'],
        ];
    }

    public function accessTtl(): int
    {
        return $this->config['access_ttl'];
    }

    public function refreshTtl(): int
    {
        return $this->config['refresh_ttl'];
    }

    private function assertConfigured(): void
    {
        // HS256 needs a key of at least 256 bits; refuse to sign with a weak or missing secret.
        if (strlen($this->config['secret']) < 32) {
            throw ApiException::unavailable('Authentication is not configured.');
        }
    }
}
