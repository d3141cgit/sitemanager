<?php

if (!function_exists('sortHead')) {
    /**
     * 정렬 가능한 테이블 헤더 생성
     * 
     * @param string $title 헤더 제목
     * @param string $orderby 정렬 필드명
     * @param string $class 추가 CSS 클래스
     * @return string
     */
    function sortHead($title, $orderby, $class = '')
    {
        $currentOrderby = request()->get('orderby');
        $currentDesc = request()->get('desc');
        $isActive = !empty($currentOrderby) && $currentOrderby == $orderby;
        
        $classes = [];
        if ($class) {
            $classes[] = $class;
        }
        if ($isActive) {
            $classes[] = 'orderby';
        }
        
        $classAttr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        
        $html = '<th' . $classAttr . '>';
        $html .= '<a href="javascript: void(0);" onclick="sort(\'' . htmlspecialchars($orderby, ENT_QUOTES, 'UTF-8') . '\');">';
        $html .= htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $html .= '</a>';
        
        if ($isActive) {
            if (!empty($currentDesc)) {
                $html .= ' <i class="bi bi-arrow-down"></i>';
            } else {
                $html .= ' <i class="bi bi-arrow-up"></i>';
            }
        }
        
        $html .= '</th>';
        
        return $html;
    }
}

