@extends('sitemanager::layouts.sitemanager')

@section('title', t('System Settings'))

@push('styles')
<style>
.table .col-type { min-width: 100px; }
.table .col-key { min-width: 200px; }
.table .col-value { width: auto; min-width: 200px; }
.table .col-action { width: 50px; min-width: 45px; text-align: center; }
.form-control-sm, .form-select-sm { font-size: 0.75rem; padding: 0.2rem 0.4rem; }
.btn-sm { padding: 0.2rem 0.4rem; font-size: 0.75rem; }

@media (min-width: 768px) {
    .table .col-type { width: 120px; min-width: 100px; }
    .table .col-key { width: 200px; min-width: 150px; }
    .table .col-value { min-width: 200px; }
    .table .col-action { width: 80px; min-width: 70px; }
    .form-control-sm, .form-select-sm { font-size: 0.875rem; padding: 0.375rem 0.75rem; }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.875rem; }
    .badge { font-size: 0.75rem; }
}
</style>
@endpush

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">
                    <i class="bi bi-gear me-2"></i>{{ t('System Settings') }}
                </h1>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-sliders me-2"></i>{{ t('Configuration') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <form name="config-form" method="post" action="{{ route('sitemanager.settings.process-config') }}">
                                @csrf

                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="col-type">{{ t('Type') }}</th>
                                                <th class="col-key">{{ t('Key') }}</th>
                                                <th class="col-value">{{ t('Value') }}</th>
                                                <th class="col-action">{{ t('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($configs as $config)
                                            @php
                                                $isSystemConfig = \SiteManager\Services\ConfigService::isSystemConfig($config->key);
                                            @endphp
                                            <tr @if($isSystemConfig) class="table-warning" @endif>
                                                <td>
                                                    @if (preg_match('/^cfg\./', $config->type) || $isSystemConfig)
                                                        <input type="text" name="type[{{ $config->id }}]" class="form-control form-control-sm" value="{{ $config->type }}" readonly>
                                                    @else
                                                        <select name="type[{{ $config->id }}]" class="form-select form-select-sm">
                                                            @foreach ($cfg_type as $type)
                                                                <option value="{{ $type }}" @selected($config->type == $type)>{{ $type }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="text" name="key[{{ $config->id }}]" class="form-control form-control-sm" value="{{ $config->key }}" @if($isSystemConfig) readonly @endif autocomplete="off">
                                                </td>
                                                <td>
                                                    @if ($config->type == 'bool' || $config->type == 'cfg.bool')
                                                        <div class="form-check">
                                                            <input type="hidden" name="val[{{ $config->id }}]" value="false">
                                                            <input type="checkbox" class="form-check-input" name="val[{{ $config->id }}]" value="true" @checked($config->value == 'true' || $config->value == true)>
                                                            <label class="form-check-label text-muted small">
                                                                {{ $config->value == 'true' || $config->value == true ? t('Enabled') : t('Disabled') }}
                                                            </label>
                                                        </div>
                                                    @else
                                                        <input type="text" class="form-control form-control-sm" name="val[{{ $config->id }}]" value="{{ $config->value }}" autocomplete="off">
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isSystemConfig)
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-lock"></i> {{ t('System') }}
                                                        </span>
                                                    @else
                                                        <button type="button" class="btn btn-outline-danger btn-sm delete-config" data-id="{{ $config->id }}">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach

                                            <!-- 새 설정 추가 행 -->
                                            <tr class="table-info">
                                                <td>
                                                    <select name="new_type" class="form-select form-select-sm">
                                                        @foreach ($cfg_type as $type)
                                                            <option value="{{ $type }}">{{ $type }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="new_key" class="form-control form-control-sm" placeholder="{{ t('New Key') }}" autocomplete="off">
                                                </td>
                                                <td>
                                                    <input type="text" name="new_val" class="form-control form-control-sm" placeholder="{{ t('New Value') }}" autocomplete="off">
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">{{ t('New') }}</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>{{ t('Save Changes') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>{{ t('Information') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6>{{ t('Configuration Types') }}</h6>
                                <ul class="list-unstyled small">
                                    <li><strong>cfg.text:</strong> {{ t('System environment variables (synced with .env)') }}</li>
                                    <li><strong>cfg.bool:</strong> {{ t('System boolean settings (synced with .env)') }}</li>
                                    <li><strong>text:</strong> {{ t('Application text settings') }}</li>
                                    <li><strong>bool:</strong> {{ t('Application boolean settings') }}</li>
                                </ul>
                            </div>
                            
                            <div class="mb-3">
                                <h6>{{ t('Usage Notes') }}</h6>
                                <ul class="list-unstyled small text-muted">
                                    <li>• {{ t('Settings with') }} <code>cfg.</code> {{ t('prefix will update your .env file') }}</li>
                                    <li>• <span class="badge bg-warning text-dark">{{ t('System') }}</span> {{ t('settings are protected and cannot be deleted') }}</li>
                                    <li>• {{ t('Key names should use alphanumeric characters, underscores, and hyphens only') }}</li>
                                    <li>• {{ t('Boolean values are stored as \'true\' or \'false\' strings') }}</li>
                                    <li>• {{ t('Changes take effect immediately after saving') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-tools me-2"></i>{{ t('Other Actions') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <form method="POST" action="{{ route('sitemanager.settings.reset-config') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100 reset-config-btn">
                                        <i class="bi bi-arrow-clockwise me-1"></i>{{ t('Reset Configuration') }}
                                    </button>
                                </form>
                                
                                <form method="POST" action="{{ route('sitemanager.settings.reset-resources') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning btn-sm w-100 reset-resources-btn">
                                        <i class="bi bi-files me-1"></i>{{ t('Reset Resources') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 설정 삭제 버튼 처리
    document.querySelectorAll('.delete-config').forEach(function(button) {
        button.addEventListener('click', function() {
            Swal.fire({
                title: '{{ t("Delete Setting") }}',
                text: '{{ t("Are you sure you want to delete this setting?") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '{{ t("Delete") }}',
                cancelButtonText: '{{ t("Cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    const row = this.closest('tr');
                    const configId = this.dataset.id;
                    
                    // 키 필드를 비워서 삭제 표시
                    const keyInput = row.querySelector(`input[name="key[${configId}]"]`);
                    if (keyInput) {
                        keyInput.value = '';
                        row.style.opacity = '0.5';
                        this.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                        this.classList.remove('btn-outline-danger');
                        this.classList.add('btn-outline-success');
                        this.title = '{{ t("Undo delete") }}';
                        
                        // Undo 기능
                        this.onclick = function() {
                            keyInput.value = keyInput.dataset.originalValue || '';
                            row.style.opacity = '1';
                            this.innerHTML = '<i class="bi bi-trash"></i>';
                            this.classList.remove('btn-outline-success');
                            this.classList.add('btn-outline-danger');
                            this.title = '{{ t("Delete") }}';
                        };
                    }
                }
            });
        });
    });

    // 설정 초기화 버튼 처리
    document.querySelector('.reset-config-btn').addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '{{ t("Reset Configuration") }}',
            text: '{{ t("Are you sure you want to reset all settings to default values? User-added settings will be deleted.") }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '{{ t("Reset") }}',
            cancelButtonText: '{{ t("Cancel") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                this.closest('form').submit();
            }
        });
    });

    // 리소스 초기화 버튼 처리
    document.querySelector('.reset-resources-btn').addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '{{ t("Reset Resources") }}',
            text: '{{ t("Are you sure you want to reset all resource files? CSS/JS cache will be deleted and regenerated.") }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '{{ t("Reset") }}',
            cancelButtonText: '{{ t("Cancel") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                this.closest('form').submit();
            }
        });
    });

    // Boolean 체크박스 라벨 업데이트
    document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
        if (checkbox.name.startsWith('val[')) {
            checkbox.addEventListener('change', function() {
                const label = this.nextElementSibling;
                if (label) {
                    label.textContent = this.checked ? '{{ t("Enabled") }}' : '{{ t("Disabled") }}';
                }
            });
        }
    });
});
</script>
@endpush
