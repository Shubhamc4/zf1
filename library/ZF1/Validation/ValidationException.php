<?php

declare(strict_types=1);

namespace ZF1\Validation;

use RuntimeException;

/**
 * Exception thrown when validation fails.
 */
final class ValidationException extends RuntimeException
{
    /**
     * The validation error messages.
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * The validator instance that failed.
     */
    protected ?Validator $validator = null;

    /**
     * Create a new validation exception.
     *
     * @param  array<string, array<string>>|MessageBag  $errors
     */
    public function __construct(array|MessageBag $errors, ?Validator $validator = null, string $message = 'The given data was invalid.')
    {
        parent::__construct($message, 422);

        $this->errors = $errors instanceof MessageBag ? $errors->toArray() : $errors;
        $this->validator = $validator;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the validator instance.
     */
    public function validator(): ?Validator
    {
        return $this->validator;
    }
}
