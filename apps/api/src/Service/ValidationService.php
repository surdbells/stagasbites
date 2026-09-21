<?php

declare(strict_types=1);

namespace StagasBites\Service;

use StagasBites\Exception\ApiException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationService
{
    private readonly ValidatorInterface $validator;

    public function __construct()
    {
        $this->validator = Validation::createValidator();
    }

    /**
     * Validates a request body against per-field constraints and returns only the declared fields.
     * Unknown fields are dropped; missing optional fields come back as null.
     *
     * @param array<string, mixed> $data
     * @param array<string, Constraint|list<Constraint>> $rules
     *
     * @return array<string, mixed>
     *
     * @throws ApiException 422 with `errors[field][]`
     */
    public function validate(array $data, array $rules): array
    {
        $fields = [];
        foreach ($rules as $field => $constraints) {
            $constraints = is_array($constraints) ? $constraints : [$constraints];
            $required = array_filter($constraints, static fn (Constraint $c): bool => $c instanceof Assert\NotBlank || $c instanceof Assert\NotNull) !== [];
            $fields[$field] = $required ? new Assert\Required($constraints) : new Assert\Optional($constraints);
        }

        $violations = $this->validator->validate($data, new Assert\Collection(
            fields: $fields,
            allowExtraFields: true,
            missingFieldsMessage: 'This field is required.',
        ));

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $path = trim(str_replace(['][', '[', ']'], ['.', '', ''], $violation->getPropertyPath()), '.');
                $errors[$path][] = (string) $violation->getMessage();
            }
            throw ApiException::validation('Please check the highlighted fields.', $errors);
        }

        $clean = [];
        foreach (array_keys($rules) as $field) {
            $value = $data[$field] ?? null;
            $clean[$field] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }

    /**
     * @return list<Constraint>
     */
    public static function password(): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Length(min: 10, max: 128, minMessage: 'Use at least 10 characters.'),
            new Assert\Regex(pattern: '/[A-Z]/', message: 'Include at least one uppercase letter.'),
            new Assert\Regex(pattern: '/\d/', message: 'Include at least one number.'),
        ];
    }
}
