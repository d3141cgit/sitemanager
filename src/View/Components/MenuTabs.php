<?php

namespace SiteManager\View\Components;

use Illuminate\View\Component;

class MenuTabs extends Component
{
    public $tabs;
    public $variant;
    public $alignment;

    /**
     * Create a new component instance.
     */
    public function __construct($tabs = [], $variant = 'tabs', $alignment = 'left')
    {
        $this->tabs = $tabs;
        $this->variant = $variant; // 'tabs', 'pills', 'underline'
        $this->alignment = $alignment; // 'left', 'center', 'right'
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        // 프로젝트에 커스텀 뷰가 있는지 확인 (Laravel 표준 경로)
        if (view()->exists('components.menu-tabs')) {
            return view('components.menu-tabs');
        }
        
        // 없으면 패키지 기본 뷰 사용
        return view('sitemanager::components.menu-tabs');
    }
}
