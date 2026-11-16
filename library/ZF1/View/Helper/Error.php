<?php

declare(strict_types=1);

use ZF1\Session\Session;
use ZF1\Validation\MessageBag;

/**
 * ZF1 View Helper: error()
 *
 * Provides quick access to the first validation error for a field:
 *   <?= $this->error('email') ?>
 *   <?= $this->error('email', '<div class="invalid-feedback">%s</div>') ?>
 */
final class ZF1_View_Helper_Error extends Zend_View_Helper_Abstract
{
    /**
     * Retrieve the first error message for a given field.
     */
    public function error(string $key, ?string $format = null): ?string
    {
        $raw = Session::get('errors');

        $bag = $raw instanceof MessageBag ? $raw : new MessageBag(is_array($raw) ? $raw : []);

        return $bag->first($key, $format);
    }
}
