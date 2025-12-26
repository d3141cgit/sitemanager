<?php

namespace SiteManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SiteManager\Services\ExtensionManager;
use SiteManager\Contracts\ExtensionInterface;

abstract class ExtensionController extends Controller
{
    /**
     * Extension key (slug)
     */
    protected string $extensionKey;

    /**
     * Model class
     */
    protected string $modelClass;

    /**
     * View prefix for custom views
     */
    protected string $viewPrefix = 'sitemanager::extensions';

    /**
     * Items per page
     */
    protected int $perPage = 20;

    /**
     * Extension instance
     */
    protected ?ExtensionInterface $extension = null;

    public function __construct()
    {
        $this->middleware(['auth', 'sitemanager']);
    }

    /**
     * Get the extension instance
     */
    protected function getExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = app(ExtensionManager::class)->get($this->extensionKey);
        }
        return $this->extension;
    }

    /**
     * Get the view name, checking for custom views first
     */
    protected function getView(string $name): string
    {
        // Check for custom view in project
        $customView = "sitemanager.extensions.{$this->extensionKey}.{$name}";
        if (view()->exists($customView)) {
            return $customView;
        }

        // Fall back to package default view
        return "{$this->viewPrefix}.{$name}";
    }

    /**
     * List items
     */
    public function index(Request $request): View
    {
        $extension = $this->getExtension();
        $query = $this->modelClass::query();

        // Apply search
        if ($request->filled('search')) {
            $query = $this->applySearch($query, $request->search);
        }

        // Apply filters
        if ($request->filled('filters')) {
            $query = $this->applyFilters($query, $request->filters);
        }

        // Apply sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query = $this->applySorting($query, $sortBy, $sortDir);

        // Apply eager loading
        $query = $this->applyEagerLoading($query);

        $items = $query->paginate($this->perPage)->withQueryString();

        return view($this->getView('index'), [
            'extension' => $extension,
            'extensionKey' => $this->extensionKey,
            'items' => $items,
            'columns' => $extension->getListColumns(),
            'filters' => $extension->getFilters(),
            'currentFilters' => $request->get('filters', []),
            'search' => $request->get('search', ''),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * Show create form
     */
    public function create(): View
    {
        $extension = $this->getExtension();

        return view($this->getView('create'), [
            'extension' => $extension,
            'extensionKey' => $this->extensionKey,
        ]);
    }

    /**
     * Store new item
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateStore($request);
        $item = $this->modelClass::create($validated);

        return redirect()
            ->route("sitemanager.extensions.{$this->extensionKey}.show", $item->id)
            ->with('success', __('Created successfully.'));
    }

    /**
     * Show item detail
     */
    public function show($id): View
    {
        $item = $this->findItem($id);
        $extension = $this->getExtension();

        return view($this->getView('show'), [
            'extension' => $extension,
            'extensionKey' => $this->extensionKey,
            'item' => $item,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit($id): View
    {
        $item = $this->findItem($id);
        $extension = $this->getExtension();

        return view($this->getView('edit'), [
            'extension' => $extension,
            'extensionKey' => $this->extensionKey,
            'item' => $item,
        ]);
    }

    /**
     * Update item
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $item = $this->findItem($id);
        $validated = $this->validateUpdate($request, $item);
        $item->update($validated);

        return redirect()
            ->route("sitemanager.extensions.{$this->extensionKey}.show", $id)
            ->with('success', __('Updated successfully.'));
    }

    /**
     * Delete item
     */
    public function destroy($id): RedirectResponse
    {
        $item = $this->findItem($id);

        $this->beforeDestroy($item);
        $item->delete();
        $this->afterDestroy($item);

        return redirect()
            ->route("sitemanager.extensions.{$this->extensionKey}.index")
            ->with('success', __('Deleted successfully.'));
    }

    /**
     * Bulk action
     */
    public function bulkAction(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'action' => 'required|string',
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $action = $request->action;
        $ids = $request->ids;
        $count = 0;

        foreach ($ids as $id) {
            $item = $this->modelClass::find($id);
            if ($item) {
                $result = $this->handleBulkAction($action, $item);
                if ($result) {
                    $count++;
                }
            }
        }

        $message = __(':count items processed.', ['count' => $count]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'count' => $count]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Export items
     */
    public function export(Request $request, string $format = 'csv')
    {
        $query = $this->modelClass::query();

        // Apply same filters as index
        if ($request->filled('search')) {
            $query = $this->applySearch($query, $request->search);
        }

        if ($request->filled('filters')) {
            $query = $this->applyFilters($query, $request->filters);
        }

        $items = $query->get();

        return $this->handleExport($items, $format);
    }

    // ========================================
    // Methods to override in child classes
    // ========================================

    /**
     * Find item by ID
     */
    protected function findItem($id)
    {
        return $this->modelClass::findOrFail($id);
    }

    /**
     * Apply search to query
     */
    protected function applySearch($query, string $search)
    {
        $searchableFields = $this->getExtension()->getSearchableFields();

        if (empty($searchableFields)) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $searchableFields) {
            foreach ($searchableFields as $field) {
                $q->orWhere($field, 'like', "%{$search}%");
            }
        });
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, array $filters)
    {
        foreach ($filters as $field => $value) {
            if ($value !== '' && $value !== null) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }
        return $query;
    }

    /**
     * Apply sorting to query
     */
    protected function applySorting($query, string $sortBy, string $sortDir)
    {
        // Handle dot notation for related fields
        if (str_contains($sortBy, '.')) {
            // For now, just ignore related field sorting
            return $query->orderBy('created_at', $sortDir);
        }

        return $query->orderBy($sortBy, $sortDir);
    }

    /**
     * Apply eager loading
     */
    protected function applyEagerLoading($query)
    {
        return $query;
    }

    /**
     * Validate store request
     */
    protected function validateStore(Request $request): array
    {
        return $request->all();
    }

    /**
     * Validate update request
     */
    protected function validateUpdate(Request $request, $item): array
    {
        return $request->all();
    }

    /**
     * Before destroy hook
     */
    protected function beforeDestroy($item): void
    {
        // Override in child class if needed
    }

    /**
     * After destroy hook
     */
    protected function afterDestroy($item): void
    {
        // Override in child class if needed
    }

    /**
     * Handle bulk action
     */
    protected function handleBulkAction(string $action, $item): bool
    {
        switch ($action) {
            case 'delete':
                $item->delete();
                return true;
            default:
                return false;
        }
    }

    /**
     * Handle export
     */
    protected function handleExport($items, string $format)
    {
        $extension = $this->getExtension();
        $columns = $extension->getListColumns();
        $filename = $this->extensionKey . '_' . date('Y-m-d_His');

        switch ($format) {
            case 'json':
                return response()->json($items)
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}.json\"");

            case 'csv':
            default:
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
                ];

                $callback = function () use ($items, $columns) {
                    $file = fopen('php://output', 'w');

                    // Header row
                    fputcsv($file, array_keys($columns));

                    // Data rows
                    foreach ($items as $item) {
                        $row = [];
                        foreach (array_keys($columns) as $column) {
                            $row[] = $this->getColumnValue($item, $column);
                        }
                        fputcsv($file, $row);
                    }

                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
        }
    }

    /**
     * Get column value from item (supports dot notation)
     */
    protected function getColumnValue($item, string $column)
    {
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $value = $item;
            foreach ($parts as $part) {
                $value = $value?->{$part};
            }
            return $value;
        }

        return $item->{$column};
    }
}
