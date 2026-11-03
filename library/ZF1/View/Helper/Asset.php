<?php

declare(strict_types=1);

/**
 * ZF1 View Helper: asset()
 *
 * Provides view access to the framework asset helper:
 *   <?= $this->asset('css/app.css', true) ?>
 */
final class ZF1_View_Helper_Asset extends Zend_View_Helper_Abstract
{
    /**
     * Generate an asset URL.
     *
     * @param  string  $path  Path relative to public directory
     * @param  bool|string|null  $version  Auto cache-busting timestamp (if true) or explicit version string
     * @param  bool|null  $secure  Force HTTPS if true, HTTP if false, auto if null
     */
    public function asset(string $path = '', bool|string|null $version = null, ?bool $secure = null): string
    {
        return asset($path, $version, $secure);
    }
}
