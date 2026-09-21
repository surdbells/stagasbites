<?php

declare(strict_types=1);

namespace StagasBites\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $response = $request->getMethod() === 'OPTIONS'
            ? (new SlimResponse())->withStatus(204)
            : $handler->handle($request);

        $origin = $request->getHeaderLine('Origin');
        if ($origin === '' || !in_array($origin, $this->allowedOrigins, true)) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
