<?php

namespace SiteManager\Extensions;

use SiteManager\Contracts\ExtensionInterface;

/**
 * Extension implementation from array configuration
 */
class ArrayExtension implements ExtensionInterface
{
    protected string $key;
    protected array $config;

    public function __construct(string $key, array $config)
    {
        $this->key = $key;
        $this->config = $config;
    }

    public function getName(): string
    {
        return $this->config['name'] ?? ucfirst($this->key);
    }

    public function getIcon(): string
    {
        return $this->config['icon'] ?? 'bi-puzzle';
    }

    public function getSlug(): string
    {
        return $this->key;
    }

    public function getModel(): ?string
    {
        return $this->config['model'] ?? null;
    }

    public function getController(): ?string
    {
        return $this->config['controller'] ?? null;
    }

    public function getRoutePrefix(): string
    {
        return $this->config['route_prefix'] ?? "sitemanager/extensions/{$this->key}";
    }

    public function getViewPrefix(): string
    {
        return $this->config['view_prefix'] ?? "sitemanager.extensions.{$this->key}";
    }

    public function getMenuPosition(): int
    {
        return $this->config['menu_position'] ?? 100;
    }

    public function getPermissions(): array
    {
        return $this->config['permissions'] ?? ['index', 'read', 'write', 'manage'];
    }

    public function getListColumns(): array
    {
        $columns = $this->config['list_columns'] ?? ['id', 'created_at'];

        // Convert simple array to full column config
        $result = [];
        foreach ($columns as $key => $value) {
            if (is_string($key)) {
                // Already in key => config format
                $result[$key] = $value;
            } else {
                // Simple string value, convert to config
                $result[$value] = [
                    'label' => ucfirst(str_replace('_', ' ', $value)),
                    'sortable' => true,
                ];
            }
        }

        return $result;
    }

    public function getSearchableFields(): array
    {
        return $this->config['searchable'] ?? [];
    }

    public function getFilters(): array
    {
        $filters = $this->config['filterable'] ?? [];

        // Convert simple array to filter config
        $result = [];
        foreach ($filters as $key => $value) {
            if (is_string($key)) {
                // Already in key => config format
                $result[$key] = $value;
            } else {
                // Simple string value, convert to basic filter
                $result[$value] = [
                    'type' => 'text',
                    'label' => ucfirst(str_replace('_', ' ', $value)),
                ];
            }
        }

        return $result;
    }

    public function getMemberRelation(): ?string
    {
        return $this->config['member_relation'] ?? null;
    }

    public function getMemberRelationDefinition(): ?callable
    {
        if (isset($this->config['member_relation_definition'])) {
            return $this->config['member_relation_definition'];
        }

        // Default relation definition using model and member_id
        $model = $this->getModel();
        $foreignKey = $this->config['member_foreign_key'] ?? 'member_id';

        if ($model && $this->getMemberRelation()) {
            return function ($member) use ($model, $foreignKey) {
                return $member->hasMany($model, $foreignKey);
            };
        }

        return null;
    }

    public function getStatistics(): array
    {
        if (isset($this->config['statistics']) && is_callable($this->config['statistics'])) {
            return call_user_func($this->config['statistics']);
        }

        $model = $this->getModel();
        if (!$model || !class_exists($model)) {
            return [];
        }

        return [
            'total' => $model::count(),
            'today' => $model::whereDate('created_at', today())->count(),
            'this_week' => $model::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    public function boot(): void
    {
        if (isset($this->config['boot']) && is_callable($this->config['boot'])) {
            call_user_func($this->config['boot']);
        }
    }

    /**
     * Get any additional config value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all config
     */
    public function getAllConfig(): array
    {
        return $this->config;
    }
}
