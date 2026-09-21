<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Exception\HttpException;
use Slim\Psr7\Response;
use StagasBites\Exception\ApiException;
use StagasBites\Helper\JsonResponse;
use StagasBites\Middleware\CorsMiddleware;
use StagasBites\Middleware\JsonBodyParserMiddleware;
use StagasBites\Middleware\SecurityHeadersMiddleware;

return function (App $app): void {
    $container = $app->getContainer();
    $debug = (bool) $container->get('settings')['app']['debug'];
    $logger = $container->get(LoggerInterface::class);

    // Slim middleware is LIFO: the last one added runs first.
    $app->add(JsonBodyParserMiddleware::class);
    $app->addRoutingMiddleware();

    $errorMiddleware = $app->addErrorMiddleware($debug, true, true, $logger);
    $errorMiddleware->setDefaultErrorHandler(
        static function (ServerRequestInterface $request, Throwable $e) use ($debug, $logger): ResponseInterface {
            $response = new Response();
            if ($e instanceof ApiException) {
                return JsonResponse::error($response, $e->getMessage(), $e->getStatusCode(), $e->getErrors());
            }
            if ($e instanceof HttpException) {
                return JsonResponse::error($response, $e->getMessage(), $e->getCode());
            }

            $logger->error($e->getMessage(), ['exception' => $e, 'uri' => (string) $request->getUri()]);

            return JsonResponse::error($response, $debug ? $e->getMessage() : 'Something went wrong on our side. Please try again.', 500);
        },
    );

    $app->add(SecurityHeadersMiddleware::class);
    $app->add(CorsMiddleware::class); // outermost, so error responses carry CORS headers too
};
