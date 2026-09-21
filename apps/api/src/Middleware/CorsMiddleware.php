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
        if ($origin === '' || !$this->isAllowed($origin)) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With')
            ->withHeader('Access-Control-Max-Age', '86400');
    }

    /**
     * Exact origins, plus "https://*.example.pages.dev" style entries for Cloudflare Pages preview deployments.
     */
    private function isAllowed(string $origin): bool
    {
        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return true;
            }
            if (str_contains($allowed, '://*.')) {
                [$scheme, $suffix] = explode('://*', $allowed, 2);
                if (str_starts_with($origin, $scheme . '://') && str_ends_with($origin, $suffix) && strlen($origin) > strlen($scheme . '://' . $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
