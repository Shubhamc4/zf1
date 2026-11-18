<?php

declare(strict_types=1);

use ZF1\Session\Session;
use ZF1\Validation\MessageBag;

/**
 * ZF1 View Helper: errors()
 *
 * Provides view access to flashed validation error messages:
 *   <?php if ($this->errors()->has('email')): ?>
 *       <span class="error"><?= $this->escape($this->errors()->first('email')) ?></span>
 *   <?php endif; ?>
 */
final class ZF1_View_Helper_Errors extends Zend_View_Helper_Abstract
{
    /**
     * Retrieve the validation MessageBag from session, or the first error for a field.
     *
     * @param  string|null  $key  Optional field name (e.g. 'email')
     * @param  string|null  $format  Optional format string (e.g. '<span class="error">%s</span>')
     * @return MessageBag|string|null
     */
    public function errors(?string $key = null, ?string $format = null): mixed
    {
        $raw = Session::get('errors');

        $bag = $raw instanceof MessageBag ? $raw : new MessageBag(is_array($raw) ? $raw : []);

        if ($key === null) {
            return $bag;
        }

        return $bag->first($key, $format);
    }
}
