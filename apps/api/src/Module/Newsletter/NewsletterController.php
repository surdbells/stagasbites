<?php

declare(strict_types=1);

namespace StagasBites\Module\Newsletter;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StagasBites\Entity\Subscriber;
use StagasBites\Helper\JsonResponse;
use StagasBites\Repository\SubscriberRepository;
use StagasBites\Service\ValidationService;
use Symfony\Component\Validator\Constraints as Assert;

final class NewsletterController
{
    public function __construct(
        private readonly SubscriberRepository $subscribers,
        private readonly ValidationService $validator,
    ) {
    }

    public function subscribe(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
        ]);

        // Same reply whether or not the address was already on the list, so it can't be used to probe for emails.
        $existing = $this->subscribers->findOneBy(['email' => strtolower($data['email'])]);
        if ($existing === null) {
            $this->subscribers->save(new Subscriber($data['email']));
        } else {
            $existing->setActive(true);
            $this->subscribers->save($existing);
        }

        return JsonResponse::success($response, null, 201, "You're on the list. We'll only email when there's something tasty to share.");
    }

    public function unsubscribe(Request $request, Response $response, string $token): Response
    {
        $subscriber = $this->subscribers->findOneBy(['unsubscribeToken' => $token]);
        if ($subscriber !== null) {
            $subscriber->setActive(false);
            $this->subscribers->save($subscriber);
        }

        return JsonResponse::success($response, null, 200, 'You have been unsubscribed.');
    }

    public function index(Request $request, Response $response): Response
    {
        $all = $this->subscribers->findBy([], ['createdAt' => 'DESC']);

        return JsonResponse::success($response, array_map(static fn (Subscriber $s): array => $s->toArray(), $all));
    }
}
