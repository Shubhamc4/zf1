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
     * Retrieve the validation MessageBag from session.
     */
    public function errors(): MessageBag
    {
        $raw = Session::get('errors');

        if ($raw instanceof MessageBag) {
            return $raw;
        }

        if (is_array($raw)) {
            return new MessageBag($raw);
        }

        return new MessageBag;
    }
}
