<?php

declare(strict_types=1);

/**
 * ZF1 View Helper: session()
 *
 * Provides view access to the session store:
 *   <?= $this->session('user.name') ?>
 */
final class ZF1_View_Helper_Session extends Zend_View_Helper_Abstract
{
    /**
     * Retrieve a session value or the Session manager instance.
     *
     * @return mixed|ZF1\Session\Session
     */
    public function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return new ZF1\Session\Session;
        }

        return ZF1\Session\Session::get($key, $default);
    }
}
