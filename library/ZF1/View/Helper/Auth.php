<?php

declare(strict_types=1);

/**
 * ZF1 View Helper: auth()
 *
 * Provides view access to authentication manager:
 *   <?= $this->auth()->user()->name ?>
 *   <?= $this->auth()->id ?>
 *   <?php if ($this->auth()->check()): ?> ... <?php endif; ?>
 */
final class ZF1_View_Helper_Auth extends Zend_View_Helper_Abstract
{
    /**
     * Retrieve the authentication manager instance.
     */
    public function auth(): ZF1\Auth\Auth
    {
        return ZF1\Auth\Auth::getInstance();
    }
}
