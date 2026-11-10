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
php vendor/bin/zf make:foundation
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
- `php zf down` / `php zf up` — Maintenance mode management (supports `--publish` to publish editable maintenance view, `--template=` for custom views, and secret bypass tokens)
- `php zf db:backup` — Automated gzip database backup with retention pruning

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
- `dispatch($job, $payload, $queue, $delay)` — Quickly dispatch a background job
- `env($key, $default)`
- `base_path($subpath)` / `app_path()` / `storage_path()` / `public_path()` / `config_path()`
- `url($path, $params, $secure)` / `asset($path, $version)`
- `storage($disk)`
- `mailer($to, $name)` / `Mailer::to($to, $name)`
- `render_email_layout($content, $subject, $data)` / `EmailLayout::render($content, $subject, $data)`
- `blank($val)` / `filled($val)` / `data_get($target, $key)`
- `discord_notify($title, $message, $fields)`

### 6. Mail Service & Email Layout System (`ZF1\Mail\Mailer`, `ZF1\Mail\EmailLayout`)
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
  - **Logo Fallback Hierarchy**:
    1. Checks `public/logo.png`
    2. Then checks `public/logo-dark.png`
    3. Then checks `public/logo-light.png`
    4. If no logo is found, automatically falls back to `APP_NAME` as stylized text.
    5. The logo can also be explicitly overridden in the payload via `'logo'` or `'logo_url'`.
  - **Template Variable Interpolation**:
    - Supports `{key}`, `{{key}}`, `{ key }`, and `{{ key }}` in both the subject and the body.
    - Default available variables: `{app_name}`, `{app_url}`, `{year}`, `{date}`, `{time}`, `{logo_url}`.
    - Payload values replace placeholders and can override defaults (e.g. passing `'app_name'` or `'logo'`).
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
- `MAIL_ACTIVE=false` safely skips SMTP transmission and logs to debug log
- `TEST_MAIL` reroutes all outgoing emails in development to the test address and annotates original recipients in the subject
- Failure tracking via `Mailer::$lastError` and automatic debug logging

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
