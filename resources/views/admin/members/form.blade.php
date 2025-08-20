@extends('sitemanager::layouts.admin')

@section('title', isset($member) ? 'Edit Member - ' . $member->name : 'Add New Member')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h1>
                @if(isset($member))
                    <i class="bi bi-pencil"></i> Edit Member - {{ $member->name }}
                @else
                    <i class="bi bi-person-plus"></i> Add New Member
                @endif
            </h1>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ isset($member) ? route('admin.members.update', $member) : route('admin.members.store') }}" enctype="multipart/form-data">
                        @csrf
                        @if(isset($member))
                            @method('PUT')
                        @endif

                        <div class="row mb-3 align-items-center">
                            <label for="username" class="col-md-3 col-form-label text-md-end">Username</label>
                            <div class="col-md-9">
                                <input type="text" 
                                       class="form-control @error('username') is-invalid @enderror" 
                                       id="username" 
                                       name="username" 
                                       value="{{ old('username', isset($member) ? $member->username : '') }}" 
                                       @if(isset($member) && $member->id == 1) readonly @endif
                                       required>
                                @error('username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if(isset($member) && $member->id == 1)
                                    <div class="form-text text-warning">Root user's username cannot be changed.</div>
                                @else
                                    <div class="form-text">Username must be unique.</div>
                                @endif
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="name" class="col-md-3 col-form-label text-md-end">Name</label>
                            <div class="col-md-9">
                                <input type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       id="name" 
                                       name="name" 
                                       value="{{ old('name', isset($member) ? $member->name : '') }}" 
                                       required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="email" class="col-md-3 col-form-label text-md-end">Email</label>
                            <div class="col-md-9">
                                <input type="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       id="email" 
                                       name="email" 
                                       value="{{ old('email', isset($member) ? $member->email : '') }}"
                                       required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="phone" class="col-md-3 col-form-label text-md-end">Phone</label>
                            <div class="col-md-9">
                                <input type="text" 
                                       class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" 
                                       name="phone" 
                                       value="{{ old('phone', isset($member) ? $member->phone : '') }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Profile Photo Section -->
                        <div class="row mb-3 align-items-center">
                            <label for="profile_photo" class="col-md-3 col-form-label text-md-end">Profile Photo</label>
                            <div class="col-md-9">
                                <div class="profile-photo-manager">
                                    @if(isset($member) && $member->profile_photo)
                                        <div class="current-photo mb-3">
                                            <img src="{{ $member->profile_photo_url }}" 
                                                 alt="{{ $member->name }}'s profile photo" 
                                                 class="profile-photo-preview">
                                            <div class="photo-actions mt-2">
                                                <button type="button" class="btn btn-danger btn-sm" id="remove-photo">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    <div class="photo-upload">
                                        <input type="file" 
                                               class="form-control @error('profile_photo') is-invalid @enderror" 
                                               id="profile_photo" 
                                               name="profile_photo" 
                                               accept="image/*">
                                        <div class="form-text">Upload a new profile photo (JPEG, PNG, GIF). Max size: 2MB</div>
                                        @error('profile_photo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                    <!-- Preview for new upload -->
                                    <div class="new-photo-preview mt-3" style="display: none;">
                                        <img src="" alt="New photo preview" class="profile-photo-preview">
                                    </div>
                                    
                                    <!-- Hidden input to mark photo for removal -->
                                    <input type="hidden" name="remove_profile_photo" id="remove_profile_photo" value="0">
                                </div>
                            </div>
                        </div>

                        @if(Auth::user()->isAdmin())
                            <div class="row mb-3 align-items-center">
                                <label for="level" class="col-md-3 col-form-label text-md-end">Member Level</label>
                                <div class="col-md-9">
                                    <select class="form-select @error('level') is-invalid @enderror" 
                                            id="level" 
                                            name="level">
                                        @foreach($levels as $levelValue => $levelName)
                                            <option value="{{ $levelValue }}" {{ old('level', isset($member) ? $member->level : 1) == $levelValue ? 'selected' : '' }}>
                                                {{ $levelValue }} - {{ $levelName }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('level')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3 align-items-center">
                                <label class="col-md-3 col-form-label text-md-end">Account Status</label>
                                <div class="col-md-9">
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               class="form-check-input @error('active') is-invalid @enderror" 
                                               id="active" 
                                               name="active" 
                                               value="1"
                                               {{ old('active', isset($member) ? $member->active : true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="active">
                                            Active Account
                                        </label>
                                        @error('active')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">
                                            When deactivated, the member cannot log in.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Groups Selection -->
                        <div class="row mb-3 align-items-center">
                            <label class="col-md-3 col-form-label text-md-end">Groups</label>
                            <div class="col-md-9">
                                @if($groups->count() > 0)
                                    @foreach($groups as $group)
                                        <div class="form-check">
                                            <input type="checkbox" 
                                                   class="form-check-input" 
                                                   id="group_{{ $group->id }}" 
                                                   name="groups[]" 
                                                   value="{{ $group->id }}"
                                                   @if(isset($member) && $member->groups->contains($group->id)) checked @endif>
                                            <label class="form-check-label" for="group_{{ $group->id }}">
                                                {{ $group->name }}
                                            </label>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-muted">No groups available.</p>
                                @endif
                            </div>
                        </div>

                        <hr>

                        <h5 class="mb-3">
                            @if(isset($member))
                                Change Password (Optional)
                            @else
                                Password
                            @endif
                        </h5>
                        @if(isset($member))
                            <p class="text-muted small">Leave the password fields empty if you don't want to change the password.</p>
                        @endif

                        <div class="row mb-3 align-items-center">
                            <label for="password" class="col-md-3 col-form-label text-md-end">
                                @if(isset($member))
                                    New Password
                                @else
                                    Password
                                @endif
                            </label>
                            <div class="col-md-9">
                                <input type="password" 
                                       class="form-control @error('password') is-invalid @enderror" 
                                       id="password" 
                                       name="password"
                                       @if(!isset($member)) required @endif>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="password_confirmation" class="col-md-3 col-form-label text-md-end">Confirm Password</label>
                            <div class="col-md-9">
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirmation" 
                                       name="password_confirmation"
                                       @if(!isset($member)) required @endif>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-9 offset-md-3">
                                <button type="submit" class="btn btn-primary">
                                    @if(isset($member))
                                        Update Member
                                    @else
                                        Create Member
                                    @endif
                                </button>
                                <a href="{{ route('admin.members.index') }}" class="btn btn-secondary ms-2">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
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

    // File input change handler
    if (profilePhotoInput) {
        profilePhotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select a valid image file.');
                    this.value = '';
                    return;
                }

                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB.');
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
            if (confirm('Are you sure you want to remove the current profile photo?')) {
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
