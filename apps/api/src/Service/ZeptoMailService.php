<?php

declare(strict_types=1);

namespace StagasBites\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/** ZeptoMail transactional email over the HTTP API (v1.1). Never throws: mail must not break checkout. */
final class ZeptoMailService
{
    private const BASE_URL = 'https://api.zeptomail.com/v1.1/';

    private readonly Client $client;

    /**
     * @param array{api_key: string, from_email: string, from_name: string, admin_email: string} $config
     */
    public function __construct(private readonly array $config, private readonly LoggerInterface $logger, ?Client $client = null)
    {
        $this->client = $client ?? new Client(['base_uri' => self::BASE_URL, 'timeout' => 15]);
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        if ($this->config['api_key'] === '') {
            $this->logger->warning('ZeptoMail API key not configured; email skipped.', ['to' => $toEmail, 'subject' => $subject]);

            return false;
        }

        $payload = [
            'from' => ['address' => $this->config['from_email'], 'name' => $this->config['from_name']],
            'to' => [['email_address' => ['address' => $toEmail, 'name' => $toName]]],
            'subject' => $subject,
            'htmlbody' => $htmlBody,
        ];
        if ($replyTo !== null) {
            $payload['reply_to'] = [['address' => $replyTo]];
        }

        // Accept either the bare token or the full "Zoho-enczapikey ..." string from the ZeptoMail console.
        $key = $this->config['api_key'];
        $authorization = str_starts_with($key, 'Zoho-enczapikey') ? $key : 'Zoho-enczapikey ' . $key;

        try {
            $this->client->post('email', [
                'headers' => ['Authorization' => $authorization, 'Accept' => 'application/json'],
                'json' => $payload,
            ]);

            return true;
        } catch (GuzzleException $e) {
            $this->logger->error('ZeptoMail send failed.', ['to' => $toEmail, 'subject' => $subject, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function adminEmail(): string
    {
        return $this->config['admin_email'];
    }
}
