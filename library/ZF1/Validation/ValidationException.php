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
     * Get a MessageBag instance of the errors.
     */
    public function errorsBag(): MessageBag
    {
        return new MessageBag($this->errors);
    }

    /**
     * Get the single first error message for a key, or first overall error.
     */
    public function error(?string $key = null, ?string $format = null): ?string
    {
        return $this->errorsBag()->first($key, $format);
    }

    /**
     * Get the first error message for a key, or first overall error (alias for error()).
     */
    public function first(?string $key = null, ?string $format = null): ?string
    {
        return $this->error($key, $format);
    }

    /**
     * Get the validator instance.
     */
    public function validator(): ?Validator
    {
        return $this->validator;
    }
}
