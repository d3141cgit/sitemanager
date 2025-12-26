@extends('sitemanager::layouts.sitemanager')

@section('title', $extension->getName() . ' - ' . t('View'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <i class="{{ $extension->getIcon() }}"></i>
        {{ t($extension->getName()) }}
    </h1>
    <div class="d-flex gap-2">
        <a href="{{ route("sitemanager.extensions.{$extensionKey}.index") }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ t('Back to List') }}
        </a>
        @if(in_array('write', $extension->getPermissions()))
            <a href="{{ route("sitemanager.extensions.{$extensionKey}.edit", $item->id) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> {{ t('Edit') }}
            </a>
        @endif
        @if(in_array('manage', $extension->getPermissions()))
            <form action="{{ route("sitemanager.extensions.{$extensionKey}.destroy", $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ t('Are you sure you want to delete this item?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash"></i> {{ t('Delete') }}
                </button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">{{ t('Details') }}</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <tbody>
                    @foreach($item->getAttributes() as $key => $value)
                        @if(!in_array($key, ['password', 'remember_token']))
                            <tr>
                                <th width="200" class="bg-light">{{ t(ucfirst(str_replace('_', ' ', $key))) }}</th>
                                <td>
                                    @if(is_null($value))
                                        <span class="text-muted">-</span>
                                    @elseif(is_bool($value) || in_array($key, ['active', 'enabled', 'verified']))
                                        @if($value)
                                            <span class="badge bg-success">{{ t('Yes') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ t('No') }}</span>
                                        @endif
                                    @elseif(in_array($key, ['status', 'type', 'payment_status']))
                                        <span class="badge bg-{{ $value === 'completed' || $value === 'active' || $value === 'registered' || $value === 'fully_paid' ? 'success' : ($value === 'pending' || $value === 'deposit_paid' ? 'warning' : ($value === 'failed' || $value === 'cancelled' || $value === 'expired' || $value === 'refunded' ? 'danger' : 'secondary')) }}">
                                            {{ $value }}
                                        </span>
                                    @elseif(in_array($key, ['created_at', 'updated_at', 'deleted_at', 'paid_at', 'email_verified_at', 'token_expires_at']))
                                        @if($value)
                                            {{ \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s') }}
                                            <small class="text-muted">({{ \Carbon\Carbon::parse($value)->diffForHumans() }})</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    @elseif(in_array($key, ['amount', 'total_amount', 'deposit_amount', 'price']))
                                        ${{ number_format($value, 2) }}
                                    @elseif(is_array($value) || is_object($value))
                                        <pre class="mb-0 bg-light p-2 rounded"><code>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                    @elseif(strlen($value) > 100)
                                        <div style="white-space: pre-wrap; max-height: 200px; overflow-y: auto;">{{ $value }}</div>
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Related Data (if available) --}}
@php
    $relations = method_exists($item, 'getRelations') ? $item->getRelations() : [];
@endphp

@foreach($relations as $relationName => $relationData)
    @if($relationData && (is_countable($relationData) ? count($relationData) > 0 : true))
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">{{ t(ucfirst(str_replace('_', ' ', $relationName))) }}</h5>
            </div>
            <div class="card-body">
                @if($relationData instanceof \Illuminate\Database\Eloquent\Collection)
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    @if($relationData->first())
                                        @foreach(array_keys($relationData->first()->getAttributes()) as $col)
                                            @if(!in_array($col, ['password', 'remember_token']))
                                                <th>{{ t(ucfirst(str_replace('_', ' ', $col))) }}</th>
                                            @endif
                                        @endforeach
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($relationData as $relatedItem)
                                    <tr>
                                        @foreach($relatedItem->getAttributes() as $col => $val)
                                            @if(!in_array($col, ['password', 'remember_token']))
                                                <td>{{ Str::limit($val, 30) }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif(is_object($relationData))
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <tbody>
                                @foreach($relationData->getAttributes() as $key => $value)
                                    @if(!in_array($key, ['password', 'remember_token']))
                                        <tr>
                                            <th width="200" class="bg-light">{{ t(ucfirst(str_replace('_', ' ', $key))) }}</th>
                                            <td>{{ $value }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endforeach

<div class="mt-4">
    <a href="{{ route("sitemanager.extensions.{$extensionKey}.index") }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> {{ t('Back to List') }}
    </a>
</div>
@endsection
