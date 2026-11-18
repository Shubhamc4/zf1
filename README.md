# Zend Framework 1 for PHP 8.2

This is an updated version of the Zend Framework 1 package adapted to work with PHP 8.2.

## Background

Zend Framework 1 (ZF1) officially ended its life on September 28, 2016. However, many applications still rely on it, and with PHP moving forward, compatibility issues have emerged.

This package aims to provide a PHP 8.2 compatible version of Zend Framework 1, allowing legacy applications to continue functioning on modern PHP environments.

## PHP 8.2 Compatibility

This version of Zend Framework 1 has been updated to be fully compatible with PHP 8.2. Key updates include:

- Added `#[\ReturnTypeWillChange]` attributes to interface implementations (Iterator, Countable, ArrayAccess, etc.)
- Added `#[AllowDynamicProperties]` attribute to classes that use dynamic properties
- Replaced deprecated `utf8_encode()`/`utf8_decode()` with `mb_convert_encoding()`
- Updated string access syntax (replaced curly braces with square brackets)
- Fixed constructor compatibility issues
- Updated parameter default value ordering in method signatures
- Removed usage of deprecated PHP functions and features

For more detailed information about the PHP 8.2 compatibility changes, see the [PHP82_COMPATIBILITY.md](PHP82_COMPATIBILITY.md) file.

## Installation

```bash
composer require shubhamc4/zf1
```

## Quick Start & Scaffolding

This package provides a modern application foundation and the **`zf`** CLI tool.

To scaffold the root `zf` CLI runner, application Kernel, and Bootstrap in your project:

```bash
php vendor/bin/zf foundation:install
```

This creates:

- `./zf` (root executable CLI runner)
- `application/Kernel.php` (modern application lifecycle & path management)
- `application/Bootstrap.php` (extended bootstrap)

Once scaffolded, you can run commands directly using `./zf` or `php zf`:

```bash
php zf list
```

## Features & Modern Foundation

### 1. Command Line Interface (`zf`)

Built-in commands for queues, scheduling, scaffolding, system maintenance, and local development:

- `php zf about` — Display an overview of application environment, database, storage permissions, and diagnostics
- `php zf route:list` — List and filter all registered MVC routes, URL patterns, methods, and targets
- `php zf cache:clear` — Flush file caches, Zend_Cache pools, and database metadata caches
- `php zf serve {--port=8000}` — Start the PHP built-in local development web server
- `php zf make:command <Name>` — Scaffold a new CLI command in `application/Commands/`
- `php zf make:job <Name>` — Scaffold a new Queue Job in `application/Jobs/`
- `php zf queue:table` — Create database tables for jobs and failed jobs (`zend_jobs`, `zend_failed_jobs`)
- `php zf queue:work` — Start background queue worker
- `php zf queue:status` — Inspect queue metrics
- `php zf queue:retry {id|all}` — Retry failed jobs
- `php zf queue:failed` — List failed jobs
- `php zf queue:flush` / `php zf queue:forget` / `php zf queue:prune`
- `php zf schedule:run [name]` — Run due scheduled tasks, or run a specific scheduled task on-demand by name
- `php zf schedule:list` — View scheduled cron events, intervals, and names
- `php zf schedule:work` — Run a local daemon worker for the scheduler
- `php zf mail:layout {--publish} {--force}` — Inspect or publish customizable responsive email layout template
- `php zf console:publish {--force}` — Publish customizable web console HTML/CSS template to application/views/scripts/console/index.phtml
- `php zf error:publish {--code=} {--path=} {--force}` — Publish customizable error view templates to application/views/error/{code}.phtml
- `php zf down` / `php zf up` — Maintenance mode management (supports `--publish` to publish editable 503 error view to `application/views/error/503.phtml`, `--template=` for custom views, and secret bypass tokens)
- `php zf db:backup` — Automated gzip database backup with retention pruning
- `php zf db:show` — Display an overview of database tables, rows, storage size, and engine
- `php zf db:table <table>` — Inspect table schema, columns, data types, and indexes
- `php zf db:wipe {--force} {--drop-views}` — Drop all tables, views, and types in the active database
- `php zf optimize` / `php zf optimize:clear` — Pre-cache database metadata and warm application caches, or clear all optimization caches

### 2. Modern Application Kernel (`ZF1\Foundation\Kernel`)

- Auto-detects and loads `.env` variables via `vlucas/phpdotenv`
- Supports both PHP array configuration files (`application/configs/application.php`) and traditional INI files (`application/configs/application.ini`)
- Automatic project path resolution (`base_path()`, `app_path()`, `storage_path()`, `public_path()`, `config_path()`)

### 3. Background Job Queue (`ZF1\Queue\Queue`)

- Database-backed (`zend_jobs`, `zend_failed_jobs`) and synchronous drivers
- Automatic job deduplication and unique payloads
- Exponential backoff and retry limits
- Graceful shutdown on `SIGTERM` / `SIGINT` signals

### 4. Cron Task Scheduler (`ZF1\Schedule\Schedule`)

- Fluent scheduling syntax (`->daily()`, `->everyFiveMinutes()`, `->weekdays()`)
- Pure PHP 5-part cron parser (`CronExpression`)
- Name schedules for easy tracking and manual execution:
  ```php
  $schedule->command('db:backup')->name('nightly-backup')->dailyAt('02:00');
  $schedule->job(PruneLogsJob::class)->name('prune-logs')->weekly();
  ```
- Run any scheduled task immediately on-demand by name:
  ```bash
  php zf schedule:run nightly-backup
  ```
- Mutex-based overlap prevention and background execution

### 5. Universal Helpers

Autoloaded framework helpers available globally:

- **Environment & App**: `env($key, $default)`, `app_env()`, `is_production()`, `is_local()`, `is_testing()`, `is_development()`, `app_name()`, `app_url($path)`, `is_cli()`
- **Paths & Assets**: `base_path()`, `app_path()`, `storage_path()`, `public_path()`, `config_path()`, `url($path)`, `asset($path, $version)`
- **Database & Cache**: `db($conn)`, `db_transaction(fn ($db) => ...)`, `cache($key, $default)`, `Cache::remember($key, $ttl, fn () => ...)`, `session($key, $default)`
- **Strings**: `str_uuid()`, `str_random($len)`, `str_mask($str, $char, $index, $len)`, `str_headline($str)`, `str_slug($title)`, `str_limit($val, $limit)`, `str_contains_any($str, $needles)`, `str_starts_with_any()`, `str_ends_with_any()`
- **Arrays & Collections**: `array_only($arr, $keys)`, `array_except($arr, $keys)`, `array_wrap($val)`, `array_pull($arr, $key)`, `array_key_by($arr, $key)`, `array_first($arr, $fn)`, `array_last($arr, $fn)`, `array_has($arr, $key)`, `array_forget($arr, $keys)`, `data_get($target, $key)`, `data_set($target, $key, $val)`, `pluck($data, $cols)`
- **Request & Redirects**: `redirect($to, $status)`, `back($fallback, $status)`, `old($key, $default)`, `optional($val, $fn)`, `validator($data, $rules)`, `errors($key)`, `error($key)`, `csrf_token()`, `csrf_field()`, `csrf_verify($token)`, `request_wants_json()`, `request_bearer_token()`, `get_client_ip()`
- **Debugging & Inspection**: `dump(...$vars)`, `dd(...$vars)`, `dump_if($cond, ...$vars)`, `dd_if($cond, ...$vars)`, `dlog(...$vars)`, `dtrace($e)`, `benchmark($fn, $runs)`, `dump_sql($query, $bind)`, `dd_sql($query, $bind)`
- **Numbers & Formatting**: `formatAmount($amt)`, `displayAmount($amt, $currency)`, `spellSeconds($secs)`, `formatBytes($bytes)`
- **Filesystem & Mail**: `storage($disk)`, `mailer($to, $name)`, `render_email_layout($content, $subject, $data)`, `dispatch($job)`
- **Alerts**: `discord_notify($message, $level, $fields, $title)`

### 6. Database Facade & Transactions (`ZF1\Database\DB`)

Modern static database interface with automatic transaction rollback and deadlock retries:

```php
use ZF1\Database\DB;

// Automatic transaction management (commits on success, rolls back on exception)
$orderId = db_transaction(function ($db) use ($cart) {
    $db->insert('orders', [
        'customer_id' => $cart['customer_id'],
        'total' => $cart['total'],
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $orderId = $db->lastInsertId();

    foreach ($cart['items'] as $item) {
        $db->insert('order_items', [
            'order_id' => $orderId,
            'product_id' => $item['id'],
            'qty' => $item['qty'],
        ]);
    }

    return $orderId;
});

// Fluent helper methods
$users = DB::select('users', ['id', 'name', 'email'], ['status = ?' => 1], 'id DESC', 20);
$userId = DB::insert('users', ['name' => 'Alice', 'email' => 'alice@example.com']);
DB::update('users', ['status' => 2], ['id = ?' => $userId]);
DB::delete('users', ['id = ?' => $userId]);

// Modern Query Builder terminal helpers (returns FluentRow and FluentCollection):
$user    = DB::table('users')->where('status', 1)->first(); // FluentRow (extends stdClass, has toArray(), dd())
$user    = DB::table('users')->find(42);                    // Find by primary key 'id'
$users   = DB::table('users')->where('status', 1)->get();   // FluentCollection (has toArray(), pluck(), dd())
$email   = DB::table('users')->where('id', 42)->value('email'); // Single scalar value
$emails  = DB::table('users')->pluck('email');              // Array of column values ['a@b.com', ...]
$pairs   = DB::table('users')->pluck('name', 'id');         // Key-value pairs [id => name]
$count   = DB::table('users')->where('status', 1)->count(); // Total record count (auto subqueries for GROUP BY/DISTINCT)
$exists  = DB::table('users')->where('status', 1)->exists(); // Boolean check
$max     = DB::table('orders')->max('total');               // Aggregates: min, max, sum, avg

// Fluent mutations directly on the builder:
DB::table('users')->where('id', 42)->update(['status' => 1]); // Returns affected row count
DB::table('sessions')->where('last_activity', '<', time() - 86400)->delete();
DB::table('audit_logs')->insert(['action' => 'login', 'user_id' => 1]);
$newId = DB::table('users')->insertGetId(['name' => 'Alice', 'email' => 'alice@test.com']);

// Batch chunking (memory-safe streaming for large tables):
DB::table('orders')->where('status', 'pending')->chunk(100, function ($orders, $page) {
    foreach ($orders as $order) {
        // Process order...
    }
});

// Fluent row and collection inspections (using Dumper with UI card / terminal colors):
DB::table('users')->find(42)->dd();                         // Dump row and die
$userArray  = DB::table('users')->find(42)->toArray();      // Convert row to array
$usersArray = DB::table('users')->get()->toArray();         // Convert collection to array

// FluentCollection power methods:
$names      = DB::table('users')->get()->implode('name', ', '); // "Alice, Bob, Carol"
$hasAdmin   = DB::table('users')->get()->contains('role', 'admin');
$totalRev   = DB::table('orders')->get()->sum('total');
$avgScore   = DB::table('reviews')->get()->avg('rating');
$byDept     = DB::table('employees')->get()->groupBy('department');
$sorted     = DB::table('users')->get()->sortByDesc('created_on');

// Modern where & date reporting clauses:
$active     = DB::table('users')->whereIn('id', [1, 2, 3])->get();
$others     = DB::table('users')->whereNotIn('role', ['banned', 'guest'])->get();
$verified   = DB::table('users')->whereNotNull('email_verified_at')->get();
$unverified = DB::table('users')->whereNull('email_verified_at')->get();
$diffCols   = DB::table('tickets')->whereColumn('updated_at', '>', 'created_at')->get();
$today      = DB::table('orders')->whereDate('created_at', '2026-11-18')->get();
$thisYear   = DB::table('orders')->whereYear('created_at', 2026)->get();
$range      = DB::table('orders')->whereBetween('total', [50, 200])->get();
$searched   = DB::table('users')->whereLike('name', '%alice%')->get();
$rawWhere   = DB::table('users')->whereRaw('status = ? AND points > ?', [1, 100])->get();

// Reporting date ranges (inclusive, includes full end-of-day timestamp):
$monthlyReport = DB::table('sales')->whereDateBetween('created_at', '2026-11-01', '2026-11-30')->get();
// Or flexible request filter: automatically handles from-only, to-only, both, or neither:
$filterReport  = DB::table('sales')->whereDateRange('created_at', $from, $to)->get();

// Ordering & pagination helpers:
$latest     = DB::table('orders')->latest()->first();        // ORDER BY created_at DESC
$paged      = DB::table('orders')->latest()->skip(10)->take(10)->get();
$random     = DB::table('quotes')->inRandomOrder()->first();

// Inspect raw runnable SQL with interpolated bindings (Laravel-style):
$rawSql = DB::table('user')->where('status', 1)->toRawSql();

// Dump the query SQL and terminate execution ("dump and die"):
DB::table('user')->where('status', 1)->ddSql();

// Dump the query SQL and continue chaining:
$users = DB::table('user')->where('status', 1)->dumpSql()->get();

// Fluent Pagination (auto-resolves ?page= from request):
$paginator = DB::table('users')->where('status', 1)->latest()->paginate(15);
$simple    = DB::table('logs')->latest()->simplePaginate(25);

// Paginator helpers:
$paginator->total();          // Total records (50)
$paginator->currentPage();    // Active page (1)
$paginator->lastPage();       // Last page number (4)
$paginator->hasPages();       // bool
$paginator->previousPageUrl();// ?page=1 or null
$paginator->nextPageUrl();    // ?page=3

// Preserve query parameters or append anchor fragment:
$paginator->appends(['search' => 'alice', 'role' => 'admin'])->fragment('users-table');

// Render in your view template (.phtml):
<?= $paginator->links() ?>            // Render default Bootstrap 5 markup
<?= $paginator->links('tailwind') ?>  // Render modern Tailwind markup
<?= $paginator ?>                     // Auto-renders via __toString()
```

#### Publish & Customize Pagination Templates:

Instead of hardcoded HTML, pagination templates are fully customizable per project. Publish the templates to `application/views/scripts/pagination/`:

```bash
# Publish all templates (bootstrap-5, bootstrap-4, simple-bootstrap-5, tailwind)
php zf pagination:publish

# Publish a specific template
php zf pagination:publish --type=tailwind

# Overwrite existing templates
php zf pagination:publish --force
```

Configure your application default style in `Bootstrap.php`:

```php
use ZF1\Database\Paginator;

Paginator::useTailwind();        // Default to Tailwind CSS
Paginator::useBootstrapFive();   // Default to Bootstrap 5
Paginator::useSimple();          // Default to simple Previous / Next
Paginator::defaultView('custom');// application/views/scripts/pagination/custom.phtml
```
```

### 7. Modern Cache Manager & Remember Pattern (`ZF1\Cache\Cache`)

Wrap slow queries or computations with automatic caching:

```php
use ZF1\Cache\Cache;

// The remember pattern: returns cached data if exists, otherwise runs callback, caches, and returns
$permissions = Cache::remember("user_permissions_{$userId}", 3600, function () use ($userId) {
    return (new UserRoleModel)->fetchPermissions($userId);
});

// Cache operations
Cache::put('key', 'value', 3600);
$value = Cache::get('key', 'default');
Cache::has('key');
Cache::forget('key');
Cache::flush();
```

### 8. Base Application Controller (`ZF1\Foundation\Controller`)

Extend `ZF1\Foundation\Controller` for modern API, SPA, and download capabilities:

```php
use ZF1\Foundation\Controller;

final class ApiController extends Controller
{
    public function usersAction(): void
    {
        // 1. Reading JSON request payloads (Vue / React / Axios php://input)
        $email = $this->jsonPayload('user.email');

        // 2. Simple parameter validation
        $data = $this->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,editor,viewer',
        ]);

        // 3. Standard JSON API envelopes
        $this->success(['userId' => 42], 'User registered successfully.');
    }

    public function downloadInvoiceAction(): void
    {
        // 4. File download helper
        $this->download(storage_path('invoices/inv-1001.pdf'));
    }

    public function updateProfileAction(): void
    {
        // 5. Fluent redirects with flash messages & old input retention
        $this->redirect('/dashboard')
            ->withSuccess('Profile updated successfully!')
            ->with('status', 'active');

        // Redirect back to previous page with errors and input:
        $this->back()
            ->withErrors(['email' => 'Invalid email address'])
            ->withInput();

        // Redirect to named route or action:
        $this->redirect()->route('user_profile', ['id' => 42]);
        $this->redirect()->action('index', 'users');
    }
}
```

### 9. Modern Session Store & Flash Messages (`ZF1\Session\Session`)

Seamlessly integrates with `Zend_Session`, `Zend_Session_Namespace`, and native `$_SESSION` with dot-notation support:

```php
use ZF1\Session\Session;

// 1. Read & write using dot-notation
session(['user.profile.name' => 'Jane Doe', 'user.role' => 'admin']);
$name = session('user.profile.name'); // 'Jane Doe'
$role = Session::get('user.role');

// 2. Inspection & management
Session::has('user.role');      // true
Session::missing('user.age');   // true
Session::pull('user.role');     // gets and deletes
Session::forget('user.profile');
Session::all();

// 3. One-request flash messages (auto-purged on subsequent request)
Session::flash('success', 'Order created successfully!');
// Or via helper:
session()->flash('info', 'Profile updated');

// 4. Lifecycle & security
Session::regenerate(); // Regenerates session ID to prevent session fixation
Session::invalidate(); // Destroys session and regenerates fresh session ID
Session::token();      // Active CSRF token
```

#### In `.phtml` Views:

```html
<!-- Flash notifications -->
<?php if ($msg = $this->flash('success')) { ?>
<div class="alert alert-success"><?= $this->escape($msg) ?></div>
<?php } ?>

<!-- Reading session data -->
<span
  >Logged in as: <?= $this->escape($this->session('user.profile.name')) ?></span
>

<!-- CSRF Field & Old Input -->
<form method="POST">
  <?= $this->csrf() ?>
  <input
    type="text"
    name="email"
    value="<?= $this->escape($this->old('email')) ?>"
  />
  ...
</form>
```

### 10. Dedicated Validation Engine (`ZF1\Validation\Validator`)

Powerful, standalone validation engine inspired by modern frameworks. Features 30+ built-in rules, database uniqueness and existence checks, custom closure rules, global rule extensions, and automatic 422 JSON envelopes or flash redirect responses:

```php
use ZF1\Validation\Validator;

// 1. Manual Validation:
$validator = Validator::make($data, [
    'email' => 'required|email|max:255',
    'password' => 'required|string|min:8|confirmed',
    'age' => 'required|integer|between:18,65',
    'avatar' => 'nullable|url',
    'role' => 'required|in:admin,editor,subscriber',
    'username' => 'required|alpha_dash|unique:users,username',
], [
    'email.required' => 'We need your email address to register!',
], [
    'username' => 'User Handle',
]);

if ($validator->fails()) {
    // 1. Retrieve single error directly from validator:
    $emailError = $validator->error('email'); // or $validator->first('email')
    $hasEmailError = $validator->hasError('email');

    // 2. Or from MessageBag:
    $errors = $validator->errors();
    $firstError = $errors->first('email');
    $onlyEmailAndName = $errors->only('email', 'name'); // MessageBag filtered to specific keys
    $withoutPassword = $errors->except('password');     // MessageBag excluding specific keys
    $allErrors = $errors->all();
}

// 3. Extract only or except from validated data:
$credentials = $validator->only('email', 'password');
$profileData = $validator->except('password_confirmation');

// 4. Fluent Safe Input wrapper:
$safe = $validator->safe()->only(['email', 'name']);
$validated = $validator->validated(); // Returns all passed inputs

// 2. Custom Closure Rules:
Validator::make($data, [
    'coupon' => [
        'required',
        function ($attribute, $value, $fail) {
            if (! CouponService::isValid($value)) {
                $fail('The :attribute has expired.');
            }
        },
    ],
]);

// 3. Register Global Rule Extensions:
Validator::extend('strong_password', function ($attribute, $value) {
    return is_string($value) && preg_match('/[A-Z]/', $value) && preg_match('/[0-9]/', $value);
}, 'The :attribute must contain at least one uppercase letter and one number.');
```

#### In Controllers:

```php
// In any controller extending ZF1\Foundation\Controller:
public function storeAction(): void
{
    // Auto-validates: sends 422 JSON if request wants JSON, or redirects back with errors & old input:
    $data = $this->validate([
        'title' => 'required|string|max:100',
        'body' => 'required|string|min:10',
    ]);
}
```

#### In `.phtml` Views:

```html
<form method="POST">
  <?= $this->csrf() ?>

  <div class="form-group">
    <label>Email</label>
    <input
      type="email"
      name="email"
      value="<?= $this->escape($this->old('email')) ?>"
      class="form-control <?= $this->errors()->class('email', 'is-invalid') ?>"
    />

    <?php if ($this->errors()->has('email')): ?>
    <div class="invalid-feedback">
      <?= $this->escape($this->errors('email')) ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($this->errors()->any()): ?>
  <div class="alert alert-danger">Please fix the highlighted form errors.</div>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary">Submit</button>
</form>
```

### 11. Mail Service & Email Layout System (`ZF1\Mail\Mailer`, `ZF1\Mail\EmailLayout`)

- Fluent wrapper around `Zend_Mail` with SMTP transport auto-configured from `.env`:
  ```ini
  # Mail Configuration
  MAIL_ACTIVE=false
  MAIL_HOST=localhost
  MAIL_PORT=25
  MAIL_USERNAME=
  MAIL_PASSWORD=
  MAIL_ENCRYPTION=
  TEST_MAIL=shubham@inerun.com
  ```
- Send emails with fluent chaining:

  ```php
  Mailer::to('user@example.com', 'John Doe')
      ->setSubject('Welcome to our application!')
      ->setBodyHtml('<p>Thank you for joining.</p>')
      ->addAttachment('/path/to/invoice.pdf')
      ->send();

  // Or using the helper:
  mailer('user@example.com')
      ->setSubject('Password Reset')
      ->setBody('<p>Reset link...</p>')
      ->send();

  // Sending with payload variables ({name}, {{email}}, etc.):
  Mailer::to('user@example.com')
      ->setSubject('Welcome to {app_name}, {name}!')
      ->setBody('<p>Hi {name}, your email is {{email}}.</p>', [
          'name' => 'John Doe',
          'email' => 'user@example.com',
      ])
      ->send();

  // Fluent chaining with payload variables:
  Mailer::to('user@example.com')
      ->with(['name' => 'John Doe', 'email' => 'user@example.com'])
      ->setSubject('Welcome to {app_name}, {name}!')
      ->setBody('<p>Hi {name}, your email is {{email}}.</p>')
      ->send();

  // Sending raw HTML without the layout:
  Mailer::to('user@example.com')
      ->withoutLayout()
      ->setSubject('Raw Notification')
      ->setBodyHtml('<div>Raw content</div>')
      ->send();
  ```

- **Email Layout System**:
  - Automatically wraps email HTML and plain text inside a responsive card layout with header logo and footer copyright.
  - Automatically avoids double-wrapping if the content is already a complete `<!DOCTYPE html>` or `<html>` document.
  - Automatically converts plain text with linebreaks into styled HTML paragraphs.
  - Automatically generates a high-quality plain-text alternative if no text body is explicitly set.
  - **Logo Fallback Hierarchy**:
    1. Explicit logo passed in payload (`'logo'`, `'logo_url'`)
    2. `public/logo.png`
    3. `public/logo-dark.png`
    4. `public/logo-light.png`
    5. Falls back to `APP_NAME` as stylized text.
    - Caches logo discovery to eliminate redundant filesystem I/O in batch dispatches.
  - **Template Variable Interpolation & Security**:
    - Supports `{key}`, `{{key}}`, `{ key }`, and `{{ key }}` in both the subject and the body.
    - Standard variables: `{app_name}`, `{app_url}`, `{year}`, `{date}`, `{time}`, `{logo_url}`.
    - Single-use token slot isolation prevents template injection and double-interpolation leaks from user input.
    - URL schemes are sanitized against dangerous protocols (`javascript:`, `vbscript:`, `data:text/html`).
    - Globally shared variables can be registered application-wide via `Mailer::share(['support_email' => 'help@example.com'])` or `EmailLayout::share(...)`.
  - **Resolution hierarchy**:
    1. If a published layout exists at `application/views/scripts/emails/layout.phtml`, the project-specific layout is used.
    2. Otherwise, the built-in responsive default layout is used automatically.
- **Publishing Email Layout**:
  - Publish the customizable template to your project using the CLI:
    ```bash
    php zf mail:layout --publish
    # Or force overwrite if already published:
    php zf mail:layout --publish --force
    ```
  - Or programmatically:
    ```php
    EmailLayout::publish(); // or Mailer::publishLayout();
    ```
- `MAIL_ACTIVE=false` safely skips SMTP transmission and logs to debug log.
- `TEST_MAIL` reroutes all outgoing emails in non-production environments (`APP_ENV !== 'production'`) to one or more comma-separated test addresses and annotates original recipients in the subject (`[TEST: original@example.com] Subject`). Validates email formats before overriding.
- CRLF injection protection automatically strips carriage returns and newlines from dynamic subjects.
- Failure tracking via `Mailer::$lastError` and automatic debug logging.

### 12. Interactive Web Console & Terminal (`ZF1\Console\WebConsole`)

A browser-based terminal interface for running `zf` console commands securely with real-time SSE streaming, syntax-highlighted ANSI output, command history, and instant search.

- **Default Route**: `/zf-console`
- **Zero Config**: Ready out of the box in local and development environments.
- **Maintenance Mode Access**: Accessible even while the application is down (`php zf down`), allowing authorized administrators to perform maintenance tasks, inspect status, and execute `php zf up` directly from the web terminal.
- **Environment Configuration (`.env`)**:
  ```ini
  # Web Console
  ZF_CONSOLE_ENABLED=true
  ZF_CONSOLE_PATH=/zf-console
  ZF_CONSOLE_ALLOWED_IPS=127.0.0.1,::1,192.168.*
  ZF_CONSOLE_ALLOW_MAINTENANCE=true
  ```

#### Access Restriction & Authorization

In `production`, access is locked by default. Restrict who can access the web console in `application/Bootstrap.php`:

```php
class Bootstrap extends \ZF1\Foundation\Bootstrap
{
    /**
     * Authorize access to the Web Console.
     */
    public function authorizeWebConsole(\Zend_Controller_Request_Http $request): bool
    {
        // Example: Only permit logged-in administrators
        return ! empty($this->roleid) && $this->roleid === 1;
    }
}
```

Or register a gate closure anywhere (e.g. in `public/index.php` or `Kernel`):

```php
WebConsole::auth(function ($request, $bootstrap) {
    return isset($_SESSION['user']) && $_SESSION['user']['is_admin'] === true;
});
```

#### Customizing the Console & UI

Configure paths, priority groups, command filtering, and styling in `App\Kernel`:

```php
class Kernel extends \ZF1\Foundation\Kernel
{
    protected static function configureWebConsole(): void
    {
        WebConsole::path('/zf-console')
            ->title('TigmooBiz Terminal')
            ->homeUrl('/')
            ->theme('dark') // 'dark', 'light', or 'auto'
            ->priorityGroups(['csr', 'dispatch', 'vsr', 'general', 'queue'])
            ->hideCommands(['system:down', 'db:backup']) // Blacklist sensitive commands
            ->allowedIps(['127.0.0.1', '192.168.1.*'])
            ->redirectOnForbidden('/error/unauthorized') // Optional custom redirect (defaults to project ErrorController)
            ->onlyProjectCommands(false); // Default false shows all framework + app commands
    }
}
```

#### Publishing and Customizing the View Template

To customize the HTML, CSS styles, or keyboard shortcuts, publish the template into your application:

```bash
php zf console:publish
# Or force overwrite if already published:
php zf console:publish --force
```

This publishes the template to `application/views/scripts/console/index.phtml`.

### 13. Database Profiler & Slow Query Logger (`ZF1\Database\DatabaseProfiler`)

- Automatically logs slow queries exceeding configurable threshold (e.g. `DB_SLOW_QUERY_MS=500`).
- Configurable via `.env`:
  ```ini
  DB_LOGGING=true
  DB_SLOW_QUERY_MS=500
  ```

### 14. Modern Error Handling & Error Views (`ZF1\View\ErrorRenderer`)

A modern error handling system featuring rich diagnostics during development, clean status pages in production, automatic project view overrides, chronological stack traces, and unified JSON handling.

- **Developer Debug Mode (`APP_DEBUG=true`)**:
  - Full-screen dark developer diagnostics screen inspired by Laravel Ignition.
  - Exception class, HTTP status badge, and primary error message.
  - Prioritizes project controllers in the error header and source code preview over framework drivers or models (e.g. highlights `ServiceorderController.php:50` when a model query fails).
  - Interactive source code preview with 15–20 lines centered around the error line and highlighted syntax.
  - Comprehensive inspection tabs for Request, Parameters (`$_GET`, `$_POST`, JSON payload), Headers, Session, and Server/Environment variables (with sensitive keys masked).
  - One-click copy buttons for exception messages, file locations, and clean stack traces (works seamlessly in both HTTPS and non-secure HTTP local development).

- **Production Mode (`APP_DEBUG=false`)**:
  - Sleek, modern, responsive status cards for standard HTTP errors (`400`, `401`, `403`, `404`, `419`, `429`, `500`, `503`).
  - Dark/light system theme support, clean typography, friendly messages, and return home/back navigation.

- **Project-Level View Overrides (`application/views/error/{code}.phtml`)**:
  - Customize any error page project-wise simply by creating the corresponding `.phtml` file:
    1. `application/views/error/{code}.phtml`
    2. `application/views/scripts/error/{code}.phtml`
  - Customize the master wrapper layout for all error pages at `application/views/error/layout.phtml`.
  - If a project view does not exist, the framework seamlessly falls back to its built-in default stubs.

- **Publishing Error Views**:
  - Publish default error templates into your project views directory for immediate customization:

    ```bash
    # Publish all error views and layout stub:
    php zf error:publish

    # Or publish only a specific status code (e.g., 404 or 500):
    php zf error:publish --code=404

    # Publish to a custom directory:
    php zf error:publish --path=application/views/scripts/error

    # Force overwrite existing files:
    php zf error:publish --force
    ```

- **Laravel-Style `abort()`, `abort_if()`, and `abort_unless()` Helpers**:
  Easily interrupt execution and trigger standard HTTP error handling from anywhere (controllers, models, services):

  ```php
  // Immediate abort with status code and optional custom message/headers:
  abort(404);
  abort(403, 'You do not have permission to access this area.');
  abort(429, 'Rate limit exceeded. Please wait.', ['Retry-After' => '60']);

  // Conditional aborts (accepts booleans or Closures):
  abort_if(! $user, 404);
  abort_if($user->is_banned, 403, 'Account is suspended.');
  abort_if(fn() => $this->isRateLimited(), 429);

  abort_unless($user, 404, 'User not found.');
  abort_unless($user->can('manage-orders'), 403, 'Access denied.');
  ```

- **In `ErrorController`**:

  ```php
  use ZF1\Foundation\Controller;

  final class ErrorController extends Controller
  {
      public function errorAction(): void
      {
          // Automatically handles 404, 500, or HTTP exceptions,
          // respects APP_DEBUG, and serves project or default views:
          $this->renderError();
      }
  }
  ```

  _(You can also scaffold a modern `ErrorController.php` anytime with `php zf foundation --errors`.)_

- **Missing `ErrorController` Graceful Fallback**:
  - In modular applications or new projects where `ErrorController.php` is missing or not dispatchable, the framework's `Zend_Controller_Plugin_ErrorHandler` intercepts the error and directly renders via `ErrorRenderer`, completely preventing legacy `Invalid controller specified (error)` loop exceptions and exposing the real underlying error immediately.

- **Unified Web Exception & Fatal Shutdown Handling**:
  - `UnifiedErrorHandler` intercepts uncaught exceptions and fatal shutdown errors, automatically rendering the rich debug inspector (in debug mode) or modern 500 error page (in production) instead of a blank white screen.

- **JSON / AJAX API Responses**:
  - Requests expecting JSON (`Accept: application/json`, `X-Requested-With: XMLHttpRequest`, or `request_wants_json()`) automatically receive properly structured JSON error envelopes with sanitized messages in production and stack traces in debug mode:
    ```json
    {
      "message": "Invalid database table specified",
      "code": 500,
      "exception": "Zend_Db_Statement_Exception",
      "file": "/Users/work/Developer/app/application/modules/purchase/controllers/ServiceorderController.php",
      "line": 50,
      "trace": [
        "#0 /Users/work/Developer/app/application/modules/purchase/controllers/ServiceorderController.php(50): Application_Model_Album->fetchData(...)",
        "#1 /Users/work/Developer/app/application/models/Album.php(39): Zend_Db_Adapter_Abstract->fetchAll(...)",
        "... [+6 vendor/framework frames hidden]"
      ]
    }
    ```

- **Theme, Layout & Sensitive Keys Configuration**:
  Customize themes (`'dark'`, `'light'`, or `'auto'`), custom master layout templates, and register sensitive keys to mask on error screens by overriding `configureErrorRenderer()` or properties in `App\Kernel` (or anywhere in `Bootstrap`):

  ```php
  namespace App;

  use ZF1\Foundation\Kernel as BaseKernel;
  use ZF1\View\ErrorRenderer;

  final class Kernel extends BaseKernel
  {
      // Option A: Via static properties:
      protected static string $errorTheme = 'dark'; // 'dark', 'light', or 'auto'
      protected static ?string $errorLayout = 'views/error/layout.phtml';
      protected static array $sensitiveKeys = ['stripe_key', 'pin', 'custom_token', 'private_key'];

      // Option B: Via fluent configure hook:
      protected static function configureErrorRenderer(): void
      {
          ErrorRenderer::theme('dark')
            ->layout('views/error/layout.phtml')
            ->addSensitiveKeys(['stripe_secret', 'pin', 'ssn']);
      }
  }
  ```

  Or programmatically in `application/Bootstrap.php`:

  ```php
  \ZF1\View\ErrorRenderer::theme('dark')
      ->layout('views/error/layout.phtml')
      ->addSensitiveKeys(['api_token', 'card_number']);
  ```

- **Clean Exception Messages & Isolated Traces**:
  - Automatically isolates error messages from appended file and stack trace strings (e.g., legacy Zend broker trace concatenations or PHP file traces), ensuring error titles, descriptions, and Discord alerts remain concise and clean.

- **HTTP Exceptions & Abort Helpers (`abort`, `abort_if`, `abort_unless`)**:
  - Laravel-style abort helpers are available globally and as methods on `ZF1\Foundation\Controller`:

    ```php
    // In any controller, model, or service:
    abort(404, 'Post not found');
    abort(403, 'Unauthorized access to this resource');
    abort(429, 'Rate limit exceeded', ['Retry-After' => '60']);
    abort(503, 'System undergoing scheduled database migration');

    // Conditional aborts:
    abort_if(! $user, 404);
    abort_if($user->is_suspended, 403, 'Account suspended');
    abort_unless($user->hasPermission('admin'), 403, 'Admin access required');

    // Inside controllers ($this->abort, $this->abortIf, $this->abortUnless):
    $this->abortIf(! $invoice, 404, 'Invoice not found');
    $this->abortUnless($this->canAccess(), 403);
    ```

  - Standard HTTP exception classes (`ZF1\Exceptions\*`):
    - `HttpException` (base exception with status code & custom headers)
    - `BadRequestHttpException` (400)
    - `UnauthorizedHttpException` (401)
    - `AccessDeniedHttpException` (403)
    - `NotFoundHttpException` (404)
    - `MethodNotAllowedHttpException` (405)
    - `TooManyRequestsHttpException` (429)
    - `ServiceUnavailableHttpException` (503)

- **Service Unavailable (503) & Maintenance Mode Integration**:
  - When the application is put into maintenance mode via `php zf down`:
    - `ServiceUnavailableHttpException` and `abort(503)` automatically pull the active maintenance message, secret bypass settings, and retry interval from `storage/down`.
    - Automatically attaches the `Retry-After: <seconds>` HTTP header to responses.
    - JSON API requests receive a standardized payload with `'maintenance' => true` and `'retry_after' => 60`.
    - Custom templates published via `php zf error:publish --code=503` or `php zf down --publish` are automatically detected and rendered with live countdown timers and secure bypass key entry forms.
    - Status badge renders as `SERVICE UNAVAILABLE` with an interactive "Refresh Now" button.

- **Smart Error Logging & Ignored Exceptions (`$dontReport`)**:
  - `storage/logs/zf1.log` (and Discord notifications) only records true, actionable server errors (HTTP 500+, unhandled exceptions, database crashes, fatal PHP errors).
  - Client errors (HTTP 4xx: `404 Not Found`, `403 Forbidden`, `401 Unauthorized`, `422 Unprocessable Entity`, `429 Too Many Requests`) and `503 Service Unavailable` during maintenance mode are automatically excluded from `zf1.log` to prevent log pollution and alert spam.
  - Minor PHP notices, warnings, and deprecations are suppressed from `zf1.log`.
  - Developers can register additional custom exceptions to exclude from `zf1.log` in `application/Kernel.php`:

    ```php
    namespace App;

    use ZF1\Foundation\Kernel as BaseKernel;

    final class Kernel extends BaseKernel
    {
        protected static array $dontReport = [
            CustomBusinessException::class,
            PaymentDeclinedException::class,
        ];
    }
    ```

    Or programmatically:

    ```php
    \ZF1\Logging\UnifiedErrorHandler::dontReport(CustomBusinessException::class);
    ```

### 15. Modern Debugging Helpers (`dump`, `dd`, `dlog`, `benchmark`)

Global debugging methods available across CLI scripts, controllers, services, and view scripts:

- **`dump(...$vars)`**:
  - Dumps any variable with origin file and line number.
  - Automatically adapts to context:
    - **Browser**: Renders a modern, dark-themed responsive card with syntax-friendly styling and caller badges.
    - **CLI**: Colorized terminal output with caller info.
    - **API / AJAX**: Standardized pretty JSON payload.
    - Automatically integrates with `symfony/var-dumper` if installed.
  - Returns the passed variable(s) for inline debugging: `$user = dump(User::find(1));`.

- **`dd(...$vars)`**:
  - "Dump and Die": dumps the variables and immediately terminates execution (`exit(1)`).
  - Automatically sends HTTP 500 error header in browser context if headers not already sent.

- **`dump_if($condition, ...$vars)` & `dd_if($condition, ...$vars)`**:
  - Conditionally dump or dump-and-die only when `$condition` is truthy:
    ```php
    dd_if($order->total > 1000, $order);
    ```

- **`dlog(...$vars)`**:
  - Silently dumps variables directly to `storage/logs/zf1.log` with timestamp and caller location. Perfect for debugging background workers, queue jobs, webhooks, or AJAX endpoints without corrupting output.

- **`dtrace(?Throwable $e = null, bool $exit = false)`**:
  - Dumps a clean, filtered stack trace omitting vendor and framework internals.

- **`benchmark(callable $callback, int $iterations = 1, ?string $label = null)`**:
  - Measures execution time (ms), average time per iteration, memory consumption, and peak memory.

- **`dump_sql($query, array $bind = [])` & `dd_sql($query, array $bind = [])`**:
  - Accepts a `Zend_Db_Select` query object or SQL string with bindings, interpolates parameters, and dumps the runnable SQL query.

### 16. Modern Authentication (`ZF1\Auth\Auth`, `auth()`)

Provides a Laravel-style authentication layer completely decoupled from legacy global state:

```php
use ZF1\Auth\Auth;

// 1. Authenticate user (supports optional "Remember Me")
$remember = (bool) $request->getParam('remember_me', false);

if (Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
    return redirect('/dashboard');
}

// Or manually log in any array or object:
Auth::login($userRow, $remember);

// 2. Check authentication state
if (Auth::check()) {
    $user = Auth::user(); // Returns ZF1\Auth\AuthUser instance
    $id = Auth::id();     // Authenticated user ID
}

if (auth()->guest()) {
    return redirect('/login');
}

// 3. Global auth() helper (controllers, models, and views)
$id       = auth()->id;            // Direct property shortcut
$user     = auth()->user();        // AuthUser instance
$name     = auth()->user()->name;  // Access user property
$username = auth()->username;      // Dynamic proxy to user property

// 4. Logout
Auth::logout(); // Clears session, resets identity, and regenerates session ID
```

#### Custom User Model & Configurable Fields:

You can extend `AuthUser` to configure table name, primary key, password column, or add custom domain methods:

```php
namespace Application\Model;

use ZF1\Auth\AuthUser;

class User extends AuthUser
{
    protected string $table = 'users_admin';
    protected string $primaryKey = 'id';
    protected string $authPasswordName = 'password_md5'; // or 'password'
    protected string $authPasswordAlgorithm = 'md5';     // 'md5' (default) or 'bcrypt'

    // Attribute aliases:
    protected array $aliases = [
        'role' => 'user_role',
        'type' => 'user_type',
    ];

    // Custom accessor (invoked via $user->full_name):
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || (bool) $this->admin;
    }
}
```

Register your custom class in your application bootstrap:

```php
use Application\Model\User;
use ZF1\Auth\Auth;

Auth::use(User::class);

// Now auth()->user() returns an instance of Application\Model\User:
if (auth()->user()->isAdmin()) {
    // Admin access
}
```

#### Impersonation & Authorization Gates:

Protect impersonation with model-level gates or custom closures:

```php
// In your User model extending AuthUser:
class User extends AuthUser
{
    public function canImpersonate(): bool
    {
        return $this->role === 'admin';
    }

    public function canBeImpersonated(): bool
    {
        return $this->role !== 'superadmin';
    }
}

// Or define a global gate callback in your bootstrap:
Auth::canImpersonateUsing(function ($currentAdmin, $targetUser) {
    return $currentAdmin->role === 'admin' && $targetUser->role !== 'superadmin';
});

// Check permission before rendering UI:
if (Auth::canImpersonate($targetUser)) {
    // Show "Login as User" button
}

// Impersonate (throws AccessDeniedHttpException 403 if unauthorized):
Auth::impersonate($targetUserId);

// Check if currently impersonating:
if (Auth::isImpersonating()) {
    $admin = Auth::getImpersonator(); // Original admin user
}

// Revert back to the original admin:
Auth::stopImpersonating();
```

#### Directly in `.phtml` Views:

Because `auth()` is a global helper, you do **not** need `$this->auth()` (though `$this->auth()` is also supported via view helper):

```php
<?php if (auth()->check()): ?>
    <span>Welcome, <?= auth()->user()->name ?> (ID: <?= auth()->id ?>)</span>
    <a href="/logout">Logout</a>
<?php else: ?>
    <a href="/login">Sign In</a>
<?php endif; ?>
```

### 17. Automatic Action Routing & CamelCase Support

You can define controller actions in camelCase without registering custom routes. They automatically match all common URL conventions:

```php
class UserController extends Zend_Controller_Action
{
    // Accessible via:
    // - /user/someRandom
    // - /user/somerandom
    // - /user/some-random
    public function someRandomAction()
    {
        // ViewRenderer automatically finds views/scripts/user/some-random.phtml,
        // somerandom.phtml, or someRandom.phtml!
    }
}
```

## Requirements

- PHP 8.2 or higher

## Usage with Legacy Applications

When using this package with an existing ZF1 application, you may need to make some changes to your code to ensure compatibility with PHP 8.2:

1. Add `#[\ReturnTypeWillChange]` to any custom classes that implement PHP interfaces
2. Add property declarations or use `#[AllowDynamicProperties]` for classes that create properties dynamically
3. Replace deprecated functions like `utf8_encode()`, `utf8_decode()`, `create_function()`, etc.
4. Update string access syntax from `$string{0}` to `$string[0]`

## License

BSD-3-Clause (same as the original Zend Framework)
