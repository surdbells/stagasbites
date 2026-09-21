<?php

declare(strict_types=1);

namespace StagasBites\Middleware;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use StagasBites\Exception\ApiException;

/** Fixed-window limiter keyed by client IP. Backed by a PSR-6 pool so no Redis is required. */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly string $prefix,
    ) {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $ip = self::clientIp($request);
        $window = intdiv(time(), $this->windowSeconds);
        $item = $this->cache->getItem(sprintf('rl.%s.%s.%d', $this->prefix, hash('xxh3', $ip), $window));

        $hits = (int) ($item->get() ?? 0) + 1;
        $item->set($hits)->expiresAfter($this->windowSeconds);
        $this->cache->save($item);

        if ($hits > $this->maxAttempts) {
            throw ApiException::tooManyRequests();
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxAttempts - $hits));
    }

    public static function clientIp(Request $request): string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
