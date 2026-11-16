<?php

declare(strict_types=1);

/**
 * ZF1 View Helper: flash()
 *
 * Provides view access to flash notification messages:
 *   <?php if ($msg = $this->flash('success')) { ?> ... <?php } ?>
 */
final class ZF1_View_Helper_Flash extends Zend_View_Helper_Abstract
{
    /**
     * Retrieve a flash message by key or all flash messages.
     *
     * @return mixed|array<string, mixed>
     */
    public function flash(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            $keys = array_unique(array_merge(
                $_SESSION['_flash']['old'] ?? [],
                $_SESSION['_flash']['new'] ?? []
            ));
            $messages = [];
            foreach ($keys as $k) {
                $messages[$k] = ZF1\Session\Session::get($k);
            }

            return $messages;
        }

        return ZF1\Session\Session::get($key, $default);
    }
}
