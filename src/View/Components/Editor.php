<?php

namespace SiteManager\View\Components;

use Illuminate\View\Component;

class Editor extends Component
{
    public $name;
    public $id;
    public $value;
    public $placeholder;
    public $height;
    public $toolbar;
    public $required;

    /**
     * Create a new component instance.
     */
    public function __construct(
        $name = 'content',
        $id = null,
        $value = '',
        $placeholder = '내용을 입력하세요...',
        $height = 300,
        $toolbar = null,
        $required = false
    ) {
        $this->name = $name;
        $this->id = $id ?: $name;
        $this->value = old($name, $value);
        $this->placeholder = $placeholder;
        $this->height = $height;
        $this->required = $required;
        
        // 기본 툴바 설정
        $this->toolbar = $toolbar ?: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('sitemanager::components.editor');
    }
}
