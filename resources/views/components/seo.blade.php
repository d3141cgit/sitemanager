{{-- SEO Meta Tags Component --}}
{{-- 컨트롤러에서 생성된 seoData 배열을 사용하여 SEO 메타태그와 구조화 데이터 출력 --}}

@if(isset($seoData))
    @push('head')
        {{-- Article-specific meta tags (if article type) --}}
        @if(isset($seoData['og_type']) && $seoData['og_type'] === 'article')
            <meta property="og:type" content="article">
            @if(!empty($seoData['article_author']))
            <meta property="article:author" content="{{ $seoData['article_author'] }}">
            @endif
            @if(!empty($seoData['article_published_time']))
            <meta property="article:published_time" content="{{ $seoData['article_published_time'] }}">
            @endif
            @if(!empty($seoData['article_modified_time']))
            <meta property="article:modified_time" content="{{ $seoData['article_modified_time'] }}">
            @endif
            @if(!empty($seoData['article_section']))
            <meta property="article:section" content="{{ $seoData['article_section'] }}">
            @endif
            @if(!empty($seoData['article_tag']))
            <meta property="article:tag" content="{{ $seoData['article_tag'] }}">
            @endif
        @endif

        {{-- JSON-LD Structured Data --}}
        {{-- 브레드크럼 JSON-LD (NavigationComposer에서 생성) --}}
        @if(isset($seoData['breadcrumb_json_ld']))
        <script type="application/ld+json">
        {!! json_encode($seoData['breadcrumb_json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
        @endif
        
        {{-- 컨텐츠 JSON-LD (컨트롤러에서 생성) --}}
        @if(isset($seoData['json_ld']))
        <script type="application/ld+json">
        {!! json_encode($seoData['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
        @endif
    @endpush
@endif
