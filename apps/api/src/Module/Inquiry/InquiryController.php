<?php

declare(strict_types=1);

namespace StagasBites\Module\Inquiry;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StagasBites\Entity\Inquiry;
use StagasBites\Helper\JsonResponse;
use StagasBites\Repository\InquiryRepository;
use StagasBites\Service\MailService;
use StagasBites\Service\ValidationService;
use Symfony\Component\Validator\Constraints as Assert;

final class InquiryController
{
    public function __construct(
        private readonly InquiryRepository $inquiries,
        private readonly MailService $mail,
        private readonly ValidationService $validator,
    ) {
    }

    public function create(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        // Honeypot: real visitors never see or fill this field. Pretend success so bots learn nothing.
        if (trim((string) ($body['website'] ?? '')) !== '') {
            return JsonResponse::success($response, null, 201, 'Thanks — we will be in touch shortly.');
        }

        $data = $this->validator->validate($body, [
            'type' => [new Assert\NotBlank(), new Assert\Choice([Inquiry::TYPE_CONTACT, Inquiry::TYPE_CATERING])],
            'name' => [new Assert\NotBlank(), new Assert\Length(max: 160)],
            'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            'phone' => [new Assert\Length(max: 30)],
            'event_date' => [new Assert\Date()],
            'guest_count' => [new Assert\Type('integer'), new Assert\Range(min: 1, max: 5000)],
            'event_type' => [new Assert\Length(max: 120)],
            'message' => [new Assert\NotBlank(), new Assert\Length(min: 10, max: 4000)],
        ]);

        $inquiry = new Inquiry(
            $data['type'],
            $data['name'],
            $data['email'],
            $data['phone'] ?: null,
            $data['message'],
            empty($data['event_date']) ? null : new \DateTimeImmutable($data['event_date']),
            $data['guest_count'],
            $data['event_type'] ?: null,
        );
        $this->inquiries->save($inquiry);

        $this->mail->sendInquiryAlert($inquiry);
        $this->mail->sendInquiryReceipt($inquiry);

        return JsonResponse::success($response, null, 201, 'Thanks — we will be in touch shortly.');
    }
}
