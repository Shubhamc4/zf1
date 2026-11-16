<?php

declare(strict_types=1);

namespace ZF1\Validation;

use Closure;
use DateTime;
use Throwable;
use ZF1\Database\DB;

/**
 * Modern Validation Engine for Zend Framework 1.
 */
final class Validator
{
    /**
     * Input data to be validated.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Validation rules mapped by attribute.
     *
     * @var array<string, mixed>
     */
    protected array $rules = [];

    /**
     * Custom error messages.
     *
     * @var array<string, string>
     */
    protected array $customMessages = [];

    /**
     * Custom friendly attribute display names.
     *
     * @var array<string, string>
     */
    protected array $customAttributes = [];

    /**
     * Validated attributes and values.
     *
     * @var array<string, mixed>
     */
    protected array $validatedData = [];

    /**
     * Collected validation errors.
     */
    protected MessageBag $errors;

    /**
     * Flag indicating if validation has already run.
     */
    protected bool $hasRun = false;

    /**
     * Globally registered custom validation rule callbacks.
     *
     * @var array<string, array{callback: Closure, message: string|null}>
     */
    protected static array $extensions = [];

    /**
     * Default error message templates for built-in rules.
     *
     * @var array<string, string>
     */
    protected static array $defaultMessages = [
        'required' => 'The :attribute field is required.',
        'string' => 'The :attribute must be a string.',
        'integer' => 'The :attribute must be an integer.',
        'numeric' => 'The :attribute must be a number.',
        'boolean' => 'The :attribute field must be true or false.',
        'array' => 'The :attribute must be an array.',
        'json' => 'The :attribute must be a valid JSON string.',
        'email' => 'The :attribute must be a valid email address.',
        'url' => 'The :attribute must be a valid URL.',
        'ip' => 'The :attribute must be a valid IP address.',
        'ipv4' => 'The :attribute must be a valid IPv4 address.',
        'ipv6' => 'The :attribute must be a valid IPv6 address.',
        'min' => 'The :attribute must be at least :min.',
        'min.string' => 'The :attribute must be at least :min characters.',
        'min.numeric' => 'The :attribute must be at least :min.',
        'min.array' => 'The :attribute must have at least :min items.',
        'max' => 'The :attribute may not be greater than :max.',
        'max.string' => 'The :attribute may not be greater than :max characters.',
        'max.numeric' => 'The :attribute may not be greater than :max.',
        'max.array' => 'The :attribute may not have more than :max items.',
        'between' => 'The :attribute must be between :min and :max.',
        'between.string' => 'The :attribute must be between :min and :max characters.',
        'between.numeric' => 'The :attribute must be between :min and :max.',
        'between.array' => 'The :attribute must have between :min and :max items.',
        'size' => 'The :attribute must be :size.',
        'size.string' => 'The :attribute must be :size characters.',
        'size.numeric' => 'The :attribute must be :size.',
        'size.array' => 'The :attribute must contain :size items.',
        'in' => 'The selected :attribute is invalid.',
        'not_in' => 'The selected :attribute is invalid.',
        'confirmed' => 'The :attribute confirmation does not match.',
        'same' => 'The :attribute and :other must match.',
        'different' => 'The :attribute and :other must be different.',
        'date' => 'The :attribute is not a valid date.',
        'date_format' => 'The :attribute does not match the format :format.',
        'before' => 'The :attribute must be a date before :date.',
        'after' => 'The :attribute must be a date after :date.',
        'regex' => 'The :attribute format is invalid.',
        'alpha' => 'The :attribute may only contain letters.',
        'alpha_num' => 'The :attribute may only contain letters and numbers.',
        'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
        'uuid' => 'The :attribute must be a valid UUID.',
        'digits' => 'The :attribute must be :digits digits.',
        'digits_between' => 'The :attribute must be between :min and :max digits.',
        'unique' => 'The :attribute has already been taken.',
        'exists' => 'The selected :attribute is invalid.',
    ];

    /**
     * Create a new validator instance.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $customAttributes
     */
    public function __construct(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $messages;
        $this->customAttributes = $customAttributes;
        $this->errors = new MessageBag;
    }

    /**
     * Static factory to create a new validator instance.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $customAttributes
     */
    public static function make(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): static {
        return new self($data, $rules, $messages, $customAttributes);
    }

    /**
     * Register a custom rule extension globally.
     *
     * @param  callable(string, mixed, array<string>): bool  $callback
     */
    public static function extend(string $rule, callable $callback, ?string $message = null): void
    {
        self::$extensions[strtolower($rule)] = [
            'callback' => $callback instanceof Closure ? $callback : Closure::fromCallable($callback),
            'message' => $message,
        ];
    }

    /**
     * Determine if the data fails the validation rules.
     */
    public function fails(): bool
    {
        if (! $this->hasRun) {
            $this->validateData();
        }

        return $this->errors->isNotEmpty();
    }

    /**
     * Determine if the data passes the validation rules.
     */
    public function passes(): bool
    {
        return ! $this->fails();
    }

    /**
     * Get the validation error messages.
     */
    public function errors(): MessageBag
    {
        if (! $this->hasRun) {
            $this->validateData();
        }

        return $this->errors;
    }

    /**
     * Run the validator and return the validated data, or throw ValidationException on failure.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors(), $this);
        }

        return $this->validated();
    }

    /**
     * Get the validated attributes and values.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        if (! $this->hasRun) {
            $this->validateData();
        }

        return $this->validatedData;
    }

    /**
     * Execute all validation rules against data.
     */
    protected function validateData(): void
    {
        $this->hasRun = true;
        $this->validatedData = [];
        $this->errors = new MessageBag;

        foreach ($this->rules as $attribute => $ruleSet) {
            $rules = $this->parseRuleSet($ruleSet);
            $value = $this->getValue($attribute);

            $isSometimes = in_array('sometimes', $rules, true);
            if ($isSometimes && ! $this->hasValue($attribute)) {
                continue;
            }

            $isNullable = in_array('nullable', $rules, true);
            if ($isNullable && ($value === null || $value === '')) {
                $this->validatedData[$attribute] = null;

                continue;
            }

            $hasError = false;
            foreach ($rules as $rule) {
                if ($rule === 'nullable' || $rule === 'sometimes') {
                    continue;
                }

                // Custom closure rule
                if ($rule instanceof Closure) {
                    $failed = false;
                    $failCallback = function (string $msg) use (&$failed, $attribute) {
                        $failed = true;
                        $this->addError($attribute, 'custom', $msg);
                    };

                    $rule($attribute, $value, $failCallback);
                    if ($failed) {
                        $hasError = true;
                        break;
                    }

                    continue;
                }

                [$ruleName, $parameters] = $this->parseRuleParameters((string) $rule);

                if (! $this->validateRule($attribute, $ruleName, $value, $parameters)) {
                    $hasError = true;
                    $this->addRuleError($attribute, $ruleName, $parameters);
                    break; // Stop after first failing rule for attribute
                }
            }

            if (! $hasError && $this->hasValue($attribute)) {
                $this->validatedData[$attribute] = $value;
            }
        }
    }

    /**
     * Parse a rule set into an array of rule specifications.
     *
     * @param  string|array<mixed>  $rules
     * @return array<mixed>
     */
    protected function parseRuleSet(string|array $rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }

        return array_map('trim', explode('|', $rules));
    }

    /**
     * Parse rule name and its colon-delimited parameters.
     *
     * @return array{0: string, 1: array<string>}
     */
    protected function parseRuleParameters(string $rule): array
    {
        if (! str_contains($rule, ':')) {
            return [strtolower(trim($rule)), []];
        }

        [$name, $paramString] = explode(':', $rule, 2);

        $params = str_getcsv($paramString, ',', "'");
        $params = array_map('trim', $params);

        return [strtolower(trim($name)), $params];
    }

    /**
     * Validate an individual rule against an attribute and value.
     *
     * @param  array<string>  $parameters
     */
    protected function validateRule(string $attribute, string $rule, mixed $value, array $parameters): bool
    {
        if ($rule === 'required') {
            return $this->validateRequired($value);
        }

        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return true;
        }

        if (isset(self::$extensions[$rule])) {
            return (bool) (self::$extensions[$rule]['callback'])($attribute, $value, $parameters, $this->data);
        }

        return match ($rule) {
            'string' => is_string($value),
            'integer', 'int' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'boolean', 'bool' => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true),
            'array' => is_array($value),
            'json' => $this->validateJson($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'ip' => is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false,
            'ipv4' => is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6' => is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'min' => $this->validateMin($value, (float) ($parameters[0] ?? 0)),
            'max' => $this->validateMax($value, (float) ($parameters[0] ?? 0)),
            'between' => $this->validateBetween($value, (float) ($parameters[0] ?? 0), (float) ($parameters[1] ?? 0)),
            'size' => $this->validateSize($value, (float) ($parameters[0] ?? 0)),
            'in' => in_array((string) $value, $parameters, true),
            'not_in' => ! in_array((string) $value, $parameters, true),
            'confirmed' => $this->validateConfirmed($attribute, $value),
            'same' => $this->validateSame($value, $parameters[0] ?? ''),
            'different' => $this->validateDifferent($value, $parameters[0] ?? ''),
            'date' => is_string($value) && strtotime($value) !== false,
            'date_format' => $this->validateDateFormat($value, $parameters[0] ?? 'Y-m-d'),
            'before' => $this->validateDateComparison($value, $parameters[0] ?? '', '<'),
            'after' => $this->validateDateComparison($value, $parameters[0] ?? '', '>'),
            'regex' => is_string($value) && (bool) @preg_match($parameters[0] ?? '//', $value),
            'alpha' => is_string($value) && (bool) preg_match('/^[\pL\pM]+$/u', $value),
            'alpha_num' => is_string($value) && (bool) preg_match('/^[\pL\pM\pN]+$/u', $value),
            'alpha_dash' => is_string($value) && (bool) preg_match('/^[\pL\pM\pN_-]+$/u', $value),
            'uuid' => is_string($value) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value),
            'digits' => is_numeric($value) && strlen((string) $value) === (int) ($parameters[0] ?? 0),
            'digits_between' => $this->validateDigitsBetween($value, (int) ($parameters[0] ?? 0), (int) ($parameters[1] ?? 0)),
            'unique' => $this->validateUnique($attribute, $value, $parameters),
            'exists' => $this->validateExists($attribute, $value, $parameters),
            default => true,
        };
    }

    /**
     * Validate that a value is not empty.
     */
    protected function validateRequired(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && count($value) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Validate JSON syntax (using native json_validate in PHP 8.3+).
     */
    protected function validateJson(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        if (function_exists('json_validate')) {
            return json_validate($value);
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate minimum size / length / value.
     */
    protected function validateMin(mixed $value, float $min): bool
    {
        return $this->getSize($value) >= $min;
    }

    /**
     * Validate maximum size / length / value.
     */
    protected function validateMax(mixed $value, float $max): bool
    {
        return $this->getSize($value) <= $max;
    }

    /**
     * Validate between size / length / value.
     */
    protected function validateBetween(mixed $value, float $min, float $max): bool
    {
        $size = $this->getSize($value);

        return $size >= $min && $size <= $max;
    }

    /**
     * Validate exact size.
     */
    protected function validateSize(mixed $value, float $size): bool
    {
        return $this->getSize($value) === $size;
    }

    /**
     * Get size for comparison (numeric value, string length, or array count).
     */
    protected function getSize(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_array($value)) {
            return (float) count($value);
        }

        return (float) mb_strlen((string) $value);
    }

    /**
     * Validate confirmed field (e.g. password matches password_confirmation).
     */
    protected function validateConfirmed(string $attribute, mixed $value): bool
    {
        $confirmationKey = $attribute.'_confirmation';
        $confirmationValue = $this->getValue($confirmationKey);

        return $value === $confirmationValue;
    }

    /**
     * Validate that two fields have the exact same value.
     */
    protected function validateSame(mixed $value, string $other): bool
    {
        return $value === $this->getValue($other);
    }

    /**
     * Validate that two fields have different values.
     */
    protected function validateDifferent(mixed $value, string $other): bool
    {
        return $value !== $this->getValue($other);
    }

    /**
     * Validate that a value matches a date format.
     */
    protected function validateDateFormat(mixed $value, string $format): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $parsed = DateTime::createFromFormat('!'.$format, $value);

        return $parsed && $parsed->format($format) === $value;
    }

    /**
     * Validate date comparison (before / after).
     */
    protected function validateDateComparison(mixed $value, string $dateString, string $operator): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $valTime = strtotime($value);
        $targetTime = strtotime($dateString) ?: strtotime((string) $this->getValue($dateString));

        if (! $valTime || ! $targetTime) {
            return false;
        }

        return $operator === '<' ? $valTime < $targetTime : $valTime > $targetTime;
    }

    /**
     * Validate digits between min and max length.
     */
    protected function validateDigitsBetween(mixed $value, int $min, int $max): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        $len = strlen((string) $value);

        return $len >= $min && $len <= $max;
    }

    /**
     * Validate uniqueness in database table: unique:table,column,ignore_id,id_column.
     *
     * @param  array<string>  $parameters
     */
    protected function validateUnique(string $attribute, mixed $value, array $parameters): bool
    {
        $table = $parameters[0] ?? '';
        $column = $parameters[1] ?? $attribute;
        $ignoreId = $parameters[2] ?? null;
        $idColumn = $parameters[3] ?? 'id';

        if ($table === '') {
            return true;
        }

        try {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
            $bindings = [$value];

            if ($ignoreId !== null && $ignoreId !== '') {
                $sql .= " AND {$idColumn} != ?";
                $bindings[] = $ignoreId;
            }

            $count = (int) DB::scalar($sql, $bindings);

            return $count === 0;
        } catch (Throwable) {
            return true; // Gracefully pass if database is unreachable or offline
        }
    }

    /**
     * Validate existence in database table: exists:table,column.
     *
     * @param  array<string>  $parameters
     */
    protected function validateExists(string $attribute, mixed $value, array $parameters): bool
    {
        $table = $parameters[0] ?? '';
        $column = $parameters[1] ?? $attribute;

        if ($table === '') {
            return true;
        }

        try {
            $count = (int) DB::scalar("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$value]);

            return $count > 0;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Check if a value exists in the input dataset.
     */
    protected function hasValue(string $attribute): bool
    {
        return function_exists('array_has')
            ? array_has($this->data, $attribute)
            : array_key_exists($attribute, $this->data);
    }

    /**
     * Retrieve a value from the dataset with dot-notation support.
     */
    protected function getValue(string $attribute): mixed
    {
        return function_exists('data_get')
            ? data_get($this->data, $attribute)
            : ($this->data[$attribute] ?? null);
    }

    /**
     * Add an error message for a failing rule.
     *
     * @param  array<string>  $parameters
     */
    protected function addRuleError(string $attribute, string $rule, array $parameters): void
    {
        $message = $this->getErrorMessage($attribute, $rule, $parameters);
        $this->errors->add($attribute, $message);
    }

    /**
     * Add a direct custom error message.
     */
    protected function addError(string $attribute, string $rule, string $message): void
    {
        $this->errors->add($attribute, $message);
    }

    /**
     * Resolve and format the error message.
     *
     * @param  array<string>  $parameters
     */
    protected function getErrorMessage(string $attribute, string $rule, array $parameters): string
    {
        $key = "{$attribute}.{$rule}";
        if (isset($this->customMessages[$key])) {
            return $this->formatMessage($this->customMessages[$key], $attribute, $parameters);
        }

        if (isset($this->customMessages[$attribute])) {
            return $this->formatMessage($this->customMessages[$attribute], $attribute, $parameters);
        }

        if (isset(self::$extensions[$rule]['message']) && self::$extensions[$rule]['message'] !== null) {
            return $this->formatMessage(self::$extensions[$rule]['message'], $attribute, $parameters);
        }

        if (in_array($rule, ['min', 'max', 'between', 'size'], true)) {
            $type = $this->getAttributeType($attribute);
            $typeRule = "{$rule}.{$type}";
            if (isset(self::$defaultMessages[$typeRule])) {
                return $this->formatMessage(self::$defaultMessages[$typeRule], $attribute, $parameters);
            }
        }

        $template = self::$defaultMessages[$rule] ?? 'The :attribute field is invalid.';

        return $this->formatMessage($template, $attribute, $parameters);
    }

    /**
     * Determine the primary type of an attribute for error messages.
     */
    protected function getAttributeType(string $attribute): string
    {
        $val = $this->getValue($attribute);

        if (is_array($val)) {
            return 'array';
        }

        $rules = $this->parseRuleSet($this->rules[$attribute] ?? []);
        if (in_array('numeric', $rules, true) || in_array('integer', $rules, true) || in_array('int', $rules, true)) {
            return 'numeric';
        }

        if (is_numeric($val) && ! is_string($val)) {
            return 'numeric';
        }

        return 'string';
    }

    /**
     * Replace placeholders in message templates (:attribute, :min, :max, etc.).
     *
     * @param  array<string>  $parameters
     */
    protected function formatMessage(string $template, string $attribute, array $parameters): string
    {
        $displayName = $this->customAttributes[$attribute] ?? str_replace(['_', '-'], ' ', $attribute);

        $replacements = [
            ':attribute' => $displayName,
        ];

        if (isset($parameters[0])) {
            $replacements[':min'] = $parameters[0];
            $replacements[':max'] = $parameters[0];
            $replacements[':size'] = $parameters[0];
            $replacements[':digits'] = $parameters[0];
            $replacements[':format'] = $parameters[0];
            $replacements[':date'] = $parameters[0];
            $replacements[':other'] = str_replace(['_', '-'], ' ', $parameters[0]);
            $replacements[':values'] = implode(', ', $parameters);
        }

        if (isset($parameters[1])) {
            $replacements[':max'] = $parameters[1];
        }

        return strtr($template, $replacements);
    }
}
