<?php

declare(strict_types=1);

/**
 * ZF1 View Helper: csrf()
 *
 * Provides view access to the CSRF token:
 *   <?= $this->csrf() ?> (outputs hidden input)
 *   <?= $this->csrf(false) ?> (outputs token string)
 */
final class ZF1_View_Helper_Csrf extends Zend_View_Helper_Abstract
{
    /**
     * Render the CSRF token input field or return the token string.
     */
    public function csrf(bool $asHtml = true): string
    {
        return $asHtml ? csrf_field() : csrf_token();
    }
}
