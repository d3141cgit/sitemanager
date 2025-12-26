<?php

namespace SiteManager\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use SiteManager\Contracts\ExtensionInterface;
use SiteManager\Extensions\ArrayExtension;
use SiteManager\Models\Member;
use SiteManager\Models\EdmMember;

class ExtensionManager
{
    protected Collection $extensions;
    protected bool $booted = false;
    protected bool $routesRegistered = false;

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
                $this->register($key, $config);
            }
        }
    }

    /**
     * Load extension classes from directory
     */
    public function loadFromDirectory(string $path, string $namespace): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (glob($path . '/*Extension.php') as $file) {
            $className = $namespace . '\\' . basename($file, '.php');

            if (class_exists($className)) {
                $extension = new $className();

                if ($extension instanceof ExtensionInterface) {
                    $this->extensions->put($extension->getSlug(), $extension);
                }
            }
        }
    }

    /**
     * Register an extension
     */
    public function register(string $key, ExtensionInterface|array $extension): void
    {
        if (is_array($extension)) {
            $extension = new ArrayExtension($key, $extension);
        }

        if ($extension->isEnabled()) {
            $this->extensions->put($key, $extension);
        }
    }

    /**
     * Get all extensions
     */
    public function all(): Collection
    {
        return $this->extensions;
    }

    /**
     * Get a specific extension
     */
    public function get(string $key): ?ExtensionInterface
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
     * Get menu items for sidebar
     */
    public function getMenuItems(): array
    {
        return $this->extensions
            ->filter(fn($ext) => $ext->isEnabled())
            ->map(fn($ext, $key) => [
                'key' => $key,
                'name' => $ext->getName(),
                'icon' => $ext->getIcon(),
                'route' => "sitemanager.extensions.{$key}.index",
                'position' => $ext->getMenuPosition(),
            ])
            ->sortBy('position')
            ->values()
            ->all();
    }

    /**
     * Get dashboard statistics from all extensions
     */
    public function getDashboardStats(): array
    {
        $stats = [];

        foreach ($this->extensions as $key => $extension) {
            $extStats = $extension->getStatistics();
            if (!empty($extStats)) {
                $stats[$key] = [
                    'name' => $extension->getName(),
                    'icon' => $extension->getIcon(),
                    'route' => "sitemanager.extensions.{$key}.index",
                    'stats' => $extStats,
                ];
            }
        }

        return $stats;
    }

    /**
     * Register member relations for all extensions
     */
    public function registerMemberRelations(): void
    {
        foreach ($this->extensions as $extension) {
            $relation = $extension->getMemberRelation();
            $definition = $extension->getMemberRelationDefinition();

            if ($relation && $definition) {
                // Register on Member model
                if (class_exists(Member::class) && !method_exists(Member::class, $relation)) {
                    Member::resolveRelationUsing($relation, $definition);
                }

                // Register on EdmMember if enabled
                if (config('sitemanager.auth.enable_edm_member_auth', false) && class_exists(EdmMember::class)) {
                    if (!method_exists(EdmMember::class, $relation)) {
                        // Adjust foreign key for EdmMember (uses mm_uid instead of id)
                        $model = $extension->getModel();
                        if ($model) {
                            EdmMember::resolveRelationUsing($relation, function ($member) use ($model) {
                                return $member->hasMany($model, 'mm_uid');
                            });
                        }
                    }
                }
            }
        }
    }

    /**
     * Register routes for all extensions
     */
    public function registerRoutes(): void
    {
        if ($this->routesRegistered) {
            return;
        }

        Route::middleware(['web', 'auth', 'sitemanager'])
            ->prefix('sitemanager/extensions')
            ->name('sitemanager.extensions.')
            ->group(function () {
                foreach ($this->extensions as $key => $extension) {
                    $controller = $extension->getController();

                    if ($controller && class_exists($controller)) {
                        Route::prefix($key)->name("{$key}.")->group(function () use ($controller) {
                            Route::get('/', [$controller, 'index'])->name('index');
                            Route::get('/create', [$controller, 'create'])->name('create');
                            Route::post('/', [$controller, 'store'])->name('store');
                            Route::get('/{id}', [$controller, 'show'])->name('show');
                            Route::get('/{id}/edit', [$controller, 'edit'])->name('edit');
                            Route::put('/{id}', [$controller, 'update'])->name('update');
                            Route::patch('/{id}', [$controller, 'update'])->name('patch');
                            Route::delete('/{id}', [$controller, 'destroy'])->name('destroy');

                            // Bulk actions
                            Route::post('/bulk-action', [$controller, 'bulkAction'])->name('bulk-action');

                            // Export
                            Route::get('/export/{format?}', [$controller, 'export'])->name('export');
                        });
                    }
                }
            });

        $this->routesRegistered = true;
    }

    /**
     * Boot all extensions
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->extensions as $extension) {
            $extension->boot();
        }

        $this->booted = true;
    }

    /**
     * Get extension count
     */
    public function count(): int
    {
        return $this->extensions->count();
    }

    /**
     * Check if extensions have been loaded
     */
    public function isEmpty(): bool
    {
        return $this->extensions->isEmpty();
    }

    /**
     * Get extensions as array for JSON response
     */
    public function toArray(): array
    {
        return $this->extensions->map(fn($ext, $key) => [
            'key' => $key,
            'name' => $ext->getName(),
            'icon' => $ext->getIcon(),
            'slug' => $ext->getSlug(),
            'model' => $ext->getModel(),
            'controller' => $ext->getController(),
            'menu_position' => $ext->getMenuPosition(),
            'enabled' => $ext->isEnabled(),
        ])->values()->all();
    }
}
