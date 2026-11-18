<?php

declare(strict_types=1);

namespace ZF1\Database;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use Throwable;
use Traversable;
use ZF1\Support\Dumper;

/**
 * Modern fluent Paginator for database result sets and API responses.
 *
 * @template TKey of array-key
 * @template TValue of FluentRow|mixed
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
class Paginator implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The items for the current page.
     *
     * @var FluentCollection<TKey, TValue>
     */
    protected FluentCollection $items;

    /**
     * Total number of items across all pages.
     */
    protected int $total;

    /**
     * Number of items per page.
     */
    protected int $perPage;

    /**
     * The current page number.
     */
    protected int $currentPage;

    /**
     * The base path for pagination URLs.
     */
    protected string $path = '/';

    /**
     * The query parameter name for page number.
     */
    protected string $pageName = 'page';

    /**
     * Additional query string parameters to append to URLs.
     *
     * @var array<string, mixed>
     */
    protected array $query = [];

    /**
     * URL fragment (#anchor).
     */
    protected ?string $fragment = null;

    /**
     * Create a new Paginator instance.
     *
     * @param  FluentCollection<TKey, TValue>|array<TKey, mixed>  $items
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        FluentCollection|array $items,
        int $total,
        int $perPage = 15,
        int $currentPage = 1,
        array $options = []
    ) {
        $this->items = $items instanceof FluentCollection ? $items : new FluentCollection($items);
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);

        $this->path = $options['path'] ?? self::resolveCurrentPath();
        $this->pageName = $options['pageName'] ?? 'page';
        $this->query = $options['query'] ?? self::resolveCurrentQuery();
        $this->fragment = $options['fragment'] ?? null;
    }

    /**
     * Get the collection of items for the current page.
     *
     * @return FluentCollection<TKey, TValue>
     */
    public function items(): FluentCollection
    {
        return $this->items;
    }

    /**
     * Get the total number of items across all pages.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get the number of items per page.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the current page number.
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the total number of pages.
     */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * Get the index number of the first item on the current page.
     */
    public function firstItem(): ?int
    {
        return $this->total > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    /**
     * Get the index number of the last item on the current page.
     */
    public function lastItem(): ?int
    {
        return $this->total > 0 ? min($this->total, $this->firstItem() + $this->count() - 1) : null;
    }

    /**
     * Determine if there are enough items to split into multiple pages.
     */
    public function hasPages(): bool
    {
        return $this->total > $this->perPage;
    }

    /**
     * Determine if there are more items in the data source.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Determine if the paginator is on the first page.
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    /**
     * Determine if the paginator is on the last page.
     */
    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage();
    }

    /**
     * Get the URL for the next page, or null if on last page.
     */
    public function nextPageUrl(): ?string
    {
        return $this->hasMorePages() ? $this->url($this->currentPage + 1) : null;
    }

    /**
     * Get the URL for the previous page, or null if on first page.
     */
    public function previousPageUrl(): ?string
    {
        return $this->onFirstPage() ? null : $this->url($this->currentPage - 1);
    }

    /**
     * Get the URL for a given page number.
     */
    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parameters = array_merge($this->query, [$this->pageName => $page]);

        $queryString = http_build_query($parameters);
        $url = $this->path.($queryString !== '' ? '?'.$queryString : '');

        return $this->fragment ? $url.'#'.$this->fragment : $url;
    }

    /**
     * Add a set of query string values to the paginator URLs.
     *
     * @param  array<string, mixed>|string  $key
     */
    public function appends(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->query = array_merge($this->query, $key);
        } else {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * Set the base URL path for pagination links.
     */
    public function withPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set the URL #fragment anchor.
     */
    public function fragment(?string $fragment = null): static
    {
        $this->fragment = $fragment;

        return $this;
    }

    /**
     * The default view template used to render pagination links.
     */
    protected static string $defaultView = 'default';

    /**
     * Set the default pagination view name.
     */
    public static function defaultView(string $view): void
    {
        self::$defaultView = $view;
    }

    /**
     * Set the default pagination template to Bootstrap 5.
     */
    public static function useBootstrapFive(): void
    {
        self::$defaultView = 'bootstrap-5';
    }

    /**
     * Set the default pagination template to Bootstrap 4.
     */
    public static function useBootstrapFour(): void
    {
        self::$defaultView = 'bootstrap-4';
    }

    /**
     * Set the default pagination template to Tailwind CSS.
     */
    public static function useTailwind(): void
    {
        self::$defaultView = 'tailwind';
    }

    /**
     * Set the default pagination template to Simple (Previous / Next only).
     */
    public static function useSimple(): void
    {
        self::$defaultView = 'simple-bootstrap-5';
    }

    /**
     * Get the array of page elements and URLs for rendering sliding window pagination.
     *
     * @return array<int|string, string|null> Keys are page numbers or '...', values are URLs or null
     */
    public function elements(int $onEachSide = 2): array
    {
        $current = $this->currentPage();
        $last = $this->lastPage();

        if ($last <= 1) {
            return [];
        }

        $elements = [];
        $start = max(1, $current - $onEachSide);
        $end = min($last, $current + $onEachSide);

        if ($start > 1) {
            $elements[1] = $this->url(1);
            if ($start > 2) {
                $elements['...'] = null;
            }
        }

        for ($p = $start; $p <= $end; $p++) {
            $elements[$p] = $this->url($p);
        }

        if ($end < $last) {
            if ($end < $last - 1) {
                $elements['....'] = null;
            }
            $elements[$last] = $this->url($last);
        }

        return $elements;
    }

    /**
     * Render HTML pagination links using a customizable template.
     *
     * @param  string|null  $view  Custom view template name or file path
     * @param  array<string, mixed>  $data  Additional data to pass into view
     */
    public function links(?string $view = null, array $data = []): string
    {
        if (! $this->hasPages()) {
            return '';
        }

        $viewName = $view ?: static::$defaultView;

        return static::renderView($viewName, array_merge([
            'paginator' => $this,
            'elements' => $this->elements(),
            'currentPage' => $this->currentPage(),
            'lastPage' => $this->lastPage(),
            'total' => $this->total(),
            'perPage' => $this->perPage(),
            'hasPages' => $this->hasPages(),
            'onFirstPage' => $this->onFirstPage(),
            'hasMorePages' => $this->hasMorePages(),
            'previousPageUrl' => $this->previousPageUrl(),
            'nextPageUrl' => $this->nextPageUrl(),
            'firstItem' => $this->firstItem(),
            'lastItem' => $this->lastItem(),
        ], $data));
    }

    /**
     * Alias for links().
     *
     * @param  array<string, mixed>  $data
     */
    public function render(?string $view = null, array $data = []): string
    {
        return $this->links($view, $data);
    }

    /**
     * Render the given view template file with isolated data scope.
     *
     * @param  array<string, mixed>  $data
     */
    public static function renderView(string $viewName, array $data): string
    {
        $file = static::resolveViewFile($viewName);

        if ($file === null) {
            throw new RuntimeException("Pagination view template [{$viewName}] not found.");
        }

        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                include $__file;

                return (string) ob_get_clean();
            } catch (Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        return $render($file, $data);
    }

    /**
     * Resolve the template file path for the given view name.
     */
    public static function resolveViewFile(string $viewName): ?string
    {
        if (str_contains($viewName, '..')) {
            return null;
        }

        if (is_file($viewName)) {
            return $viewName;
        }

        $baseName = preg_replace('/\.phtml$/', '', $viewName);

        $candidates = [];

        if (function_exists('app_path')) {
            $candidates[] = app_path("views/scripts/pagination/{$baseName}.phtml");
            $candidates[] = app_path("views/pagination/{$baseName}.phtml");
            $candidates[] = app_path("views/scripts/{$baseName}.phtml");
        }

        if (class_exists(\ZF1\Foundation\Kernel::class)) {
            $appPath = \ZF1\Foundation\Kernel::getApplicationPath();
            $candidates[] = "{$appPath}/views/scripts/pagination/{$baseName}.phtml";
            $candidates[] = "{$appPath}/views/pagination/{$baseName}.phtml";
            $candidates[] = "{$appPath}/views/scripts/{$baseName}.phtml";
        }

        $candidates[] = getcwd()."/application/views/scripts/pagination/{$baseName}.phtml";
        $candidates[] = getcwd()."/application/views/pagination/{$baseName}.phtml";

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $stubFile = __DIR__."/stubs/pagination/{$baseName}.phtml.stub";
        if (is_file($stubFile)) {
            return $stubFile;
        }

        if ($baseName === 'default' && is_file(__DIR__.'/stubs/pagination/bootstrap-5.phtml.stub')) {
            return __DIR__.'/stubs/pagination/bootstrap-5.phtml.stub';
        }

        return null;
    }

    /**
     * Publish customizable pagination templates to the application views directory.
     *
     * @return array<string> List of published files
     */
    public static function publishViews(?string $targetDir = null, ?string $type = null, bool $force = false): array
    {
        $destinationDir = $targetDir ?: (function_exists('app_path') ? app_path('views/scripts/pagination') : (class_exists(\ZF1\Foundation\Kernel::class) ? \ZF1\Foundation\Kernel::getApplicationPath().'/views/scripts/pagination' : getcwd().'/application/views/scripts/pagination'));

        if (! is_dir($destinationDir)) {
            @mkdir($destinationDir, 0775, true);
        }

        $stubsDir = __DIR__.'/stubs/pagination';
        $published = [];

        $allTypes = ['bootstrap-5', 'bootstrap-4', 'simple-bootstrap-5', 'tailwind', 'default'];
        $typesToPublish = ($type !== null && $type !== '') ? [strtolower($type)] : $allTypes;

        foreach ($typesToPublish as $t) {
            $baseName = preg_replace('/\.phtml$/', '', $t);
            $destFile = $destinationDir."/{$baseName}.phtml";

            if (! $force && file_exists($destFile)) {
                continue;
            }

            $sourceStub = $stubsDir."/{$baseName}.phtml.stub";
            if (! file_exists($sourceStub) && $baseName === 'default') {
                $sourceStub = $stubsDir.'/bootstrap-5.phtml.stub';
            }

            if (file_exists($sourceStub)) {
                $content = (string) file_get_contents($sourceStub);
                file_put_contents($destFile, $content);
                $published[] = $destFile;
            }
        }

        return $published;
    }

    /**
     * Dump the paginator details and continue chaining.
     */
    public function dump(mixed ...$args): static
    {
        $payload = empty($args) ? $this->toArray() : [$this->toArray(), ...$args];

        if (class_exists(Dumper::class)) {
            Dumper::dump(...(is_array($payload) && ! isset($payload[0]) ? [$payload] : (array) $payload));
        } elseif (function_exists('dump')) {
            dump($this->toArray(), ...$args);
        } else {
            var_dump($this->toArray());
        }

        return $this;
    }

    /**
     * Dump the paginator details and terminate execution ("dump and die").
     */
    public function dd(mixed ...$args): never
    {
        if (class_exists(Dumper::class)) {
            if (empty($args)) {
                Dumper::dd($this->toArray());
            } else {
                Dumper::dd($this->toArray(), ...$args);
            }
        }

        if (function_exists('dd')) {
            dd($this->toArray(), ...$args);
        }

        var_dump($this->toArray());
        exit(1);
    }

    /**
     * Convert the paginator instance to an associative array for API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
        ];
    }

    /**
     * Convert the paginator to its JSON representation.
     */
    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->toArray(), $options | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Determine if an offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Get an item at a given offset.
     *
     * @return TValue|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Set the item at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$offset] = $value;
    }

    /**
     * Unset the item at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Count the number of items on the current page.
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Get an iterator for the items.
     *
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return $this->items->getIterator();
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Render the pagination links when cast to string.
     */
    public function __toString(): string
    {
        return $this->links();
    }

    /**
     * Resolve the current request path.
     */
    protected static function resolveCurrentPath(): string
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $parsed = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return '/';
    }

    /**
     * Resolve the current query string parameters excluding the page parameter.
     *
     * @return array<string, mixed>
     */
    protected static function resolveCurrentQuery(): array
    {
        $query = $_GET ?? [];
        unset($query['page']);

        return $query;
    }
}
