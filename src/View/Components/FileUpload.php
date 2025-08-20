<?php

namespace SiteManager\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FileUpload extends Component
{
    public $name;
    public $id;
    public $multiple;
    public $maxFileSize;
    public $maxFiles;
    public $allowedTypes;
    public $enablePreview;
    public $enableEdit;
    public $showFileInfo;
    public $showGuidelines;
    public $existingAttachments;
    public $label;
    public $icon;
    public $required;
    public $errors;
    public $class;
    public $wrapperClass;

    /**
     * Create a new component instance.
     */
    public function __construct(
        $name = 'files[]',
        $id = 'files',
        $multiple = true,
        $maxFileSize = 10240, // KB
        $maxFiles = 10,
        $allowedTypes = null,
        $enablePreview = true,
        $enableEdit = true,
        $showFileInfo = true,
        $showGuidelines = true,
        $existingAttachments = null,
        $label = 'Attachments',
        $icon = 'bi-paperclip',
        $required = false,
        $errors = null,
        $class = '',
        $wrapperClass = 'mb-3'
    ) {
        $this->name = $name;
        $this->id = $id;
        $this->multiple = $multiple;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->allowedTypes = $allowedTypes ?? config('sitemanager.board.allowed_extensions');
        $this->enablePreview = $enablePreview;
        $this->enableEdit = $enableEdit;
        $this->showFileInfo = $showFileInfo;
        $this->showGuidelines = $showGuidelines;
        $this->existingAttachments = $existingAttachments;
        $this->label = $label;
        $this->icon = $icon;
        $this->required = $required;
        $this->errors = $errors;
        $this->class = $class;
        $this->wrapperClass = $wrapperClass;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('sitemanager::components.file-upload');
    }
}
