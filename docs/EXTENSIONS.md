# SiteManager Extensions Guide

SiteManager의 확장 시스템을 사용하여 관리자 패널에 새로운 모듈을 추가하는 방법을 설명합니다.

## 개요

Extensions 시스템은 SiteManager 패키지를 수정하지 않고도 각 프로젝트에서 관리자 패널을 확장할 수 있게 해줍니다.

## Summary

### 설계 철학

- **SiteManager**: 메뉴 등록 + 권한 체크만 담당
- **Laravel**: 라우트, 컨트롤러, 뷰, 비즈니스 로직 모두 직접 관리
- **단순한 설정**: `name`, `icon`, `route`, `position` 만으로 메뉴 등록
- **URL 구조**: `/sitemanager/inquiries` (not `/sitemanager/extensions/inquiries`)
- **Member 확장**: 모델 상속 + `AUTH_MODEL` 환경변수로 관계 추가

---

## 빠른 시작

### 1. Config에 메뉴 등록

```php
// config/sitemanager.php

return [
    // ... 기존 설정들

    'extensions' => [
        'products' => [
            'name' => 'Products',           // 메뉴에 표시될 이름
            'icon' => 'bi-box',             // Bootstrap Icons 클래스
            'route' => 'sitemanager.products.index',  // 라우트 이름
            'position' => 50,               // 메뉴 순서 (낮을수록 위)
        ],
    ],
];
```

### 2. Laravel 라우트 등록

```php
// routes/web.php

use App\Http\Controllers\SiteManager\ProductController;

Route::prefix('sitemanager')->middleware(['web', 'auth', 'sitemanager'])->name('sitemanager.')->group(function () {
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
    });
});
```

### 3. Laravel 컨트롤러 생성

```php
<?php
// app/Http/Controllers/SiteManager/ProductController.php

namespace App\Http\Controllers\SiteManager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20);

        return view('sitemanager.products.index', compact('products'));
    }

    public function show(Product $product): View
    {
        return view('sitemanager.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('sitemanager.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $product->update($validated);

        return redirect()
            ->route('sitemanager.products.show', $product)
            ->with('success', t('Product updated successfully.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()
            ->route('sitemanager.products.index')
            ->with('success', t('Product deleted successfully.'));
    }
}
```

### 4. 뷰 생성

```blade
{{-- resources/views/sitemanager/products/index.blade.php --}}
@extends('sitemanager::layouts.sitemanager')

@section('title', t('Products'))

@section('content')
<h1>{{ t('Products') }}</h1>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ t('ID') }}</th>
                    <th>{{ t('Name') }}</th>
                    <th>{{ t('Price') }}</th>
                    <th>{{ t('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td>{{ $product->id }}</td>
                    <td>{{ $product->name }}</td>
                    <td>${{ number_format($product->price, 2) }}</td>
                    <td>
                        <a href="{{ route('sitemanager.products.show', $product) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{ $products->links() }}
    </div>
</div>
@endsection
```

### 5. 완료!

`/sitemanager/products`에서 Products 관리 가능

---

## Config 옵션

```php
'extensions' => [
    'extension_key' => [
        'name' => 'Extension Name',      // 필수: 메뉴에 표시될 이름
        'route' => 'sitemanager.xxx.index', // 필수: 라우트 이름
        'icon' => 'bi-puzzle',           // 선택: Bootstrap Icons (기본값: bi-puzzle)
        'position' => 100,               // 선택: 메뉴 순서 (기본값: 100)
        'enabled' => true,               // 선택: 활성화 여부 (기본값: true)
    ],
],
```

### Bootstrap Icons

아이콘은 [Bootstrap Icons](https://icons.getbootstrap.com/)에서 선택할 수 있습니다.

자주 사용되는 아이콘:
- `bi-envelope` - 메일/문의
- `bi-clipboard-check` - 등록/신청
- `bi-credit-card` - 결제
- `bi-cart` - 주문
- `bi-people` - 사용자
- `bi-gear` - 설정
- `bi-box` - 상품
- `bi-journal-text` - 게시판

---

## 실제 예제: bridge2korea

bridge2korea 프로젝트에서 Inquiries, Registrations, Payments 모듈을 추가한 예제입니다.

### Config 설정

```php
// config/sitemanager.php

'extensions' => [
    'inquiries' => [
        'name' => 'Inquiries',
        'icon' => 'bi-envelope',
        'route' => 'sitemanager.inquiries.index',
        'position' => 50,
    ],
    'registrations' => [
        'name' => 'Registrations',
        'icon' => 'bi-clipboard-check',
        'route' => 'sitemanager.registrations.index',
        'position' => 51,
    ],
    'payments' => [
        'name' => 'Payments',
        'icon' => 'bi-credit-card',
        'route' => 'sitemanager.payments.index',
        'position' => 52,
    ],
],
```

### 라우트 설정

```php
// routes/web.php

use App\Http\Controllers\SiteManager\InquiryController as AdminInquiryController;
use App\Http\Controllers\SiteManager\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\SiteManager\PaymentController as AdminPaymentController;

Route::prefix('sitemanager')->middleware(['web', 'auth', 'sitemanager'])->name('sitemanager.')->group(function () {
    // Inquiry Management
    Route::prefix('inquiries')->name('inquiries.')->group(function () {
        Route::get('/', [AdminInquiryController::class, 'index'])->name('index');
        Route::get('/{inquiry}', [AdminInquiryController::class, 'show'])->name('show');
        Route::delete('/{inquiry}', [AdminInquiryController::class, 'destroy'])->name('destroy');
    });

    // Registration Management
    Route::prefix('registrations')->name('registrations.')->group(function () {
        Route::get('/', [AdminRegistrationController::class, 'index'])->name('index');
        Route::get('/{registration}', [AdminRegistrationController::class, 'show'])->name('show');
        Route::get('/{registration}/edit', [AdminRegistrationController::class, 'edit'])->name('edit');
        Route::put('/{registration}', [AdminRegistrationController::class, 'update'])->name('update');
        Route::delete('/{registration}', [AdminRegistrationController::class, 'destroy'])->name('destroy');
    });

    // Payment Management
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [AdminPaymentController::class, 'index'])->name('index');
        Route::get('/{payment}', [AdminPaymentController::class, 'show'])->name('show');
    });
});
```

### 디렉토리 구조

```
app/
├── Http/Controllers/SiteManager/
│   ├── InquiryController.php
│   ├── RegistrationController.php
│   └── PaymentController.php
│
resources/views/sitemanager/
├── inquiries/
│   ├── index.blade.php
│   └── show.blade.php
├── registrations/
│   ├── index.blade.php
│   ├── show.blade.php
│   └── edit.blade.php
└── payments/
    ├── index.blade.php
    └── show.blade.php
```

---

## 뷰 레이아웃

모든 관리자 뷰는 `sitemanager::layouts.sitemanager` 레이아웃을 상속해야 합니다.

```blade
@extends('sitemanager::layouts.sitemanager')

@section('title', t('Page Title'))

@section('content')
    {{-- 페이지 내용 --}}
@endsection

@push('styles')
    {{-- 추가 스타일 --}}
@endpush

@push('scripts')
    {{-- 추가 스크립트 --}}
@endpush
```

### 사용 가능한 Helper

- `t('key')` - 다국어 번역
- `route('sitemanager.xxx.index')` - 라우트 URL 생성
- `auth()->user()` - 현재 로그인 사용자

### Flash Messages

컨트롤러에서 플래시 메시지를 설정하면 자동으로 SweetAlert2로 표시됩니다:

```php
return redirect()->back()->with('success', t('Operation completed successfully.'));
return redirect()->back()->with('error', t('Operation failed.'));
```

---

## Middleware

확장 모듈의 라우트에는 다음 미들웨어를 적용해야 합니다:

- `web` - Laravel 웹 미들웨어 그룹
- `auth` - 인증 확인
- `sitemanager` - SiteManager 관리자 권한 확인

```php
Route::prefix('sitemanager')
    ->middleware(['web', 'auth', 'sitemanager'])
    ->name('sitemanager.')
    ->group(function () {
        // ...
    });
```

---

## 메뉴 활성화 상태

SiteManager는 현재 라우트를 기반으로 메뉴 활성화 상태를 자동으로 감지합니다.

라우트 이름 규칙:
- `sitemanager.inquiries.index` → `sitemanager.inquiries.*` 패턴으로 활성화 감지

사이드바 메뉴에서는 `Str::beforeLast($ext['route'], '.')`를 사용하여 라우트 프리픽스를 추출하고, `request()->routeIs($routeBase . '.*')`로 활성화 상태를 확인합니다.

---

## Member 모델 확장

SiteManager의 Member 모델에 프로젝트별 관계나 메서드를 추가하려면 모델 상속 패턴을 사용합니다.

### 1. Member 모델 생성

```php
<?php
// app/Models/Member.php

namespace App\Models;

use SiteManager\Models\Member as BaseMember;

/**
 * Extended Member model
 *
 * SiteManager의 Member 모델을 상속받아
 * 프로젝트별 관계와 기능을 추가합니다.
 */
class Member extends BaseMember
{
    /**
     * Get the inquiries for the member.
     */
    public function inquiries()
    {
        return $this->hasMany(Inquiry::class, 'member_id');
    }

    /**
     * Get the registrations for the member.
     */
    public function registrations()
    {
        return $this->hasMany(Registration::class, 'member_id');
    }

    /**
     * Get the payments through registrations.
     */
    public function payments()
    {
        return $this->hasManyThrough(
            Payment::class,
            Registration::class,
            'member_id',
            'registration_id'
        );
    }

    /**
     * 커스텀 메서드 추가 가능
     */
    public function getTotalPaymentsAttribute()
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }
}
```

### 2. 환경 변수 설정

`.env` 파일에 `AUTH_MODEL`을 설정합니다:

```env
AUTH_MODEL=App\Models\Member
```

### 3. 사용 예시

```php
// 현재 로그인 사용자의 관계 접근
$member = auth()->user();
$member->inquiries;           // 문의 목록
$member->registrations;       // 등록 목록
$member->payments;            // 결제 목록
$member->total_payments;      // 총 결제 금액

// 쿼리 빌더 사용
$member = Member::find(1);
$member->inquiries()->where('status', 'pending')->get();
$member->registrations()->with('payments')->latest()->get();
```

### 장점

| 장점 | 설명 |
|------|------|
| **Laravel 표준 패턴** | 일반적인 모델 상속 방식 |
| **IDE 지원** | 자동완성, 타입 힌팅 완벽 지원 |
| **자유로운 확장** | 관계, 메서드, 속성, 스코프 자유롭게 추가 |
| **테스트 용이** | 모킹/스터빙이 쉬움 |
| **설정 간단** | `.env`에서 한 줄로 전환 |

### 주의사항

- `AUTH_MODEL` 설정 후 `php artisan config:clear` 실행 필요
- 기존 SiteManager의 모든 기능(groups, scopes 등)은 그대로 사용 가능
- 마이그레이션에서 `member_id` 외래키가 올바르게 설정되어 있어야 함

---

## 트러블슈팅

### 메뉴가 표시되지 않음

1. Config 캐시 클리어: `php artisan config:clear`
2. `enabled` 옵션이 `true`인지 확인
3. `route` 이름이 올바른지 확인

### 라우트 오류

1. 라우트가 정의되어 있는지 확인: `php artisan route:list | grep sitemanager`
2. 미들웨어가 올바르게 적용되어 있는지 확인
3. 컨트롤러 클래스와 메서드가 존재하는지 확인

### 뷰 오류

1. 뷰 파일 경로 확인: `resources/views/sitemanager/{module}/{view}.blade.php`
2. 레이아웃 상속 확인: `@extends('sitemanager::layouts.sitemanager')`
3. 뷰 캐시 클리어: `php artisan view:clear`

### 권한 오류

1. 사용자가 관리자 레벨인지 확인 (기본: level >= 200)
2. `sitemanager` 미들웨어가 적용되어 있는지 확인

---

## API Reference

### ExtensionManager

```php
// 서비스 가져오기
$manager = app(\SiteManager\Services\ExtensionManager::class);

// 모든 확장 모듈
$extensions = $manager->all();

// 특정 확장 모듈
$extension = $manager->get('inquiries');

// 확장 모듈 존재 여부
$exists = $manager->has('inquiries');

// 메뉴 아이템 (position으로 정렬됨)
$menuItems = $manager->getMenuItems();

// 확장 모듈 수
$count = $manager->count();
```

---

## 변경 이력

- **v2.1.0** (2024-12-26): Member 모델 확장 패턴 추가
  - 모델 상속을 통한 Member 관계 확장 방식 문서화
  - `AUTH_MODEL` 환경 변수를 통한 커스텀 모델 사용

- **v2.0.0** (2024-12-26): 확장 시스템 단순화
  - SiteManager는 메뉴 등록만 담당
  - 라우트, 컨트롤러, 뷰는 Laravel에서 직접 관리
  - Config 옵션 단순화 (name, icon, route, position만 필요)
  - 자동 라우트 등록 제거

- **v1.0.0** (2024-12-26): 최초 릴리스
