{{-- 통합 메뉴 네비게이션 컴포넌트 --}}
<div class="menu-navigation">
    @if($showBreadcrumb && $breadcrumb && count($breadcrumb) > 0)
        <x-sitemanager::menu-breadcrumb 
            :breadcrumb="$breadcrumb" 
            :separator="$separator" />
    @endif

    @if($showTabs && $tabs && count($tabs) > 1)
        <x-sitemanager::menu-tabs 
            :tabs="$tabs" 
            :variant="$variant" />
    @endif
</div>
