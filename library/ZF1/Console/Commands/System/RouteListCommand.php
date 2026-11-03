<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use ReflectionClass;
use ReflectionObject;
use Throwable;
use Zend_Config;
use Zend_Controller_Front;
use Zend_Controller_Router_Rewrite;
use Zend_Controller_Router_Route;
use Zend_Controller_Router_Route_Chain;
use Zend_Controller_Router_Route_Hostname;
use Zend_Controller_Router_Route_Interface;
use Zend_Controller_Router_Route_Module;
use Zend_Controller_Router_Route_Regex;
use Zend_Controller_Router_Route_Static;
use ZF1\Console\Command;
use ZF1\Foundation\Kernel;

final class RouteListCommand extends Command
{
    protected string $signature = 'route:list {--method= : Filter routes by HTTP method (GET, POST, etc.)} {--name= : Filter routes by route name} {--path= : Filter routes by URI path/pattern}';

    protected array $aliases = ['routes', 'routes:list'];

    protected string $description = 'List all registered application routes, patterns, and dispatch targets';

    public function handle(): int
    {
        $router = $this->resolveRouter();

        if ($router === null) {
            $this->error('Unable to initialize Zend_Controller_Router. Ensure application is booted.');

            return 1;
        }

        $allRoutes = $router->getRoutes();
        if (empty($allRoutes)) {
            $router->addDefaultRoutes();
            $allRoutes = $router->getRoutes();
        }

        if (empty($allRoutes)) {
            $this->comment('No routes registered in the application router.');

            return 0;
        }

        $filterMethod = $this->option('method') ? strtoupper(trim((string) $this->option('method'))) : null;
        $filterName = $this->option('name') ? strtolower(trim((string) $this->option('name'))) : null;
        $filterPath = $this->option('path') ? strtolower(trim((string) $this->option('path'))) : null;

        $rows = [];
        foreach ($allRoutes as $name => $route) {
            $info = $this->inspectRoute((string) $name, $route);

            // Apply filters
            if ($filterMethod !== null && $info['method'] !== 'ANY' && ! str_contains($info['method'], $filterMethod)) {
                continue;
            }
            if ($filterName !== null && ! str_contains(strtolower($info['name']), $filterName)) {
                continue;
            }
            if ($filterPath !== null && ! str_contains(strtolower($info['path']), $filterPath)) {
                continue;
            }

            $rows[] = [
                $info['name'],
                $info['method'],
                $info['path'],
                $info['target'],
                $info['type'],
            ];
        }

        if (empty($rows)) {
            $this->comment('No routes matched the specified filter criteria.');

            return 0;
        }

        $this->line('');
        $this->info('  Zend Framework 1 Registered Routes');
        $this->line('');

        $headers = ['Route Name', 'Method', 'URI Pattern', 'Target (Module:Controller:Action)', 'Type'];
        $this->table($headers, $rows);

        $this->line('');
        $this->comment('Total: '.count($rows).' route(s) registered.');
        $this->line('');

        return 0;
    }

    /**
     * Inspect a Zend Route and extract details.
     *
     * @return array{name: string, method: string, path: string, target: string, type: string}
     */
    private function inspectRoute(string $name, Zend_Controller_Router_Route_Interface $route): array
    {
        $type = (new ReflectionClass($route))->getShortName();
        $ref = new ReflectionObject($route);

        $path = '/';
        $method = 'ANY';
        $defaults = [];

        // Extract defaults
        if ($ref->hasProperty('_defaults')) {
            $prop = $ref->getProperty('_defaults');
            $defaults = (array) $prop->getValue($route);
        }

        // Method requirement
        if ($ref->hasProperty('_reqs')) {
            $prop = $ref->getProperty('_reqs');
            $reqs = (array) $prop->getValue($route);
            if (! empty($reqs['method'])) {
                $method = strtoupper((string) $reqs['method']);
            }
        }
        if ($method === 'ANY' && ! empty($defaults['method'])) {
            $method = strtoupper((string) $defaults['method']);
        }

        // Extract pattern / path based on route type
        if ($route instanceof Zend_Controller_Router_Route_Module) {
            $path = '/:controller/:action/*';
            $type = 'Module';
        } elseif ($route instanceof Zend_Controller_Router_Route_Static) {
            $path = '/'.ltrim((string) $this->getReflectedProperty($ref, $route, '_route', ''), '/');
            $type = 'Static';
        } elseif ($route instanceof Zend_Controller_Router_Route_Regex) {
            $path = '~^/'.ltrim((string) $this->getReflectedProperty($ref, $route, '_regex', ''), '^/~');
            $type = 'Regex';
        } elseif ($route instanceof Zend_Controller_Router_Route) {
            $path = '/'.ltrim((string) $this->getReflectedProperty($ref, $route, '_route', ''), '/');
            $type = 'Standard';
        } elseif ($route instanceof Zend_Controller_Router_Route_Hostname) {
            $path = (string) $this->getReflectedProperty($ref, $route, '_route', '<host>');
            $type = 'Hostname';
        } elseif ($route instanceof Zend_Controller_Router_Route_Chain) {
            $path = '<chain>';
            $type = 'Chain';
        } else {
            $path = (string) ($this->getReflectedProperty($ref, $route, '_route')
                ?: $this->getReflectedProperty($ref, $route, '_regex')
                ?: '/');
        }

        // Target (module : controller : action)
        $module = $defaults['module'] ?? 'default';
        $controller = $defaults['controller'] ?? ':controller';
        $action = $defaults['action'] ?? ':action';
        $target = "{$module}:{$controller}@{$action}";

        return [
            'name' => $name,
            'method' => $method,
            'path' => $path,
            'target' => $target,
            'type' => $type,
        ];
    }

    private function getReflectedProperty(ReflectionObject $ref, object $obj, string $prop, mixed $default = null): mixed
    {
        if ($ref->hasProperty($prop)) {
            $p = $ref->getProperty($prop);

            return $p->getValue($obj);
        }

        return $default;
    }

    /**
     * Resolve the active front controller router.
     */
    private function resolveRouter(): ?Zend_Controller_Router_Rewrite
    {
        try {
            $front = Zend_Controller_Front::getInstance();
            $router = $front->getRouter();

            if ($router instanceof Zend_Controller_Router_Rewrite) {
                // Ensure routes from config are loaded if available
                $app = Kernel::getApplication();
                if ($app !== null) {
                    $options = $app->getOptions();
                    $routesConfig = $options['resources']['router']['routes'] ?? ($options['routes'] ?? null);
                    if (! empty($routesConfig) && is_array($routesConfig)) {
                        $router->addConfig(new Zend_Config($routesConfig));
                    }
                }

                return $router;
            }
        } catch (Throwable) {
            // Silently handle
        }

        return new Zend_Controller_Router_Rewrite;
    }
}
