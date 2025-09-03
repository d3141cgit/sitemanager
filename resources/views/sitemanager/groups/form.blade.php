@extends('sitemanager::layouts.sitemanager')

@section('title', isset($group) ? t('Edit Group') : t('Create Group'))

@section('content')
<div class="card default-form default-form-md">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h4>
            <i class="bi bi-collection"></i>
            {{ isset($group) ? t('Edit Group') : t('Create Group') }}
        </h4>
        <a href="{{ route('sitemanager.groups.index') }}" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left"></i> {{ t('Back to List') }}
        </a>
    </div>

    <form method="POST" action="{{ isset($group) ? route('sitemanager.groups.update', $group) : route('sitemanager.groups.store') }}">
        @csrf
        @if(isset($group))
            @method('PUT')
        @endif

        <div class="card-body">
            <div class="row">
                <!-- Group Name -->
                <div class="col form-group">
                    <label for="name" class="col-form-label">{{ t('Group Name') }}</label>
                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', isset($group) ? $group->name : '') }}" required autofocus>
                    @error('name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Active Status -->
                <div class="col form-group">
                    <label for="active" class="col-form-label">{{ t('Status') }}</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', isset($group) ? $group->active : true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            {{ t('Active') }}
                        </label>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description" class="col-form-label">{{ t('Description') }}</label>
                <textarea id="description" class="form-control @error('description') is-invalid @enderror" name="description" rows="3">{{ old('description', isset($group) ? $group->description : '') }}</textarea>
                @error('description')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            @if(isset($group))
                <!-- Group Members -->
                <div class="row group-members">
                    <!-- Current Members -->
                    <div class="col form-group">
                        <label class="col-form-label">{{ t('Current Members') }}</label>
                        <div class="border rounded p-3" style="height: 300px; overflow-y: auto;">
                            @forelse($group->members as $member)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="members[]" value="{{ $member->id }}" id="member_{{ $member->id }}" checked>
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
                    <div class="col form-group">
                        <label class="col-form-label">{{ t('Available Members') }}</label>
                        <div class="border rounded p-3" style="height: 300px; overflow-y: auto;">
                            @forelse($availableMembers as $member)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="members[]" value="{{ $member->id }}" id="member_{{ $member->id }}">
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
            @endif
        </div>

        <!-- Submit Button -->
        <div class="card-footer">
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-check-circle"></i>
                {{ isset($group) ? t('Update Group') : t('Create Group') }}
            </button>
            <a href="{{ route('sitemanager.groups.index') }}" class="btn btn-secondary ms-2">
                <i class="bi bi-x-circle"></i> {{ t('Cancel') }}
            </a>
        </div>
    </form>

</div>
@endsection
