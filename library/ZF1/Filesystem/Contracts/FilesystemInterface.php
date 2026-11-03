<?php

declare(strict_types=1);

namespace ZF1\Filesystem\Contracts;

interface FilesystemInterface
{
    /**
     * Determine if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Determine if a file is missing.
     */
    public function missing(string $path): bool;

    /**
     * Get the contents of a file.
     */
    public function get(string $path): ?string;

    /**
     * Write contents to a file.
     *
     * @param  string|resource  $contents
     */
    public function put(string $path, mixed $contents): bool;

    /**
     * Prepend contents to a file.
     */
    public function prepend(string $path, string $data): bool;

    /**
     * Append contents to a file.
     */
    public function append(string $path, string $data): bool;

    /**
     * Delete one or more files.
     *
     * @param  string|array<string>  $paths
     */
    public function delete(string|array $paths): bool;

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool;

    /**
     * Get the file size in bytes.
     */
    public function size(string $path): int;

    /**
     * Get the file's last modified timestamp.
     */
    public function lastModified(string $path): int;

    /**
     * Get the MIME type of a file.
     */
    public function mimeType(string $path): string;

    /**
     * Get the absolute local filesystem path for a given relative path.
     */
    public function path(string $path = ''): string;

    /**
     * Get the public URL for a given relative path.
     */
    public function url(string $path = ''): string;

    /**
     * Store an uploaded file (from $_FILES or local path) with an auto-generated unique filename.
     *
     * @param  array<string, mixed>|string  $file
     * @return string|false Relative file path on success, false on failure
     */
    public function putFile(string $directory, array|string $file): string|false;

    /**
     * Store an uploaded file with a specific filename.
     *
     * @param  array<string, mixed>|string  $file
     * @return string|false Relative file path on success, false on failure
     */
    public function putFileAs(string $directory, array|string $file, string $name): string|false;

    /**
     * Stream a file as an HTTP attachment download response.
     *
     * @param  array<string, string>  $headers
     */
    public function download(string $path, ?string $name = null, array $headers = []): void;

    /**
     * Stream a file as an inline HTTP response (e.g. for image or PDF viewing).
     *
     * @param  array<string, string>  $headers
     */
    public function response(string $path, ?string $name = null, array $headers = []): void;

    /**
     * Get an array of all files in a directory.
     *
     * @return array<string> Relative file paths
     */
    public function files(string $directory = '', bool $recursive = false): array;

    /**
     * Get an array of all directories within a directory.
     *
     * @return array<string> Relative directory paths
     */
    public function directories(string $directory = '', bool $recursive = false): array;

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path): bool;

    /**
     * Recursively delete a directory and its contents.
     */
    public function deleteDirectory(string $directory): bool;
}
