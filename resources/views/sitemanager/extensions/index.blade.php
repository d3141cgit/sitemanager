@extends('sitemanager::layouts.sitemanager')

@section('title', $extension->getName())

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <i class="{{ $extension->getIcon() }}"></i>
        {{ t($extension->getName()) }}
    </h1>
    <div class="d-flex gap-2">
        @if(in_array('write', $extension->getPermissions()))
            <a href="{{ route("sitemanager.extensions.{$extensionKey}.create") }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> {{ t('Create New') }}
            </a>
        @endif
        <a href="{{ route("sitemanager.extensions.{$extensionKey}.export", ['format' => 'csv']) }}" class="btn btn-outline-secondary">
            <i class="bi bi-download"></i> {{ t('Export') }}
        </a>
    </div>
</div>

{{-- Search and Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route("sitemanager.extensions.{$extensionKey}.index") }}" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="{{ t('Search...') }}" value="{{ $search }}">
                </div>
            </div>

            @foreach($filters as $field => $config)
                <div class="col-md-2">
                    @if(isset($config['type']) && $config['type'] === 'select')
                        <select name="filters[{{ $field }}]" class="form-select">
                            <option value="">{{ $config['label'] ?? ucfirst(str_replace('_', ' ', $field)) }}</option>
                            @foreach($config['options'] ?? [] as $value => $label)
                                @if(is_numeric($value))
                                    <option value="{{ $label }}" {{ ($currentFilters[$field] ?? '') === $label ? 'selected' : '' }}>
                                        {{ ucfirst($label) }}
                                    </option>
                                @else
                                    <option value="{{ $value }}" {{ ($currentFilters[$field] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="filters[{{ $field }}]" class="form-control"
                               placeholder="{{ $config['label'] ?? ucfirst(str_replace('_', ' ', $field)) }}"
                               value="{{ $currentFilters[$field] ?? '' }}">
                    @endif
                </div>
            @endforeach

            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-filter"></i> {{ t('Filter') }}
                </button>
            </div>

            @if($search || !empty(array_filter($currentFilters)))
                <div class="col-md-2">
                    <a href="{{ route("sitemanager.extensions.{$extensionKey}.index") }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> {{ t('Clear') }}
                    </a>
                </div>
            @endif
        </form>
    </div>
</div>

{{-- Results Table --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40">
                            <input type="checkbox" class="form-check-input" id="select-all">
                        </th>
                        @foreach($columns as $field => $config)
                            @php
                                $label = is_array($config) ? ($config['label'] ?? ucfirst(str_replace('_', ' ', $field))) : ucfirst(str_replace('_', ' ', $field));
                                $sortable = is_array($config) ? ($config['sortable'] ?? true) : true;
                                $isSorted = $sortBy === $field;
                                $nextDir = $isSorted && $sortDir === 'asc' ? 'desc' : 'asc';
                            @endphp
                            <th>
                                @if($sortable)
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'dir' => $nextDir]) }}" class="text-decoration-none text-dark">
                                        {{ t($label) }}
                                        @if($isSorted)
                                            <i class="bi bi-arrow-{{ $sortDir === 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                @else
                                    {{ t($label) }}
                                @endif
                            </th>
                        @endforeach
                        <th width="120">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input item-checkbox" value="{{ $item->id }}">
                            </td>
                            @foreach($columns as $field => $config)
                                <td>
                                    @php
                                        $value = str_contains($field, '.') ? data_get($item, $field) : $item->{$field};
                                        $format = is_array($config) ? ($config['format'] ?? null) : null;
                                        $badge = is_array($config) ? ($config['badge'] ?? false) : false;
                                    @endphp

                                    @if($badge && $value)
                                        <span class="badge bg-{{ $value === 'completed' || $value === 'active' || $value === 'registered' ? 'success' : ($value === 'pending' ? 'warning' : ($value === 'failed' || $value === 'cancelled' || $value === 'expired' ? 'danger' : 'secondary')) }}">
                                            {{ $value }}
                                        </span>
                                    @elseif($format === 'datetime' && $value)
                                        {{ $value instanceof \Carbon\Carbon ? $value->format('Y-m-d H:i') : $value }}
                                    @elseif($format === 'date' && $value)
                                        {{ $value instanceof \Carbon\Carbon ? $value->format('Y-m-d') : $value }}
                                    @elseif($format === 'money' && $value)
                                        ${{ number_format($value, 2) }}
                                    @else
                                        {{ Str::limit($value, 50) }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route("sitemanager.extensions.{$extensionKey}.show", $item->id) }}" class="btn btn-outline-primary" title="{{ t('View') }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if(in_array('write', $extension->getPermissions()))
                                        <a href="{{ route("sitemanager.extensions.{$extensionKey}.edit", $item->id) }}" class="btn btn-outline-secondary" title="{{ t('Edit') }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if(in_array('manage', $extension->getPermissions()))
                                        <form action="{{ route("sitemanager.extensions.{$extensionKey}.destroy", $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ t('Are you sure you want to delete this item?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="{{ t('Delete') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 2 }}" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2 mb-0">{{ t('No items found') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($items->hasPages())
        <div class="card-footer">
            {{ $items->links() }}
        </div>
    @endif
</div>

{{-- Bulk Actions --}}
<div class="mt-3" id="bulk-actions" style="display: none;">
    <form action="{{ route("sitemanager.extensions.{$extensionKey}.bulk-action") }}" method="POST" id="bulk-form">
        @csrf
        <input type="hidden" name="ids" id="bulk-ids">
        <div class="btn-group">
            <button type="button" class="btn btn-danger" onclick="bulkAction('delete')">
                <i class="bi bi-trash"></i> {{ t('Delete Selected') }}
            </button>
        </div>
        <span class="ms-2 text-muted" id="selected-count">0 {{ t('items selected') }}</span>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const bulkActions = document.getElementById('bulk-actions');
        const selectedCount = document.getElementById('selected-count');

        function updateBulkActions() {
            const checked = document.querySelectorAll('.item-checkbox:checked');
            const count = checked.length;
            bulkActions.style.display = count > 0 ? 'block' : 'none';
            selectedCount.textContent = count + ' {{ t("items selected") }}';
        }

        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });
    });

    function bulkAction(action) {
        if (!confirm('{{ t("Are you sure you want to perform this action on the selected items?") }}')) {
            return;
        }

        const checked = document.querySelectorAll('.item-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);

        document.getElementById('bulk-ids').value = JSON.stringify(ids);

        const form = document.getElementById('bulk-form');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = action;
        form.appendChild(input);

        form.submit();
    }
</script>
@endpush
@endsection
