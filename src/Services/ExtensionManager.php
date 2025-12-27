<?php

namespace SiteManager\Services;

use Illuminate\Support\Collection;

/**
 * ExtensionManager - Manages admin menu extensions
 *
 * This service handles extension registration for the SiteManager admin panel.
 * Menu rendering is done directly in Blade using config('sitemanager.extensions').
 * This class is used by ExtensionController for extension metadata access.
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
            if (is_array($config)) {
                // children이 있는 경우 (dropdown 지원)
                if (isset($config['children']) && is_array($config['children'])) {
                    // children이 있으면 name만 필수
                    if (isset($config['name'])) {
                        $this->register($key, $config);
                    }
                }
                // 일반 메뉴 (route 필수)
                elseif (isset($config['name'], $config['route'])) {
                    $this->register($key, $config);
                }
            }
        }
    }

    /**
     * Register an extension menu item
     */
    public function register(string $key, array $config): void
    {
        $menuItem = [
            'key' => $key,
            'name' => $config['name'],
            'icon' => $config['icon'] ?? 'bi-puzzle',
            'position' => $config['position'] ?? 100,
            'enabled' => $config['enabled'] ?? true,
        ];

        // children이 있으면 dropdown 메뉴
        if (isset($config['children']) && is_array($config['children']) && count($config['children']) > 0) {
            $menuItem['children'] = [];
            foreach ($config['children'] as $childKey => $childConfig) {
                if (is_array($childConfig) && isset($childConfig['name'], $childConfig['route'])) {
                    $menuItem['children'][] = [
                        'key' => is_numeric($childKey) ? $childConfig['route'] : $childKey,
                        'name' => $childConfig['name'],
                        'icon' => $childConfig['icon'] ?? 'bi-dot',
                        'route' => $childConfig['route'],
                        'enabled' => $childConfig['enabled'] ?? true,
                    ];
                }
            }
            // children이 모두 disabled면 메뉴도 disabled 처리
            if (count($menuItem['children']) === 0) {
                $menuItem['enabled'] = false;
            }
        } else {
            // 일반 메뉴 (route 필수)
            $menuItem['route'] = $config['route'];
        }

        $this->extensions->put($key, $menuItem);
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
