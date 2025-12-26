<?php

namespace SiteManager\Services;

use Illuminate\Support\Collection;

/**
 * ExtensionManager - Manages admin menu extensions
 *
 * This service only handles menu registration for the SiteManager admin panel.
 * All routes, controllers, and business logic are managed by Laravel.
 */
class ExtensionManager
{
    protected Collection $extensions;

    public function __construct()
    {
        $this->extensions = collect();
    }

    /**
     * Load extensions from config
     */
    public function loadFromConfig(): void
    {
        $extensions = config('sitemanager.extensions', []);

        foreach ($extensions as $key => $config) {
            if (is_array($config) && isset($config['name'], $config['route'])) {
                $this->register($key, $config);
            }
        }
    }

    /**
     * Register an extension menu item
     */
    public function register(string $key, array $config): void
    {
        $this->extensions->put($key, [
            'key' => $key,
            'name' => $config['name'],
            'icon' => $config['icon'] ?? 'bi-puzzle',
            'route' => $config['route'],
            'position' => $config['position'] ?? 100,
            'enabled' => $config['enabled'] ?? true,
        ]);
    }

    /**
     * Get all registered extensions
     */
    public function all(): Collection
    {
        return $this->extensions;
    }

    /**
     * Get a specific extension
     */
    public function get(string $key): ?array
    {
        return $this->extensions->get($key);
    }

    /**
     * Check if extension exists
     */
    public function has(string $key): bool
    {
        return $this->extensions->has($key);
    }

    /**
     * Get menu items for sidebar (sorted by position)
     */
    public function getMenuItems(): array
    {
        return $this->extensions
            ->filter(fn($ext) => $ext['enabled'] ?? true)
            ->sortBy('position')
            ->values()
            ->all();
    }

    /**
     * Get extension count
     */
    public function count(): int
    {
        return $this->extensions->count();
    }

    /**
     * Check if extensions list is empty
     */
    public function isEmpty(): bool
    {
        return $this->extensions->isEmpty();
    }
}
