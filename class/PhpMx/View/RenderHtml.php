<?php

namespace PhpMx\View;

use PhpMx\View;

abstract class RenderHtml extends View
{
    protected static array $PREPARE_REPLACE = [
        '<!-- [#' => '[#',
        '] -->' => ']',
    ];

    /** Aplica ações extras ao renderizar uma view */
    protected static function renderizeAction(string $content): string
    {
        $content = str_replace(array_keys(self::$PREPARE_REPLACE), array_values(self::$PREPARE_REPLACE), $content);
        $content = self::applyPrepare($content);
        return trim($content);
    }

    /** Formata um conteúdo HTML mantendo quebras de linha */
    protected static function format(string $content): string
    {
        preg_match('/<html[^>]*>(.*?)<\/html>/s', $content, $html);
        $content = count($html) ? self::formatPage($content) : self::formatFragment($content);

        $preserved = [];

        $content = preg_replace_callback(
            '#<(script|style)(.*?)>(.*?)</\1>#is',
            function ($matches) use (&$preserved) {
                $key = '@@MINIFY_BLOCK_' . count($preserved) . '@@';
                $type = strtolower($matches[1]);
                $raw = $matches[3];

                $preserved[$key] = "<{$type}{$matches[2]}>{$raw}</{$type}>";
                return $key;
            },
            $content
        );

        $content = preg_replace('/<!--(?!\[if).*?-->/s', '', $content);

        $content = preg_replace('/(>)(?=<)/', "$1\n", $content);
        $content = preg_replace('/</', "\n<", $content);
        $content = preg_replace('/>\s*/', ">\n", $content);
        $content = preg_replace("/\n{2,}/", "\n", $content);
        $content = preg_replace('/^\s+/m', '', $content);

        return trim(strtr($content, $preserved));
    }

    protected static function formatFragment(string $content): string
    {
        $scriptExternal = '';
        $script = '';
        $style = '';

        preg_match_all('/<script[^>]*src=["\']?([^"\']+)["\']?[^>]*><\/script>/is', $content, $matches);
        foreach ($matches[0] as $tag) {
            $scriptExternal .= "\n" . trim($tag);
            $content = str_replace($tag, '', $content);
        }

        preg_match_all('/<script(?![^>]*src)[^>]*>(.*?)<\/script>/is', $content, $matches);
        foreach ($matches[1] as $i => $inner) {
            $script .= "\n" . trim($inner);
            $content = str_replace($matches[0][$i], '', $content);
        }

        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $content, $matches);
        foreach ($matches[1] as $i => $inner) {
            $style .= "\n" . trim($inner);
            $content = str_replace($matches[0][$i], '', $content);
        }

        $style = RenderCss::format($style);
        if (!empty($style)) $style = "<style>\n$style\n</style>";

        $script = RenderJs::format($script);
        if (!empty($script)) $script = "<script>\n$script\n</script>";

        return "$scriptExternal\n$style\n$content\n$script";
    }

    protected static function formatPage(string $content): string
    {
        $scriptExternal = '';
        $script = '';
        $style = '';
        $before = '';
        $after  = '';
        $doctype = '';
        $html = '';
        $htmlAttr = '';
        $head = '';
        $headAttr = '';
        $body = '';
        $bodyAttr = '';

        preg_match_all('/<script[^>]*src=["\']?([^"\']+)["\']?[^>]*><\/script>/is', $content, $matches);
        foreach ($matches[0] as $tag) {
            $scriptExternal .= "\n" . trim($tag);
            $content = str_replace($tag, '', $content);
        }

        preg_match_all('/<script(?![^>]*src)[^>]*>(.*?)<\/script>/is', $content, $matches);
        foreach ($matches[1] as $i => $inner) {
            $script .= "\n" . trim($inner);
            $content = str_replace($matches[0][$i], '', $content);
        }

        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $content, $matches);
        foreach ($matches[1] as $i => $inner) {
            $style .= "\n" . trim($inner);
            $content = str_replace($matches[0][$i], '', $content);
        }

        $style = RenderCss::format($style);
        if (!empty($style)) $style = "<style>\n$style\n</style>";

        $script = RenderJs::format($script);
        if (!empty($script)) $script = "<script>\n$script\n</script>";

        preg_match('/(.*?)(<html\b.*?>.*?<\/html>)(.*)/is', $content, $structure);
        $before = $structure[1];
        $content   = $structure[2];
        $after  = $structure[3];

        if (preg_match('/<!DOCTYPE[^>]*>/i', $before, $match)) {
            $doctype = $match[0];
            $before = str_replace($doctype, '', $before);
        }

        if (preg_match('/<head([^>]*)>(.*?)<\/head>/is', $content, $match)) {
            $headAttr = trim($match[1]);
            $head = trim($match[2]);
            $content = str_replace($match[0], '', $content);
        }

        if (preg_match('/<body([^>]*)>(.*?)<\/body>/is', $content, $match)) {
            $bodyAttr = trim($match[1]);
            $body = trim($match[2]);
            $content = str_replace($match[0], '', $content);
        }

        if (preg_match('/<html([^>]*)>(.*?)<\/html>/is', $content, $match)) {
            $htmlAttr = trim($match[1]);
            $html = trim($match[2]);
        }

        $bodyAttr = $bodyAttr ? " $bodyAttr" : '';
        $headAttr = $headAttr ? " $headAttr" : '';
        $htmlAttr = $htmlAttr ? " $htmlAttr" : '';

        $body = "<body$bodyAttr>\n$before\n$body\n$after\n</body>";
        $head = "<head$headAttr>\n$head\n$scriptExternal\n$style\n$script</head>";
        $html = "<html$htmlAttr>\n$head\n$body</html>";
        $content = "$doctype\n$html";

        $content = trim($content);

        return $content;
    }
}
