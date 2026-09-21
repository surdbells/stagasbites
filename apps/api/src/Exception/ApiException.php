<?php

declare(strict_types=1);

namespace StagasBites\Exception;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(string $message, private readonly int $statusCode = 400, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function validation(string $message = 'Validation failed.', array $errors = []): self
    {
        return new self($message, 422, $errors);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized.'): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden.'): self
    {
        return new self($message, 403);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please try again shortly.'): self
    {
        return new self($message, 429);
    }

    public static function unavailable(string $message = 'Service temporarily unavailable.'): self
    {
        return new self($message, 503);
    }
}
