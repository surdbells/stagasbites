<?php

declare(strict_types=1);

namespace StagasBites\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use StagasBites\Exception\ApiException;

final class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        // The Stripe webhook must see the raw, untouched payload for signature verification.
        if (str_contains($request->getHeaderLine('Content-Type'), 'application/json')
            && !$request->hasHeader('Stripe-Signature')) {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $parsed = json_decode($raw, true);
                if (!is_array($parsed)) {
                    throw new ApiException('Malformed JSON body.', 400);
                }
                $request = $request->withParsedBody($parsed);
            }
        }

        return $handler->handle($request);
    }
}
