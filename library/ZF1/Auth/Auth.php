<?php

declare(strict_types=1);

namespace ZF1\Auth;

use InvalidArgumentException;
use Throwable;
use Zend_Auth;
use ZF1\Exceptions\AccessDeniedHttpException;
use ZF1\Session\Session;

/**
 * Modern Authentication Manager and Static Facade for Zend Framework 1.
 * Uses Zend_Auth and its session storage as the single source of truth.
 *
 * @property-read AuthUser|null $user The currently authenticated user instance.
 * @property-read int|string|null $id The primary key ID of the authenticated user.
 *
 * @mixin AuthUser
 */
final class Auth
{
    /**
     * Session key for storing the original user ID during impersonation.
     */
    public const IMPERSONATION_KEY = 'zf1_impersonated_by';

    /**
     * Session key indicating the user was authenticated via "remember me".
     */
    public const VIA_REMEMBER_KEY = 'zf1_auth_via_remember';

    /**
     * Session key storing the timestamp of when the user last entered their password.
     */
    public const PASSWORD_CONFIRMED_KEY = 'zf1_auth_password_confirmed_at';

    /**
     * The User class representing authenticated users (must extend AuthUser).
     *
     * @var class-string<AuthUser>
     */
    private static string $userClass = AuthUser::class;

    /**
     * Cached AuthUser instance for the current request cycle.
     */
    private static ?AuthUser $resolvedUser = null;

    /**
     * Singleton instance for helper delegation.
     */
    private static ?self $instance = null;

    /**
     * Register a custom User class extending AuthUser.
     *
     * @param  class-string<AuthUser>  $userClass
     */
    public static function use(string $userClass): void
    {
        if ($userClass !== AuthUser::class && ! is_subclass_of($userClass, AuthUser::class)) {
            throw new InvalidArgumentException("Class {$userClass} must be an instance of or extend ".AuthUser::class);
        }

        self::$userClass = $userClass;
        self::$resolvedUser = null;
    }

    /**
     * Get the active User class name.
     *
     * @return class-string<AuthUser>
     */
    public static function getUserClass(): string
    {
        return self::$userClass;
    }

    /**
     * Instantiate a user model from attributes.
     *
     * @param  array<string, mixed>|object  $attributes
     */
    public static function createUserInstance(array|object $attributes = []): AuthUser
    {
        $class = self::$userClass;

        if ($attributes instanceof AuthUser) {
            $attributes = $attributes->toArray();
        }

        return new $class($attributes);
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Determine if current user is authenticated.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Determine if current user is a guest (not authenticated).
     */
    public static function guest(): bool
    {
        return ! self::check();
    }

    /**
     * Get the currently authenticated user instance.
     */
    public static function user(): ?AuthUser
    {
        if (self::$resolvedUser !== null) {
            return self::$resolvedUser;
        }

        try {
            $auth = Zend_Auth::getInstance();

            if ($auth->hasIdentity()) {
                $identity = $auth->getIdentity();

                if (is_object($identity) && is_a($identity, self::$userClass)) {
                    self::$resolvedUser = $identity;

                    return self::$resolvedUser;
                }

                if (is_object($identity) || is_array($identity)) {
                    self::$resolvedUser = self::createUserInstance($identity);

                    return self::$resolvedUser;
                }
            }
        } catch (Throwable) {
            // Ignore access errors if session is uninitialized
        }

        return null;
    }

    /**
     * Get the unique identifier of the authenticated user.
     */
    public static function id(): int|string|null
    {
        return self::user()?->getAuthIdentifier();
    }

    /**
     * Duration in seconds for remember-me persistence (default 30 days).
     */
    private static int $rememberDuration = 2592000;

    /**
     * Set the remember-me duration in seconds.
     */
    public static function setRememberDuration(int $seconds): void
    {
        self::$rememberDuration = $seconds;
    }

    /**
     * Get the remember-me duration in seconds.
     */
    public static function getRememberDuration(): int
    {
        return self::$rememberDuration;
    }

    /**
     * Log a user into the application using Zend_Auth persistent storage.
     *
     * @param  array<string, mixed>|object  $user
     */
    public static function login(array|object $user, bool $remember = false): AuthUser
    {
        $authUser = is_object($user) && is_a($user, self::$userClass)
            ? $user
            : self::createUserInstance($user);

        // Store into Zend_Auth storage
        Zend_Auth::getInstance()->getStorage()->write((object) $authUser->toArray());

        if ($remember) {
            Session::rememberMe(self::$rememberDuration);
        }

        Session::put(self::VIA_REMEMBER_KEY, false);
        Session::put(self::PASSWORD_CONFIRMED_KEY, time());

        self::$resolvedUser = $authUser;

        return $authUser;
    }

    /**
     * Log a user into the application using their database primary key.
     */
    public static function loginUsingId(int|string $id, ?string $table = null, bool $remember = false): ?AuthUser
    {
        if (! class_exists(\ZF1\Database\DB::class)) {
            return null;
        }

        $userModel = self::createUserInstance();
        $tableName = $table ?? $userModel->getTable();
        $keyName = $userModel->getAuthIdentifierName();

        try {
            $record = \ZF1\Database\DB::table($tableName)->where($keyName, $id)->first();

            if (! $record) {
                return null;
            }

            return self::login(self::createUserInstance($record), $remember);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Log the current user out of the application and flush credentials.
     */
    public static function logout(): void
    {
        try {
            Zend_Auth::getInstance()->clearIdentity();
        } catch (Throwable) {
            // Ignore
        }

        self::$resolvedUser = null;

        Session::forget(self::IMPERSONATION_KEY);
        Session::forget(self::VIA_REMEMBER_KEY);
        Session::forget(self::PASSWORD_CONFIRMED_KEY);
        Session::forgetMe();
        Session::regenerate();
    }

    /**
     * Determine if current user was authenticated via "remember me" persistence.
     */
    public static function viaRemember(): bool
    {
        if (self::guest()) {
            return false;
        }

        return (bool) Session::get(self::VIA_REMEMBER_KEY, false);
    }

    /**
     * Set the via-remember status.
     */
    public static function setViaRemember(bool $viaRemember = true): void
    {
        Session::put(self::VIA_REMEMBER_KEY, $viaRemember);
    }

    /**
     * Determine if the user has recently confirmed their password within the timeout window.
     *
     * @param  int  $timeout  Timeout in seconds (default 10800 = 3 hours).
     */
    public static function hasConfirmedPassword(int $timeout = 10800): bool
    {
        if (self::guest() || self::viaRemember()) {
            return false;
        }

        $confirmedAt = Session::get(self::PASSWORD_CONFIRMED_KEY);

        return $confirmedAt !== null && (time() - (int) $confirmedAt) < $timeout;
    }

    /**
     * Record that the user has entered/confirmed their password for the current session.
     */
    public static function passwordConfirmed(): void
    {
        Session::put(self::PASSWORD_CONFIRMED_KEY, time());
        Session::put(self::VIA_REMEMBER_KEY, false);
    }

    /**
     * Custom gate callback for impersonation authorization.
     *
     * @var (callable(AuthUser, ?AuthUser): bool)|null
     */
    private static mixed $impersonationGate = null;

    /**
     * Define a gate callback to authorize impersonation requests.
     *
     * @param  (callable(AuthUser, ?AuthUser): bool)|null  $callback
     */
    public static function canImpersonateUsing(?callable $callback): void
    {
        self::$impersonationGate = $callback;
    }

    /**
     * Resolve a user instance from an ID, object, or array.
     *
     * @param  object|array<string, mixed>|int|string  $user
     */
    public static function resolveUserInstance(object|array|int|string $user): ?AuthUser
    {
        if ($user instanceof AuthUser) {
            return $user;
        }

        if (is_int($user) || is_string($user)) {
            if (! class_exists(\ZF1\Database\DB::class)) {
                return null;
            }

            $userModel = self::createUserInstance();
            $tableName = $userModel->getTable();
            $keyName = $userModel->getAuthIdentifierName();

            try {
                $record = \ZF1\Database\DB::table($tableName)->where($keyName, $user)->first();

                return $record ? self::createUserInstance($record) : null;
            } catch (Throwable) {
                return null;
            }
        }

        return self::createUserInstance($user);
    }

    /**
     * Check if the current user can impersonate the given target user.
     *
     * @param  object|array<string, mixed>|int|string|null  $targetUser
     */
    public static function canImpersonate(object|array|int|string|null $targetUser = null): bool
    {
        $currentUser = self::user();
        if ($currentUser === null) {
            return false;
        }

        $targetInstance = $targetUser !== null ? self::resolveUserInstance($targetUser) : null;

        // 1. Check custom gate callback if defined
        if (self::$impersonationGate !== null) {
            return (bool) (self::$impersonationGate)($currentUser, $targetInstance);
        }

        // 2. Check model-level gates
        if (! $currentUser->canImpersonate()) {
            return false;
        }

        if ($targetInstance !== null && ! $targetInstance->canBeImpersonated()) {
            return false;
        }

        return true;
    }

    /**
     * Impersonate a given user.
     *
     * @param  object|array<string, mixed>|int|string  $user
     */
    public static function impersonate(object|array|int|string $user): ?AuthUser
    {
        if (! self::canImpersonate($user)) {
            throw new AccessDeniedHttpException('This user is not authorized to impersonate other users.');
        }

        $currentId = self::id();

        if ($currentId !== null && ! self::isImpersonating()) {
            Session::put(self::IMPERSONATION_KEY, $currentId);
        }

        if (is_int($user) || is_string($user)) {
            return self::loginUsingId($user);
        }

        return self::login($user);
    }

    /**
     * Determine if an impersonation session is active.
     */
    public static function isImpersonating(): bool
    {
        return Session::has(self::IMPERSONATION_KEY);
    }

    /**
     * Get the original user who is impersonating the current user.
     */
    public static function getImpersonator(): ?AuthUser
    {
        if (! self::isImpersonating()) {
            return null;
        }

        $impersonatorId = Session::get(self::IMPERSONATION_KEY);
        if ($impersonatorId === null || ! class_exists(\ZF1\Database\DB::class)) {
            return null;
        }

        $userModel = self::createUserInstance();
        $tableName = $userModel->getTable();
        $keyName = $userModel->getAuthIdentifierName();

        try {
            $record = \ZF1\Database\DB::table($tableName)->where($keyName, $impersonatorId)->first();

            if (! $record) {
                return null;
            }

            return self::createUserInstance($record);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Stop impersonating and restore the original user.
     */
    public static function stopImpersonating(): bool
    {
        if (! self::isImpersonating()) {
            return false;
        }

        $impersonatorId = Session::get(self::IMPERSONATION_KEY);
        Session::forget(self::IMPERSONATION_KEY);

        if ($impersonatorId !== null) {
            return self::loginUsingId($impersonatorId) !== null;
        }

        self::logout();

        return true;
    }

    /**
     * Set the current authenticated user manually.
     */
    public static function setUser(?AuthUser $user): void
    {
        self::$resolvedUser = $user;

        if ($user !== null) {
            Zend_Auth::getInstance()->getStorage()->write((object) $user->toArray());
        } else {
            Zend_Auth::getInstance()->clearIdentity();
        }
    }

    /**
     * Attempt to authenticate a user using given credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public static function attempt(array $credentials, bool $remember = false, ?string $table = null): bool
    {
        $userModel = self::createUserInstance();
        $passwordKey = 'password';

        if (! isset($credentials['password']) && isset($credentials[$userModel->getAuthPasswordName()])) {
            $passwordKey = $userModel->getAuthPasswordName();
        }

        $password = $credentials[$passwordKey] ?? null;
        if ($password === null) {
            return false;
        }

        unset($credentials[$passwordKey]);
        unset($credentials['password']);

        if (empty($credentials)) {
            return false;
        }

        if (! class_exists(\ZF1\Database\DB::class)) {
            return false;
        }

        $tableName = $table ?? $userModel->getTable();

        $query = \ZF1\Database\DB::table($tableName);

        foreach ($credentials as $col => $val) {
            $query->where($col, $val);
        }

        $row = $query->first();

        if (! $row) {
            return false;
        }

        $user = self::createUserInstance($row);

        if (! $user->verifyPassword((string) $password)) {
            return false;
        }

        self::login($user, $remember);

        return true;
    }

    /**
     * Magic property getter on auth() instance.
     * Allows: auth()->id, auth()->user, auth()->username, auth()->email
     */
    public function __get(string $name): mixed
    {
        if ($name === 'id') {
            return self::id();
        }

        if ($name === 'user') {
            return self::user();
        }

        return self::user()?->{$name};
    }

    /**
     * Magic property isset on auth() instance.
     */
    public function __isset(string $name): bool
    {
        if ($name === 'id') {
            return self::id() !== null;
        }

        if ($name === 'user') {
            return self::user() !== null;
        }

        $user = self::user();

        return $user !== null && isset($user->{$name});
    }

    /**
     * Dynamically forward instance calls to static methods.
     *
     * @param  array<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists(self::class, $method)) {
            return self::{$method}(...$arguments);
        }

        return self::user()?->{$method}(...$arguments);
    }

    /**
     * Dynamically forward static calls to user methods.
     *
     * @param  array<mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::user()?->{$method}(...$arguments);
    }
}
