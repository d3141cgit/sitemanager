<?php

namespace SiteManager\Contracts;

interface ExtensionInterface
{
    /**
     * Get the extension name (English)
     */
    public function getName(): string;

    /**
     * Get the extension icon (Bootstrap Icons class)
     */
    public function getIcon(): string;

    /**
     * Get the extension slug (URL-safe identifier)
     */
    public function getSlug(): string;

    /**
     * Get the model class name
     */
    public function getModel(): ?string;

    /**
     * Get the controller class name
     */
    public function getController(): ?string;

    /**
     * Get the route prefix
     */
    public function getRoutePrefix(): string;

    /**
     * Get the view prefix
     */
    public function getViewPrefix(): string;

    /**
     * Get the menu position (lower = higher priority)
     */
    public function getMenuPosition(): int;

    /**
     * Get the permissions for this extension
     */
    public function getPermissions(): array;

    /**
     * Get the list columns configuration
     */
    public function getListColumns(): array;

    /**
     * Get the searchable fields
     */
    public function getSearchableFields(): array;

    /**
     * Get the filterable fields with options
     */
    public function getFilters(): array;

    /**
     * Get the member relation name (if any)
     */
    public function getMemberRelation(): ?string;

    /**
     * Get the member relation definition callback
     */
    public function getMemberRelationDefinition(): ?callable;

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array;

    /**
     * Check if extension is enabled
     */
    public function isEnabled(): bool;

    /**
     * Boot the extension
     */
    public function boot(): void;
}
