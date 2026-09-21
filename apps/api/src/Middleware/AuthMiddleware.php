<?php

declare(strict_types=1);

namespace StagasBites\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use StagasBites\Exception\ApiException;
use StagasBites\Service\JwtService;

/**
 * Validates the Bearer access token and exposes `user_id`, `user_role` and `user_email`
 * as request attributes. In optional mode, anonymous requests pass through untouched
 * (used by checkout so signed-in customers get the order attached to their account).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly JwtService $jwt, private readonly bool $optional = false)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            if ($this->optional) {
                return $handler->handle($request);
            }
            throw ApiException::unauthorized('Authentication required.');
        }

        try {
            $claims = $this->jwt->validateAccessToken($m[1]);
        } catch (ApiException $e) {
            if ($this->optional) {
                return $handler->handle($request);
            }
            throw $e;
        }

        return $handler->handle(
            $request
                ->withAttribute('user_id', $claims['sub'])
                ->withAttribute('user_role', $claims['role'])
                ->withAttribute('user_email', $claims['email']),
        );
    }
}
