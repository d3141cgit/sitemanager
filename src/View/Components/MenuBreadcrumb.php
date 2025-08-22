<?php

namespace SiteManager\View\Components;

use Illuminate\View\Component;

class MenuBreadcrumb extends Component
{
    public $breadcrumb;
    public $showHome;
    public $separator;

    /**
     * Create a new component instance.
     */
    public function __construct($breadcrumb = [], $showHome = true, $separator = '/')
    {
        $this->breadcrumb = $breadcrumb;
        $this->showHome = $showHome;
        $this->separator = $separator;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        // 프로젝트에 커스텀 뷰가 있는지 확인 (Laravel 표준 경로)
        if (view()->exists('components.menu-breadcrumb')) {
            return view('components.menu-breadcrumb');
        }
        
        // 없으면 패키지 기본 뷰 사용
        return view('sitemanager::components.menu-breadcrumb');
    }
}
