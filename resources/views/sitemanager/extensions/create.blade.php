@extends('sitemanager::layouts.sitemanager')

@section('title', $extension->getName() . ' - ' . t('Create'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <i class="{{ $extension->getIcon() }}"></i>
        {{ t($extension->getName()) }} - {{ t('Create') }}
    </h1>
    <div class="d-flex gap-2">
        <a href="{{ route("sitemanager.extensions.{$extensionKey}.index") }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ t('Back to List') }}
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">{{ t('Create New Item') }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route("sitemanager.extensions.{$extensionKey}.store") }}" method="POST">
            @csrf

            @php
                $modelClass = $extension->getModel();
                $model = $modelClass ? new $modelClass() : null;
                $fillable = $model && method_exists($model, 'getFillable') ? $model->getFillable() : [];
                $hidden = $model && method_exists($model, 'getHidden') ? $model->getHidden() : ['password', 'remember_token'];
                $casts = $model && method_exists($model, 'getCasts') ? $model->getCasts() : [];

                // Fields to exclude from create form
                $excludeFields = ['id', 'uuid', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'token', 'token_expires_at'];
            @endphp

            @if(!empty($fillable))
                @foreach($fillable as $field)
                    @if(!in_array($field, $excludeFields) && !in_array($field, $hidden))
                        @php
                            $value = old($field);
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
                                       value="{{ $value }}">
                            @elseif($type === 'date' || str_ends_with($field, '_date'))
                                <input type="date" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}"
                                       value="{{ $value }}">
                            @elseif($type === 'integer' || $type === 'int' || str_ends_with($field, '_id') || str_ends_with($field, '_weeks'))
                                <input type="number" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}" value="{{ $value }}">
                            @elseif($type === 'float' || $type === 'double' || $type === 'decimal' || in_array($field, ['amount', 'total_amount', 'deposit_amount', 'price']))
                                <input type="number" step="0.01" class="form-control @error($field) is-invalid @enderror"
                                       id="{{ $field }}" name="{{ $field }}" value="{{ $value }}">
                            @elseif($type === 'array' || $type === 'json')
                                <textarea class="form-control @error($field) is-invalid @enderror"
                                          id="{{ $field }}" name="{{ $field }}" rows="4">{{ $value }}</textarea>
                                <small class="text-muted">{{ t('Enter valid JSON') }}</small>
                            @elseif(in_array($field, ['message', 'special_requests', 'description', 'content', 'notes']))
                                <textarea class="form-control @error($field) is-invalid @enderror"
                                          id="{{ $field }}" name="{{ $field }}" rows="4">{{ $value }}</textarea>
                            @elseif($field === 'status' || $field === 'payment_status')
                                <select class="form-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}">
                                    @if($field === 'status')
                                        <option value="pending" {{ $value === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="registered" {{ $value === 'registered' ? 'selected' : '' }}>Registered</option>
                                        <option value="completed" {{ $value === 'completed' ? 'selected' : '' }}>Completed</option>
                                    @elseif($field === 'payment_status')
                                        <option value="pending" {{ $value === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="deposit_paid" {{ $value === 'deposit_paid' ? 'selected' : '' }}>Deposit Paid</option>
                                        <option value="fully_paid" {{ $value === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
                                    @endif
                                </select>
                            @elseif($field === 'type')
                                <select class="form-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}">
                                    <option value="deposit" {{ $value === 'deposit' ? 'selected' : '' }}>Deposit</option>
                                    <option value="balance" {{ $value === 'balance' ? 'selected' : '' }}>Balance</option>
                                    <option value="full" {{ $value === 'full' ? 'selected' : '' }}>Full</option>
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
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    {{ t('This model does not have fillable fields defined. Please create a custom form.') }}
                </div>
            @endif

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route("sitemanager.extensions.{$extensionKey}.index") }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> {{ t('Cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> {{ t('Create') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
