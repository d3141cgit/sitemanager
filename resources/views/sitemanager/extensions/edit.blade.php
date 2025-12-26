@extends('sitemanager::layouts.sitemanager')

@section('title', $extension->getName() . ' - ' . t('Edit'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <i class="{{ $extension->getIcon() }}"></i>
        {{ t($extension->getName()) }} - {{ t('Edit') }}
    </h1>
    <div class="d-flex gap-2">
        <a href="{{ route("sitemanager.extensions.{$extensionKey}.show", $item->id) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ t('Back') }}
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">{{ t('Edit Item') }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route("sitemanager.extensions.{$extensionKey}.update", $item->id) }}" method="POST">
            @csrf
            @method('PUT')

            @php
                $fillable = method_exists($item, 'getFillable') ? $item->getFillable() : [];
                $hidden = method_exists($item, 'getHidden') ? $item->getHidden() : ['password', 'remember_token'];
                $casts = method_exists($item, 'getCasts') ? $item->getCasts() : [];

                // Fields to exclude from edit form
                $excludeFields = ['id', 'uuid', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'token'];
            @endphp

            @if(!empty($fillable))
                @foreach($fillable as $field)
                    @if(!in_array($field, $excludeFields) && !in_array($field, $hidden))
                        @php
                            $value = old($field, $item->{$field});
                            $type = $casts[$field] ?? 'string';
                            $label = ucfirst(str_replace('_', ' ', $field));
                        @endphp

                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label">{{ t($label) }}</label>

                            @if($type === 'boolean' || in_array($field, ['active', 'enabled', 'verified', 'terms_agreed', 'privacy_agreed', 'marketing_agreed', 'accommodation_required']))
                                <div class="form-check form-switch">
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <input type="checkbox" class="form-check-input @error($field) is-invalid @enderror"
                                           id="{{ $field }}" name="{{ $field }}" value="1"
                                           {{ $value ? 'checked' : '' }}>
                                    <label class="form-check-label" for="{{ $field }}">{{ t('Enabled') }}</label>
                                </div>
                            @elseif($type === 'datetime' || str_ends_with($field, '_at'))
                                <input type="datetime-local" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}"
                                       value="{{ $value ? \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i') : '' }}">
                            @elseif($type === 'date' || str_ends_with($field, '_date'))
                                <input type="date" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}"
                                       value="{{ $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '' }}">
                            @elseif($type === 'integer' || $type === 'int' || str_ends_with($field, '_id') || str_ends_with($field, '_weeks'))
                                <input type="number" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}" value="{{ $value }}">
                            @elseif($type === 'float' || $type === 'double' || $type === 'decimal' || in_array($field, ['amount', 'total_amount', 'deposit_amount', 'price']))
                                <input type="number" step="0.01" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}" value="{{ $value }}">
                            @elseif($type === 'array' || $type === 'json')
                                <textarea class="form-control @error($field) is-invalid @enderror"
                                          id="{{ $field }}" name="{{ $field }}" rows="4">{{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}</textarea>
                                <small class="text-muted">{{ t('Enter valid JSON') }}</small>
                            @elseif(in_array($field, ['message', 'special_requests', 'description', 'content', 'notes']))
                                <textarea class="form-control @error($field) is-invalid @enderror"
                                          id="{{ $field }}" name="{{ $field }}" rows="4">{{ $value }}</textarea>
                            @elseif($field === 'status' || $field === 'payment_status')
                                <select class="form-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}">
                                    @if($field === 'status')
                                        <option value="pending" {{ $value === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="registered" {{ $value === 'registered' ? 'selected' : '' }}>Registered</option>
                                        <option value="expired" {{ $value === 'expired' ? 'selected' : '' }}>Expired</option>
                                        <option value="cancelled" {{ $value === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        <option value="completed" {{ $value === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="failed" {{ $value === 'failed' ? 'selected' : '' }}>Failed</option>
                                    @elseif($field === 'payment_status')
                                        <option value="pending" {{ $value === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="deposit_paid" {{ $value === 'deposit_paid' ? 'selected' : '' }}>Deposit Paid</option>
                                        <option value="fully_paid" {{ $value === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
                                        <option value="refunded" {{ $value === 'refunded' ? 'selected' : '' }}>Refunded</option>
                                    @endif
                                </select>
                            @elseif($field === 'type')
                                <select class="form-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}">
                                    <option value="deposit" {{ $value === 'deposit' ? 'selected' : '' }}>Deposit</option>
                                    <option value="balance" {{ $value === 'balance' ? 'selected' : '' }}>Balance</option>
                                    <option value="full" {{ $value === 'full' ? 'selected' : '' }}>Full</option>
                                    <option value="refund" {{ $value === 'refund' ? 'selected' : '' }}>Refund</option>
                                </select>
                            @elseif($field === 'email')
                                <input type="email" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}" value="{{ $value }}">
                            @else
                                <input type="text" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}" value="{{ $value }}">
                            @endif

                            @error($field)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                @endforeach
            @else
                {{-- Fallback: show all attributes if fillable is not defined --}}
                @foreach($item->getAttributes() as $field => $value)
                    @if(!in_array($field, $excludeFields) && !in_array($field, $hidden))
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label">{{ t(ucfirst(str_replace('_', ' ', $field))) }}</label>
                            <input type="text" class="form-control @error($field) is-invalid @enderror"
                                   id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $value) }}">
                            @error($field)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                @endforeach
            @endif

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route("sitemanager.extensions.{$extensionKey}.show", $item->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> {{ t('Cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> {{ t('Save Changes') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
