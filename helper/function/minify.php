<?php

if (!function_exists('minifyJs')) {

    /** Formata JS mantendo quebras de linha */
    function minifyJs(string $js): string
    {
        $preserved = [];

        $js = preg_replace_callback('/`(?:\\\\`|[^`])*`/s', function ($m) use (&$preserved) {
            $key = '@@JS_TEMPLATE_' . count($preserved) . '@@';
            $preserved[$key] = $m[0];
            return $key;
        }, $js);

        $js = preg_replace_callback("/'(?:\\\\'|[^'])*'/s", function ($m) use (&$preserved) {
            $key = '@@JS_SINGLE_' . count($preserved) . '@@';
            $preserved[$key] = $m[0];
            return $key;
        }, $js);

        $js = preg_replace_callback('/"(?:\\\\"|[^"])*"/s', function ($m) use (&$preserved) {
            $key = '@@JS_DOUBLE_' . count($preserved) . '@@';
            $preserved[$key] = $m[0];
            return $key;
        }, $js);

        $js = preg_replace('!/\*.*?\*/!s', '', $js);
        $js = preg_replace('/\s*\/\/[^\n\r]*/', '', $js);

        $js = preg_replace('/[ \t]+/', ' ', $js);
        $js = preg_replace('/^\s+/m', '', $js);

        $js = strtr($js, $preserved);
        $js = strtr($js, $preserved);
        $js = strtr($js, $preserved);

        return $js;
    }
}

if (!function_exists('minifyCss')) {

    /** Formata CSS mantendo quebras de linha */
    function minifyCss(string $css): string
    {
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/[ \t]+/', ' ', $css);
        $css = preg_replace('/^\s+/m', '', $css);

        return $css;
    }
}

if (!function_exists('minifyHtml')) {

    /** Formata HTML com uma quebra de linha */
    function minifyHtml(string $html): string
    {
        $preserved = [];

        $html = preg_replace_callback(
            '#<(script|style)(.*?)>(.*?)</\1>#is',
            function ($matches) use (&$preserved) {
                $key = '@@MINIFY_BLOCK_' . count($preserved) . '@@';
                $type = strtolower($matches[1]);
                $content = $matches[3];

                if ($type === 'script') {
                    $content = minifyJs($content);
                } else {
                    $content = minifyCss($content);
                }

                $preserved[$key] = "<{$type}{$matches[2]}>{$content}</{$type}>";
                return $key;
            },
            $html
        );

        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);

        $html = preg_replace('/(>)(?=<)/', "$1\n", $html);
        $html = preg_replace('/</', "\n<", $html);
        $html = preg_replace('/>\s*/', ">\n", $html);
        $html = preg_replace("/\n{2,}/", "\n", $html);
        $html = preg_replace('/^\s+/m', '', $html);

        $html = strtr($html, $preserved);

        return trim($html);
    }
}
