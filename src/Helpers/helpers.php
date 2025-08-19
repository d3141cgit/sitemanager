<?php

// This file contains global helper functions for the application

if (!function_exists('config_get')) {
    /**
     * Get configuration value with default
     */
    function config_get($key, $default = null)
    {
        return config($key, $default);
    }
}

if (!function_exists('setResources')) {
    /**
     * Set CSS/JS resources (placeholder - implement based on your resource management system)
     */
    function setResources($resources)
    {
        // This is a placeholder function - you should implement based on your existing resource system
        // For now, return basic Bootstrap and jQuery CDN links
        $output = '';
        
        if (is_array($resources)) {
            foreach ($resources as $resource) {
                switch ($resource) {
                    case 'bootstrap':
                        $output .= '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' . "\n";
                        $output .= '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>' . "\n";
                        break;
                    case 'jquery':
                        $output .= '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>' . "\n";
                        break;
                }
            }
        }
        
        return $output;
    }
}

if (!function_exists('resource')) {
    /**
     * Include a CSS or JS resource
     */
    function resource($path)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $assetPath = asset('asset/' . $path);
        
        switch ($extension) {
            case 'css':
                return '<link rel="stylesheet" href="' . $assetPath . '">';
            case 'js':
                return '<script src="' . $assetPath . '"></script>';
            default:
                return '';
        }
    }
}
