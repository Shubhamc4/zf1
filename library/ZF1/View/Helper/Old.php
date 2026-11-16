<?php

declare(strict_types=1);

use ZF1\Session\Session;

/**
 * ZF1 View Helper: old()
 *
 * Repopulates form inputs with flashed input data:
 *   <input name="email" value="<?= $this->escape($this->old('email')) ?>">
 */
final class ZF1_View_Helper_Old extends Zend_View_Helper_Abstract
{
    /**
     * Retrieve flashed old input from the session.
     */
    public function old(?string $key = null, mixed $default = null): mixed
    {
        $old = Session::get('_old_input', []);
        if (! is_array($old)) {
            $old = [];
        }

        if ($key === null) {
            return $old;
        }

        return function_exists('data_get') ? data_get($old, $key, $default) : ($old[$key] ?? $default);
    }
}
