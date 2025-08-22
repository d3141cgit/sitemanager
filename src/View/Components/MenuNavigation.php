<?php

namespace SiteManager\View\Components;

use Illuminate\View\Component;

class MenuNavigation extends Component
{
    public $breadcrumb;
    public $tabs;
    public $showBreadcrumb;
    public $showTabs;
    public $variant;
    public $separator;

    /**
     * Create a new component instance.
     */
    public function __construct(
        $breadcrumb = [], 
        $tabs = [], 
        $showBreadcrumb = true, 
        $showTabs = true, 
        $variant = 'tabs',
        $separator = '/'
    ) {
        $this->breadcrumb = $breadcrumb;
        $this->tabs = $tabs;
        $this->showBreadcrumb = $showBreadcrumb;
        $this->showTabs = $showTabs;
        $this->variant = $variant;
        $this->separator = $separator;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        // 프로젝트에 커스텀 뷰가 있는지 확인 (Laravel 표준 경로)
        if (view()->exists('components.menu-navigation')) {
            return view('components.menu-navigation');
        }
        
        // 없으면 패키지 기본 뷰 사용
        return view('sitemanager::components.menu-navigation');
    }
}
