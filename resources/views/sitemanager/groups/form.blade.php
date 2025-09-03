@extends('sitemanager::layouts.sitemanager')

@section('title', isset($group) ? t('Edit Group') : t('Create Group'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-collection"></i>
                        {{ isset($group) ? t('Edit Group') : t('Create Group') }}
                    </h5>
                    <a href="{{ route('sitemanager.groups.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> {{ t('Back to List') }}
                    </a>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ isset($group) ? route('sitemanager.groups.update', $group) : route('sitemanager.groups.store') }}">
                        @csrf
                        @if(isset($group))
                            @method('PUT')
                        @endif

                        <!-- Group Name -->
                        <div class="row mb-3 align-items-center">
                            <label for="name" class="col-md-3 col-form-label text-md-end">{{ t('Group Name') }}</label>
                            <div class="col-md-9">
                                <input id="name" type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       name="name" 
                                       value="{{ old('name', isset($group) ? $group->name : '') }}" 
                                       required autofocus>
                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="row mb-3 align-items-center">
                            <label for="description" class="col-md-3 col-form-label text-md-end">{{ t('Description') }}</label>
                            <div class="col-md-9">
                                <textarea id="description" 
                                          class="form-control @error('description') is-invalid @enderror" 
                                          name="description" 
                                          rows="3">{{ old('description', isset($group) ? $group->description : '') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div class="row mb-3 align-items-center">
                            <label for="active" class="col-md-3 col-form-label text-md-end">{{ t('Status') }}</label>
                            <div class="col-md-9">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="active" 
                                           name="active" 
                                           value="1"
                                           {{ old('active', isset($group) ? $group->active : true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="active">
                                        {{ t('Active') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        @if(isset($group))
                            <!-- Group Members -->
                            <div class="row mb-3 align-items-center">
                                <label class="col-md-3 col-form-label text-md-end">{{ t('Members') }}</label>
                                <div class="col-md-9">
                                    <div class="row">
                                        <!-- Current Members -->
                                        <div class="col-md-6">
                                            <h6>{{ t('Current Members') }}</h6>
                                            <div class="border rounded p-3" style="min-height: 200px; max-height: 300px; overflow-y: auto;">
                                                @forelse($group->members as $member)
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               name="members[]" 
                                                               value="{{ $member->id }}" 
                                                               id="member_{{ $member->id }}" 
                                                               checked>
                                                        <label class="form-check-label" for="member_{{ $member->id }}">
                                                            {{ $member->name }} ({{ $member->username }})
                                                        </label>
                                                    </div>
                                                @empty
                                                    <p class="text-muted">{{ t('No members in this group') }}</p>
                                                @endforelse
                                            </div>
                                        </div>
                                        
                                        <!-- Available Members -->
                                        <div class="col-md-6">
                                            <h6>{{ t('Available Members') }}</h6>
                                            <div class="border rounded p-3" style="min-height: 200px; max-height: 300px; overflow-y: auto;">
                                                @forelse($availableMembers as $member)
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               name="members[]" 
                                                               value="{{ $member->id }}" 
                                                               id="member_{{ $member->id }}">
                                                        <label class="form-check-label" for="member_{{ $member->id }}">
                                                            {{ $member->name }} ({{ $member->username }})
                                                        </label>
                                                    </div>
                                                @empty
                                                    <p class="text-muted">{{ t('No available members') }}</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Submit Button -->
                        <div class="row mb-0">
                            <div class="col-md-9 offset-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i>
                                    {{ isset($group) ? t('Update Group') : t('Create Group') }}
                                </button>
                                <a href="{{ route('sitemanager.groups.index') }}" class="btn btn-secondary ms-2">
                                    <i class="bi bi-x-circle"></i> {{ t('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
