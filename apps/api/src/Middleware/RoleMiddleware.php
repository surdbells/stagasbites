<?php

declare(strict_types=1);

namespace StagasBites\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use StagasBites\Entity\UserRole;
use StagasBites\Exception\ApiException;

final class RoleMiddleware implements MiddlewareInterface
{
    /** @var list<UserRole> */
    private readonly array $roles;

    public function __construct(UserRole ...$roles)
    {
        $this->roles = array_values($roles);
    }

    public function process(Request $request, Handler $handler): Response
    {
        $role = UserRole::tryFrom((string) $request->getAttribute('user_role'));
        if ($role === null || !in_array($role, $this->roles, true)) {
            throw ApiException::forbidden('You do not have permission to perform this action.');
        }

        return $handler->handle($request);
    }
}
