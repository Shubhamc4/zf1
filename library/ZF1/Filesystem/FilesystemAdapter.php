<?php

declare(strict_types=1);

namespace ZF1\Filesystem;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZF1\Filesystem\Contracts\FilesystemInterface;

final class FilesystemAdapter implements FilesystemInterface
{
    protected string $root;

    protected string $baseUrl;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  string  $root  Absolute path to the disk root
     * @param  string  $baseUrl  Public base URL (or empty if private disk)
     * @param  array<string, mixed>  $config
     */
    public function __construct(string $root, string $baseUrl = '', array $config = [])
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->config = $config;

        if (! is_dir($this->root)) {
            @mkdir($this->root, 0775, true);
        }
    }

    /**
     * Resolve and validate an absolute path within the disk root.
     */
    public function path(string $path = ''): string
    {
        $clean = ltrim(str_replace('\\', '/', $path), '/');

        // Prevent directory traversal
        if (str_contains($clean, '..')) {
            throw new InvalidArgumentException("Directory traversal detected in path: [{$path}]");
        }

        return $clean !== '' ? "{$this->root}/{$clean}" : $this->root;
    }

    /**
     * Get the public URL for a given relative path.
     */
    public function url(string $path = ''): string
    {
        $clean = ltrim(str_replace('\\', '/', $path), '/');

        if ($this->baseUrl === '') {
            return '';
        }

        return $clean !== '' ? "{$this->baseUrl}/{$clean}" : $this->baseUrl;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function missing(string $path): bool
    {
        return ! $this->exists($path);
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->path($path);

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $contents = file_get_contents($fullPath);

        return $contents !== false ? $contents : null;
    }

    public function put(string $path, mixed $contents): bool
    {
        $fullPath = $this->path($path);
        $this->ensureParentDirectoryExists($fullPath);

        if (is_resource($contents)) {
            $dest = fopen($fullPath, 'wb');
            if (! $dest) {
                return false;
            }
            stream_copy_to_stream($contents, $dest);
            fclose($dest);

            return true;
        }

        return file_put_contents($fullPath, (string) $contents, LOCK_EX) !== false;
    }

    public function prepend(string $path, string $data): bool
    {
        if ($this->exists($path)) {
            return $this->put($path, $data.$this->get($path));
        }

        return $this->put($path, $data);
    }

    public function append(string $path, string $data): bool
    {
        $fullPath = $this->path($path);
        $this->ensureParentDirectoryExists($fullPath);

        return file_put_contents($fullPath, $data, FILE_APPEND | LOCK_EX) !== false;
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $success = true;

        foreach ($paths as $p) {
            $fullPath = $this->path($p);
            if (is_file($fullPath)) {
                $deleted = @unlink($fullPath);
                if (! $deleted) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    public function copy(string $from, string $to): bool
    {
        $source = $this->path($from);
        $dest = $this->path($to);

        if (! is_file($source)) {
            return false;
        }

        $this->ensureParentDirectoryExists($dest);

        return copy($source, $dest);
    }

    public function move(string $from, string $to): bool
    {
        $source = $this->path($from);
        $dest = $this->path($to);

        if (! file_exists($source)) {
            return false;
        }

        $this->ensureParentDirectoryExists($dest);

        return rename($source, $dest);
    }

    public function size(string $path): int
    {
        $fullPath = $this->path($path);

        return is_file($fullPath) ? (int) filesize($fullPath) : 0;
    }

    public function lastModified(string $path): int
    {
        $fullPath = $this->path($path);

        return is_file($fullPath) ? (int) filemtime($fullPath) : 0;
    }

    public function mimeType(string $path): string
    {
        $fullPath = $this->path($path);

        if (is_file($fullPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $fullPath);
                finfo_close($finfo);
                if ($mime && $mime !== 'application/octet-stream') {
                    return $mime;
                }
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->guessMimeTypeFromExtension($extension);
    }

    /**
     * Store an uploaded file (from $_FILES array or local file path) with a unique hash filename.
     */
    public function putFile(string $directory, array|string $file): string|false
    {
        $extension = $this->extractExtension($file);
        $filename = bin2hex(random_bytes(16)).($extension !== '' ? ".{$extension}" : '');

        return $this->putFileAs($directory, $file, $filename);
    }

    /**
     * Store an uploaded file with a specific filename.
     */
    public function putFileAs(string $directory, array|string $file, string $name): string|false
    {
        $cleanDir = trim(str_replace('\\', '/', $directory), '/');
        $targetRelative = $cleanDir !== '' ? "{$cleanDir}/{$name}" : $name;
        $targetAbsolute = $this->path($targetRelative);

        $this->ensureParentDirectoryExists($targetAbsolute);

        // Case 1: Uploaded array from $_FILES
        if (is_array($file)) {
            if (! isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return false;
            }

            $tmpName = (string) $file['tmp_name'];

            if (is_uploaded_file($tmpName)) {
                return move_uploaded_file($tmpName, $targetAbsolute) ? $targetRelative : false;
            }

            return copy($tmpName, $targetAbsolute) ? $targetRelative : false;
        }

        // Case 2: String filepath
        if (is_file($file)) {
            if (is_uploaded_file($file)) {
                return move_uploaded_file($file, $targetAbsolute) ? $targetRelative : false;
            }

            return copy($file, $targetAbsolute) ? $targetRelative : false;
        }

        return false;
    }

    /**
     * Stream a file as an HTTP attachment download response.
     */
    public function download(string $path, ?string $name = null, array $headers = []): void
    {
        $this->sendStreamResponse($path, $name, $headers, 'attachment');
    }

    /**
     * Stream a file as an inline HTTP response (e.g. for image or PDF viewing).
     */
    public function response(string $path, ?string $name = null, array $headers = []): void
    {
        $this->sendStreamResponse($path, $name, $headers, 'inline');
    }

    protected function sendStreamResponse(string $path, ?string $name, array $headers, string $disposition): void
    {
        $fullPath = $this->path($path);

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            throw new RuntimeException("Cannot stream file: [{$path}] does not exist or is not readable.");
        }

        $filename = str_replace('"', '', $name ?: basename($fullPath));
        $mime = $headers['Content-Type'] ?? $this->mimeType($path);
        $size = (int) filesize($fullPath);

        // Clear existing buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (! headers_sent()) {
            header("Content-Type: {$mime}");
            header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
            header("Content-Length: {$size}");
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Expires: 0');

            foreach ($headers as $key => $value) {
                if (strcasecmp($key, 'Content-Type') !== 0 && strcasecmp($key, 'Content-Disposition') !== 0) {
                    header("{$key}: {$value}");
                }
            }
        }

        $handle = fopen($fullPath, 'rb');
        if ($handle) {
            while (! feof($handle)) {
                echo fread($handle, 1024 * 64);
                flush();
            }
            fclose($handle);
        }

        exit(0);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $dirPath = $this->path($directory);
        if (! is_dir($dirPath)) {
            return [];
        }

        $files = [];

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($this->root) + 1));
                    $files[] = $rel;
                }
            }
        } else {
            foreach (scandir($dirPath) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $itemPath = "{$dirPath}/{$item}";
                if (is_file($itemPath)) {
                    $cleanDir = trim(str_replace('\\', '/', $directory), '/');
                    $files[] = $cleanDir !== '' ? "{$cleanDir}/{$item}" : $item;
                }
            }
        }

        return $files;
    }

    public function directories(string $directory = '', bool $recursive = false): array
    {
        $dirPath = $this->path($directory);
        if (! is_dir($dirPath)) {
            return [];
        }

        $dirs = [];

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($this->root) + 1));
                    $dirs[] = $rel;
                }
            }
        } else {
            foreach (scandir($dirPath) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $itemPath = "{$dirPath}/{$item}";
                if (is_dir($itemPath)) {
                    $cleanDir = trim(str_replace('\\', '/', $directory), '/');
                    $dirs[] = $cleanDir !== '' ? "{$cleanDir}/{$item}" : $item;
                }
            }
        }

        return $dirs;
    }

    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->path($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return @mkdir($fullPath, 0775, true);
    }

    public function deleteDirectory(string $directory): bool
    {
        $dirPath = $this->path($directory);

        if (! is_dir($dirPath)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($dirPath);
    }

    protected function ensureParentDirectoryExists(string $fullPath): void
    {
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    protected function extractExtension(array|string $file): string
    {
        if (is_array($file) && ! empty($file['name'])) {
            return strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        }

        if (is_string($file)) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }

        return '';
    }

    protected function guessMimeTypeFromExtension(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'zip' => 'application/zip',
            'gz' => 'application/gzip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}
