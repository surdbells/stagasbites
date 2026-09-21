<?php

declare(strict_types=1);

namespace StagasBites\Module\Account;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StagasBites\Entity\Order;
use StagasBites\Helper\JsonResponse;
use StagasBites\Repository\OrderRepository;

final class AccountController
{
    public function __construct(private readonly OrderRepository $orders)
    {
    }

    public function orders(Request $request, Response $response): Response
    {
        $orders = $this->orders->findForUser((string) $request->getAttribute('user_id'));

        return JsonResponse::success($response, array_map(static fn (Order $o): array => $o->toArray(), $orders))
            ->withHeader('Cache-Control', 'no-store');
    }
}
