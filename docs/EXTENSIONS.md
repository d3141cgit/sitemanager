# SiteManager Extensions Guide

SiteManager의 확장 시스템을 사용하여 관리자 패널에 새로운 모듈을 추가하는 방법을 설명합니다.

## 개요

Extensions 시스템은 sitemanager 패키지를 수정하지 않고도 각 프로젝트에서 관리자 패널을 확장할 수 있게 해줍니다.

### 주요 기능
- **Config 기반 등록**: `config/sitemanager.php`에서 간편하게 확장 등록
- **Class 기반 등록**: 복잡한 로직이 필요한 경우 Extension 클래스 생성
- **자동 라우트 등록**: CRUD 라우트 자동 생성
- **자동 메뉴 등록**: 관리자 사이드바에 메뉴 자동 추가
- **Member 관계 확장**: Member 모델에 동적으로 관계 추가

---

## 빠른 시작

### 1. Config에 Extension 등록

```php
// config/sitemanager.php

return [
    // ... 기존 설정들

    'extensions' => [
        'products' => [
            'name' => 'Products',
            'icon' => 'bi-box',
            'model' => \App\Models\Product::class,
            'controller' => \App\Http\Controllers\SiteManager\ProductController::class,
            'menu_position' => 50,
        ],
    ],
];
```

### 2. Controller 생성

```php
<?php
// app/Http/Controllers/SiteManager/ProductController.php

namespace App\Http\Controllers\SiteManager;

use App\Models\Product;
use Illuminate\Http\Request;
use SiteManager\Http\Controllers\ExtensionController;

class ProductController extends ExtensionController
{
    protected string $extensionKey = 'products';
    protected string $modelClass = Product::class;

    protected function applySearch($query, string $search)
    {
        return $query->where('name', 'like', "%{$search}%");
    }

    protected function validateUpdate(Request $request, $item): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);
    }
}
```

### 3. 완료!

`/sitemanager/extensions/products`에서 Products 관리 가능

---

## 상세 설정

### Extension Config 옵션

```php
'extensions' => [
    'products' => [
        // 필수 설정
        'name' => 'Products',                    // 메뉴에 표시될 이름
        'model' => \App\Models\Product::class,   // Eloquent 모델 클래스
        'controller' => \App\Http\Controllers\SiteManager\ProductController::class,

        // 선택 설정
        'icon' => 'bi-box',                      // Bootstrap Icons 클래스
        'menu_position' => 50,                   // 메뉴 순서 (낮을수록 위)
        'enabled' => true,                       // 활성화 여부
        'permissions' => ['index', 'read', 'write', 'manage'], // 권한 목록

        // 목록 페이지 설정
        'list_columns' => [
            'id' => ['label' => 'ID', 'sortable' => true],
            'name' => ['label' => 'Name', 'sortable' => true],
            'price' => ['label' => 'Price', 'sortable' => true, 'format' => 'money'],
            'status' => ['label' => 'Status', 'sortable' => true, 'badge' => true],
            'created_at' => ['label' => 'Date', 'sortable' => true, 'format' => 'datetime'],
        ],

        // 검색 가능한 필드
        'searchable' => ['name', 'description', 'sku'],

        // 필터 설정
        'filterable' => [
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => ['active', 'inactive', 'draft'],
            ],
            'category_id' => [
                'type' => 'select',
                'label' => 'Category',
                'options' => [], // 동적으로 설정 가능
            ],
        ],

        // Member 관계 설정
        'member_relation' => 'products',         // Member에 추가할 관계명
        'member_foreign_key' => 'member_id',     // 외래키 컬럼명
    ],
],
```

### 컬럼 포맷 옵션

| Format | 설명 | 예시 |
|--------|------|------|
| `datetime` | 날짜/시간 형식 | 2024-01-15 14:30 |
| `date` | 날짜 형식 | 2024-01-15 |
| `money` | 금액 형식 | $1,234.56 |
| `badge` | 상태 뱃지 | <span class="badge">active</span> |

### 뱃지 색상 자동 매핑

| 값 | 색상 |
|----|------|
| completed, active, registered, fully_paid | 녹색 (success) |
| pending, deposit_paid | 노랑 (warning) |
| failed, cancelled, expired, refunded | 빨강 (danger) |
| 기타 | 회색 (secondary) |

---

## Controller 작성 가이드

### ExtensionController 상속

모든 Extension Controller는 `SiteManager\Http\Controllers\ExtensionController`를 상속합니다.

```php
<?php

namespace App\Http\Controllers\SiteManager;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SiteManager\Http\Controllers\ExtensionController;

class OrderController extends ExtensionController
{
    // 필수: Extension 키 (config의 키와 동일)
    protected string $extensionKey = 'orders';

    // 필수: 모델 클래스
    protected string $modelClass = Order::class;

    // 선택: 페이지당 아이템 수 (기본값: 20)
    protected int $perPage = 30;
}
```

### 필수 메서드 구현

#### applySearch()
검색 로직을 정의합니다.

```php
protected function applySearch($query, string $search)
{
    return $query->where(function ($q) use ($search) {
        $q->where('order_number', 'like', "%{$search}%")
          ->orWhere('customer_name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
    });
}
```

#### validateUpdate()
수정 시 유효성 검사 규칙을 정의합니다.

```php
protected function validateUpdate(Request $request, $item): array
{
    return $request->validate([
        'status' => 'required|in:pending,processing,completed,cancelled',
        'notes' => 'nullable|string|max:1000',
    ]);
}
```

### 선택적 메서드 오버라이드

#### validateStore()
생성 시 유효성 검사 (기본값: 모든 입력 허용)

```php
protected function validateStore(Request $request): array
{
    return $request->validate([
        'customer_id' => 'required|exists:customers,id',
        'items' => 'required|array|min:1',
    ]);
}
```

#### applyFilters()
필터 적용 로직 커스터마이징

```php
protected function applyFilters($query, array $filters)
{
    foreach ($filters as $field => $value) {
        if ($value === '' || $value === null) continue;

        if ($field === 'date_range') {
            $dates = explode(' - ', $value);
            $query->whereBetween('created_at', $dates);
        } else {
            $query->where($field, $value);
        }
    }
    return $query;
}
```

#### applyEagerLoading()
관계 로딩 설정

```php
protected function applyEagerLoading($query)
{
    return $query->with(['customer', 'items', 'payments']);
}
```

#### applySorting()
정렬 로직 커스터마이징

```php
protected function applySorting($query, string $sortBy, string $sortDir)
{
    if ($sortBy === 'customer_name') {
        return $query->join('customers', 'orders.customer_id', '=', 'customers.id')
                     ->orderBy('customers.name', $sortDir)
                     ->select('orders.*');
    }

    return $query->orderBy($sortBy, $sortDir);
}
```

#### handleBulkAction()
대량 작업 처리

```php
protected function handleBulkAction(string $action, $item): bool
{
    switch ($action) {
        case 'delete':
            $item->delete();
            return true;
        case 'mark_completed':
            $item->update(['status' => 'completed']);
            return true;
        case 'export':
            // 내보내기 로직
            return true;
        default:
            return false;
    }
}
```

#### beforeDestroy() / afterDestroy()
삭제 전/후 훅

```php
protected function beforeDestroy($item): void
{
    // 관련 파일 삭제
    Storage::delete($item->attachments);
}

protected function afterDestroy($item): void
{
    // 로그 기록
    activity()->log("Order {$item->id} deleted");
}
```

### 뷰 커스터마이징

기본 뷰를 사용하지 않고 커스텀 뷰를 사용하려면:

```php
// 1. 뷰 파일 생성
// resources/views/sitemanager/extensions/orders/index.blade.php
// resources/views/sitemanager/extensions/orders/show.blade.php
// resources/views/sitemanager/extensions/orders/edit.blade.php

// 2. Controller에서 자동 감지됨 (우선순위)
//    1순위: resources/views/sitemanager/extensions/{key}/{view}.blade.php
//    2순위: sitemanager::extensions.{view} (패키지 기본)
```

---

## Class 기반 Extension

복잡한 로직이 필요한 경우 Extension 클래스를 직접 생성할 수 있습니다.

### 1. Extension 클래스 생성

```php
<?php
// app/SiteManager/Extensions/OrderExtension.php

namespace App\SiteManager\Extensions;

use App\Models\Order;
use SiteManager\Contracts\ExtensionInterface;

class OrderExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'Orders';
    }

    public function getIcon(): string
    {
        return 'bi-cart';
    }

    public function getSlug(): string
    {
        return 'orders';
    }

    public function getModel(): ?string
    {
        return Order::class;
    }

    public function getController(): ?string
    {
        return \App\Http\Controllers\SiteManager\OrderController::class;
    }

    public function getRoutePrefix(): string
    {
        return 'sitemanager/extensions/orders';
    }

    public function getViewPrefix(): string
    {
        return 'sitemanager.extensions.orders';
    }

    public function getMenuPosition(): int
    {
        return 60;
    }

    public function getPermissions(): array
    {
        return ['index', 'read', 'write', 'manage'];
    }

    public function getListColumns(): array
    {
        return [
            'id' => ['label' => 'ID', 'sortable' => true],
            'order_number' => ['label' => 'Order #', 'sortable' => true],
            'total' => ['label' => 'Total', 'sortable' => true, 'format' => 'money'],
            'status' => ['label' => 'Status', 'sortable' => true, 'badge' => true],
        ];
    }

    public function getSearchableFields(): array
    {
        return ['order_number', 'customer_name', 'email'];
    }

    public function getFilters(): array
    {
        return [
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => Order::getStatuses(), // 동적 옵션
            ],
        ];
    }

    public function getMemberRelation(): ?string
    {
        return 'orders';
    }

    public function getMemberRelationDefinition(): ?callable
    {
        return function ($member) {
            return $member->hasMany(Order::class, 'member_id');
        };
    }

    public function getStatistics(): array
    {
        return [
            'total' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'today_revenue' => Order::whereDate('created_at', today())
                                   ->where('status', 'completed')
                                   ->sum('total'),
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function boot(): void
    {
        // 초기화 로직 (이벤트 리스너 등록 등)
    }
}
```

### 2. 자동 로드

Extension 클래스는 `app/SiteManager/Extensions/` 디렉토리에 `*Extension.php` 패턴으로 저장하면 자동으로 로드됩니다.

---

## Member 관계 확장

Extension을 통해 Member 모델에 동적으로 관계를 추가할 수 있습니다.

### Config 방식

```php
'extensions' => [
    'orders' => [
        // ...
        'member_relation' => 'orders',
        'member_foreign_key' => 'member_id', // 기본값
    ],
],
```

### 사용 예시

```php
// Member 모델에서 바로 사용 가능
$member = Member::find(1);
$orders = $member->orders; // 동적 관계 사용
```

### 커스텀 관계 정의

복잡한 관계가 필요한 경우:

```php
'extensions' => [
    'orders' => [
        // ...
        'member_relation' => 'orders',
        'member_relation_definition' => function ($member) {
            return $member->hasMany(\App\Models\Order::class, 'customer_id')
                          ->where('status', '!=', 'cancelled');
        },
    ],
],
```

---

## 라우트

Extension은 자동으로 다음 라우트를 생성합니다:

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | /sitemanager/extensions/{key} | sitemanager.extensions.{key}.index | index |
| GET | /sitemanager/extensions/{key}/create | sitemanager.extensions.{key}.create | create |
| POST | /sitemanager/extensions/{key} | sitemanager.extensions.{key}.store | store |
| GET | /sitemanager/extensions/{key}/{id} | sitemanager.extensions.{key}.show | show |
| GET | /sitemanager/extensions/{key}/{id}/edit | sitemanager.extensions.{key}.edit | edit |
| PUT/PATCH | /sitemanager/extensions/{key}/{id} | sitemanager.extensions.{key}.update | update |
| DELETE | /sitemanager/extensions/{key}/{id} | sitemanager.extensions.{key}.destroy | destroy |
| POST | /sitemanager/extensions/{key}/bulk-action | sitemanager.extensions.{key}.bulk-action | bulkAction |
| GET | /sitemanager/extensions/{key}/export/{format?} | sitemanager.extensions.{key}.export | export |

---

## 통계 (Dashboard)

Extension에서 `getStatistics()` 메서드를 구현하면 대시보드에 통계가 표시됩니다.

```php
public function getStatistics(): array
{
    return [
        'total' => Order::count(),
        'today' => Order::whereDate('created_at', today())->count(),
        'pending' => Order::where('status', 'pending')->count(),
        'revenue' => Order::where('status', 'completed')->sum('total'),
    ];
}
```

---

## 예제: 완전한 Extension 구현

### 1. 모델 (이미 존재하는 경우 스킵)

```php
<?php
// app/Models/Ticket.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject',
        'description',
        'status',
        'priority',
        'member_id',
        'assigned_to',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function assignee()
    {
        return $this->belongsTo(Member::class, 'assigned_to');
    }
}
```

### 2. Config 등록

```php
// config/sitemanager.php

'extensions' => [
    'tickets' => [
        'name' => 'Support Tickets',
        'icon' => 'bi-ticket-detailed',
        'model' => \App\Models\Ticket::class,
        'controller' => \App\Http\Controllers\SiteManager\TicketController::class,
        'menu_position' => 55,
        'permissions' => ['index', 'read', 'write', 'manage'],
        'list_columns' => [
            'id' => ['label' => 'ID', 'sortable' => true],
            'subject' => ['label' => 'Subject', 'sortable' => true],
            'status' => ['label' => 'Status', 'sortable' => true, 'badge' => true],
            'priority' => ['label' => 'Priority', 'sortable' => true, 'badge' => true],
            'created_at' => ['label' => 'Created', 'sortable' => true, 'format' => 'datetime'],
        ],
        'searchable' => ['subject', 'description'],
        'filterable' => [
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => ['open', 'in_progress', 'resolved', 'closed'],
            ],
            'priority' => [
                'type' => 'select',
                'label' => 'Priority',
                'options' => ['low', 'medium', 'high', 'urgent'],
            ],
        ],
        'member_relation' => 'tickets',
    ],
],
```

### 3. Controller

```php
<?php
// app/Http/Controllers/SiteManager/TicketController.php

namespace App\Http\Controllers\SiteManager;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SiteManager\Http\Controllers\ExtensionController;

class TicketController extends ExtensionController
{
    protected string $extensionKey = 'tickets';
    protected string $modelClass = Ticket::class;

    protected function applyEagerLoading($query)
    {
        return $query->with(['member', 'assignee']);
    }

    protected function applySearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhereHas('member', function ($mq) use ($search) {
                  $mq->where('name', 'like', "%{$search}%");
              });
        });
    }

    protected function validateStore(Request $request): array
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'member_id' => 'nullable|exists:members,id',
        ]);
    }

    protected function validateUpdate(Request $request, $item): array
    {
        return $request->validate([
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:members,id',
        ]);
    }

    protected function handleBulkAction(string $action, $item): bool
    {
        switch ($action) {
            case 'delete':
                $item->delete();
                return true;
            case 'close':
                $item->update(['status' => 'closed']);
                return true;
            case 'assign_to_me':
                $item->update(['assigned_to' => auth()->id()]);
                return true;
            default:
                return false;
        }
    }

    // 커스텀 상세 뷰
    public function show($id): View
    {
        $item = Ticket::with(['member', 'assignee'])->findOrFail($id);
        $extension = $this->getExtension();

        return view($this->getView('show'), [
            'extension' => $extension,
            'extensionKey' => $this->extensionKey,
            'item' => $item,
            'statusOptions' => ['open', 'in_progress', 'resolved', 'closed'],
        ]);
    }
}
```

---

## 트러블슈팅

### Extension이 메뉴에 표시되지 않음

1. Config 캐시 클리어: `php artisan config:clear`
2. `enabled` 옵션 확인 (기본값: true)
3. Controller 클래스 존재 확인

### 라우트 오류

1. Controller가 올바른 네임스페이스에 있는지 확인
2. `extensionKey`가 config의 키와 일치하는지 확인

### 뷰 오류

1. 커스텀 뷰 경로 확인: `resources/views/sitemanager/extensions/{key}/`
2. 뷰 캐시 클리어: `php artisan view:clear`

### Member 관계 오류

1. 모델에 `member_id` 컬럼이 있는지 확인
2. 마이그레이션 실행 확인

---

## API Reference

### ExtensionInterface

```php
interface ExtensionInterface
{
    public function getName(): string;
    public function getIcon(): string;
    public function getSlug(): string;
    public function getModel(): ?string;
    public function getController(): ?string;
    public function getRoutePrefix(): string;
    public function getViewPrefix(): string;
    public function getMenuPosition(): int;
    public function getPermissions(): array;
    public function getListColumns(): array;
    public function getSearchableFields(): array;
    public function getFilters(): array;
    public function getMemberRelation(): ?string;
    public function getMemberRelationDefinition(): ?callable;
    public function getStatistics(): array;
    public function isEnabled(): bool;
    public function boot(): void;
}
```

### ExtensionManager

```php
// 확장 모듈 가져오기
$manager = app(\SiteManager\Services\ExtensionManager::class);

// 모든 확장 모듈
$extensions = $manager->all();

// 특정 확장 모듈
$extension = $manager->get('orders');

// 메뉴 아이템
$menuItems = $manager->getMenuItems();

// 대시보드 통계
$stats = $manager->getDashboardStats();
```

---

## 변경 이력

- **v1.0.0** (2024-12-26): 최초 릴리스
  - Config 기반 Extension 등록
  - Class 기반 Extension 등록
  - 자동 라우트/메뉴 등록
  - Member 동적 관계
  - 기본 CRUD 뷰 템플릿
