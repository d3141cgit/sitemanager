@extends('sitemanager::layouts.sitemanager')

@section('title', isset($member) ? t('Edit Member') . ' - ' . $member->name : t('Add New Member'))

@section('content')

<div class="card default-form default-form-md">
    <div class="card-header bg-dark text-white">
        <h4>
        @if(isset($member))
            <i class="bi bi-pencil"></i> {{ t('Edit Member') }} - {{ $member->name }}
        @else
            <i class="bi bi-person-plus"></i> {{ t('Add New Member') }}
        @endif
        </h4>
    </div>

    <form method="POST" action="{{ isset($member) ? route('sitemanager.members.update', $member) : route('sitemanager.members.store') }}" enctype="multipart/form-data">
        @csrf
        @if(isset($member))
            @method('PUT')
        @endif

        <div class="card-body">
            <div class="row">
                <div class="col form-group">
                    <label for="username" class="col-form-label">{{ t('Username') }}</label>
                    <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', isset($member) ? $member->username : '') }}" @if(isset($member) && $member->id == 1) readonly @endif required>
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if(isset($member) && $member->id == 1)
                        <div class="form-text text-warning">{{ t("Root user's username cannot be changed.") }}</div>
                    @else
                        <div class="form-text">{{ t('Username must be unique.') }}</div>
                    @endif
                </div>

                <div class="col form-group">
                    <label for="email" class="col-form-label">{{ t('Email') }}</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', isset($member) ? $member->email : '') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="col form-group">
                    <label for="name" class="col-form-label">{{ t('Name') }}</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', isset($member) ? $member->name : '') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col form-group">
                    <label for="title" class="col-form-label">{{ t('Title') }}</label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', isset($member) ? $member->title : '') }}" placeholder="{{ t('Title or honorific (e.g., Mr., Mrs., etc.)') }}">
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="col form-group">
                    <label for="phone" class="col-form-label">{{ t('Phone') }}</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', isset($member) ? $member->phone : '') }}">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Profile Photo Section -->
                <div class="col form-group">
                    <label for="profile_photo" class="col-form-label">{{ t('Profile Photo') }}</label>
                    <div class="photo-upload">
                        <input type="file" class="form-control @error('profile_photo') is-invalid @enderror" id="profile_photo" name="profile_photo" accept="image/*">
                        <div class="form-text">{{ t('Upload a new profile photo (JPEG, PNG, GIF). Max size: 2MB') }}</div>
                        @error('profile_photo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="profile-photo-manager">
                        @if(isset($member) && $member->profile_photo)
                            <div class="current-photo">
                                <img src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}" class="profile-photo-preview">

                                <button type="button" class="btn btn-danger rounded-pill btn-sm" id="remove-photo">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        @endif

                        <!-- Preview for new upload -->
                        <div class="new-photo-preview" style="display: none;">
                            <img src="" alt="New photo preview" class="profile-photo-preview">
                        </div>
                        
                        <!-- Hidden input to mark photo for removal -->
                        <input type="hidden" name="remove_profile_photo" id="remove_profile_photo" value="0">
                    </div>
                </div>
            </div>

            @if(Auth::user()->isAdmin())
            <div class="row">
                <div class="col form-group">
                    <label class="col-form-label">{{ t('Account Status') }}</label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input @error('active') is-invalid @enderror" id="active" name="active" value="1" {{ old('active', isset($member) ? $member->active : true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            {{ t('Active Account') }}
                        </label>
                        @error('active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            {{ t('When deactivated, the member cannot log in.') }}
                        </div>
                    </div>
                </div>

                <div class="col form-group">
                    <label for="level" class="col-form-label">{{ t('Member Level') }}</label>

                    <select class="form-select @error('level') is-invalid @enderror" id="level" name="level">
                        @php
                            $currentLevel = old('level', isset($member) ? $member->level : 1);
                            $hasCurrentLevel = false;
                            
                            // $levels가 있는지 확인하고, 현재 레벨이 포함되어 있는지 체크
                            if ($levels && count($levels) > 0) {
                                $hasCurrentLevel = array_key_exists($currentLevel, $levels);
                            }
                        @endphp
                    
                        @foreach($levels as $levelValue => $levelName)
                            <option value="{{ $levelValue }}" {{ (string)$currentLevel === (string)$levelValue ? 'selected' : '' }}>
                                {{ $levelValue }} - {{ $levelName }}
                            </option>
                        @endforeach
                        
                        {{-- 현재 레벨이 $levels에 없으면 추가 --}}
                        @if(!$hasCurrentLevel && isset($member))
                            <option value="{{ $currentLevel }}" selected>
                                {{ $currentLevel }}
                            </option>
                        @endif
                    </select>
                    @error('level')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            @endif

            <!-- Groups Selection -->
            <div class="form-group">
                <label class="col-form-label">{{ t('Groups') }}</label>
                @if($groups->count() > 0)
                <div class="groups">
                    @foreach($groups as $group)
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="group_{{ $group->id }}" name="groups[]" value="{{ $group->id }}" @if(isset($member) && $member->groups->contains($group->id)) checked @endif>
                            <label class="form-check-label" for="group_{{ $group->id }}"> {{ $group->name }} </label>
                        </div>
                    @endforeach
                </div>
                @else
                    <p class="text-muted">{{ t('No groups available.') }}</p>
                @endif
            </div>

            <h5>
                @if(isset($member))
                    {{ t('Change Password (Optional)') }}
                @else
                    {{ t('Password') }}
                @endif
            </h5>
            @if(isset($member))
                <p class="text-muted small">{{ t('Leave the password fields empty if you don\'t want to change the password.') }}</p>
            @endif

            <div class="row">
                <div class="col form-group">
                    <label for="password" class="col-form-label">
                        @if(isset($member))
                            {{ t('New Password') }}
                        @else
                            {{ t('Password') }}
                        @endif
                    </label>

                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" @if(!isset($member)) required @endif>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col form-group">
                    <label for="password_confirmation" class="col-form-label">{{ t('Confirm Password') }}</label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" @if(!isset($member)) required @endif>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-danger">
                @if(isset($member))
                    {{ t('Update Member') }}
                @else
                    {{ t('Create Member') }}
                @endif
            </button>
            <a href="{{ route('sitemanager.members.index') }}" class="btn btn-secondary ms-2">{{ t('Cancel') }}</a>
        </div>
    </form>

</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalLabel">{{ t('Profile Photo') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" alt="{{ t('Profile Photo') }}" class="img-fluid" id="modalPhoto">
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const profilePhotoInput = document.getElementById('profile_photo');
    const removePhotoBtn = document.getElementById('remove-photo');
    const removePhotoInput = document.getElementById('remove_profile_photo');
    const currentPhoto = document.querySelector('.current-photo');
    const newPhotoPreview = document.querySelector('.new-photo-preview');
    const newPhotoImg = newPhotoPreview ? newPhotoPreview.querySelector('img') : null;
    const photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
    const modalPhoto = document.getElementById('modalPhoto');

    // Profile photo preview click handler for modal
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('profile-photo-preview')) {
            const imgSrc = e.target.src;
            if (imgSrc && imgSrc !== '') {
                modalPhoto.src = imgSrc;
                photoModal.show();
            }
        }
    });

    // File input change handler
    if (profilePhotoInput) {
        profilePhotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('{{ t('Please select a valid image file.') }}');
                    this.value = '';
                    return;
                }

                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('{{ t('File size must be less than 2MB.') }}');
                    this.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (newPhotoImg) {
                        newPhotoImg.src = e.target.result;
                        newPhotoPreview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);

                // Reset remove flag
                if (removePhotoInput) {
                    removePhotoInput.value = '0';
                }
            } else {
                // Hide preview if no file selected
                if (newPhotoPreview) {
                    newPhotoPreview.style.display = 'none';
                }
            }
        });
    }

    // Remove photo button handler
    if (removePhotoBtn) {
        removePhotoBtn.addEventListener('click', function() {
            if (confirm('{{ t('Are you sure you want to remove the current profile photo?') }}')) {
                // Hide current photo
                if (currentPhoto) {
                    currentPhoto.style.display = 'none';
                }
                
                // Set remove flag
                if (removePhotoInput) {
                    removePhotoInput.value = '1';
                }
                
                // Clear file input
                if (profilePhotoInput) {
                    profilePhotoInput.value = '';
                }
                
                // Hide new photo preview
                if (newPhotoPreview) {
                    newPhotoPreview.style.display = 'none';
                }
            }
        });
    }
});
</script>
@endpush
