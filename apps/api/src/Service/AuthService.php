<?php

declare(strict_types=1);

namespace StagasBites\Service;

use Psr\Log\LoggerInterface;
use StagasBites\Entity\AuthToken;
use StagasBites\Entity\User;
use StagasBites\Exception\ApiException;
use StagasBites\Repository\AuthTokenRepository;
use StagasBites\Repository\UserRepository;

final class AuthService
{
    private const RESET_TTL = 3600;

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthTokenRepository $tokens,
        private readonly JwtService $jwt,
        private readonly MailService $mail,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array{first_name: string, last_name: string, email: string, phone: ?string, password: string} $data
     *
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        if ($this->users->findByEmail($data['email']) !== null) {
            throw ApiException::validation('Please check the highlighted fields.', [
                'email' => ['An account with this email already exists.'],
            ]);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['first_name']);
        $user->setLastName($data['last_name']);
        $user->setPhone($data['phone']);
        $user->setPassword($data['password']);
        $user->recordLogin();
        $this->users->save($user);

        $this->mail->sendWelcome($user);

        return $this->issueTokens($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            // Burn comparable time so response timing doesn't reveal whether the account exists.
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHRzb21lc2FsdA$2tcdrfnjZx1QPFPBBHdPfKZzkbbWvGQj6z1t9g8n3HM');
            throw ApiException::unauthorized('Incorrect email or password.');
        }
        if ($user->isLocked()) {
            throw ApiException::tooManyRequests('Too many failed attempts. Please try again in 15 minutes.');
        }
        if (!$user->verifyPassword($password)) {
            $user->recordFailedLogin();
            $this->users->save($user);
            throw ApiException::unauthorized('Incorrect email or password.');
        }

        $user->recordLogin();
        $this->users->save($user);

        return $this->issueTokens($user);
    }

    /**
     * Rotates the refresh token: the presented token is consumed and a new pair is issued.
     *
     * @return array<string, mixed>
     */
    public function refresh(string $refreshToken): array
    {
        $token = $this->tokens->findUsable($refreshToken, AuthToken::TYPE_REFRESH)
            ?? throw ApiException::unauthorized('Session expired. Please sign in again.');
        $user = $this->users->find($token->getUserId())
            ?? throw ApiException::unauthorized('Session expired. Please sign in again.');

        $token->markUsed();
        $this->tokens->save($token);

        return $this->issueTokens($user);
    }

    public function logout(string $refreshToken): void
    {
        $token = $this->tokens->findUsable($refreshToken, AuthToken::TYPE_REFRESH);
        if ($token !== null) {
            $token->markUsed();
            $this->tokens->save($token);
        }
    }

    /** Always succeeds from the caller's point of view, so it can't be used to enumerate accounts. */
    public function requestPasswordReset(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }

        $this->tokens->revokeAllForUser($user->getId(), AuthToken::TYPE_PASSWORD_RESET);
        $plain = bin2hex(random_bytes(32));
        $this->tokens->save(new AuthToken($user->getId(), AuthToken::TYPE_PASSWORD_RESET, $plain, self::RESET_TTL));
        $this->mail->sendPasswordReset($user, $plain);
    }

    public function resetPassword(string $plainToken, string $newPassword): void
    {
        $token = $this->tokens->findUsable($plainToken, AuthToken::TYPE_PASSWORD_RESET)
            ?? throw ApiException::validation('This reset link is invalid or has expired.');
        $user = $this->users->findOrFail($token->getUserId());

        $user->setPassword($newPassword);
        $user->recordLogin();
        $token->markUsed();
        $this->users->save($user);
        $this->tokens->revokeAllForUser($user->getId(), AuthToken::TYPE_REFRESH);

        $this->logger->info('Password reset completed.', ['user_id' => $user->getId()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function issueTokens(User $user): array
    {
        $refresh = bin2hex(random_bytes(32));
        $this->tokens->save(new AuthToken($user->getId(), AuthToken::TYPE_REFRESH, $refresh, $this->jwt->refreshTtl()));

        return [
            'access_token' => $this->jwt->issueAccessToken($user),
            'refresh_token' => $refresh,
            'expires_in' => $this->jwt->accessTtl(),
            'user' => $user->toArray(),
        ];
    }
}
