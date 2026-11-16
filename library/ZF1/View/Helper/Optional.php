<?php

declare(strict_types=1);

use ZF1\Support\Optional;

/**
 * ZF1 View Helper: optional()
 *
 * Provides graceful null-safe property and method access in view templates:
 *   <?= $this->escape($this->optional($user)->name) ?>
 */
final class ZF1_View_Helper_Optional extends Zend_View_Helper_Abstract
{
    /**
     * Provide access to optional objects.
     *
     * @template T
     *
     * @param  T  $value
     * @param  (callable(T): mixed)|null  $callback
     * @return mixed|Optional
     */
    public function optional(mixed $value = null, ?callable $callback = null): mixed
    {
        if ($callback === null) {
            if ($value instanceof Optional) {
                return $value;
            }

            return new Optional($value);
        }

        if ($value !== null) {
            return $callback($value);
        }

        return null;
    }
}
