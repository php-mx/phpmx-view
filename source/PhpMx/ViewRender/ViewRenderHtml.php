<?php

namespace PhpMx\ViewRender;

abstract class ViewRenderHtml extends ViewRender
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

                $formatted = $type === 'script'
                    ? ViewRenderJs::format($raw)
                    : ViewRenderCss::format($raw);

                $preserved[$key] = "<{$type}{$matches[2]}>{$formatted}</{$type}>";
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
        $srcScripts = [];
        $inlineScripts = [];
        $styles = [];

        preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $attrs = $match[1];
            $body  = trim($match[2]);

            if (stripos($attrs, 'src=') !== false) {
                $srcScripts[] = "<script{$attrs}></script>";
            } elseif ($body !== '') {
                $inlineScripts[] = $body;
            }

            $content = str_replace($match[0], '', $content);
        }

        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $styles[] = trim($match[1]);
            $content = str_replace($match[0], '', $content);
        }

        $srcBlock = implode("\n", $srcScripts);
        $styleBlock = empty($styles) ? '' : "<style>\n" . implode("\n", $styles) . "\n</style>";
        $scriptBlock = empty($inlineScripts) ? '' : "<script>\n" . implode("\n", $inlineScripts) . "\n</script>";

        return implode("\n", array_filter([$srcBlock, $styleBlock, trim($content), $scriptBlock]));
    }

    protected static function formatPage(string $content): string
    {
        $doctype = '';
        $before = '';
        $html = '';
        $after = '';

        if (preg_match('/(.*?)(<html\b.*?>.*?<\/html>)(.*)/is', $content, $matches)) {
            $before = trim($matches[1]);
            $before = preg_replace_callback('/<!DOCTYPE[^>]*?>/i', function ($m) use (&$doctype) {
                $doctype = $doctype ?: $m[0];
                return '';
            }, $before);
            $html = $matches[2];
            $after = trim($matches[3]);
        } else {
            $html = $content;
        }

        $extractTags = function (string &$source, string $tag, ?callable $filter = null): array {
            $results = [];
            preg_match_all("/<{$tag}[^>]*>.*?<\/{$tag}>/is", $source, $matches);
            foreach ($matches[0] as $block) {
                if ($filter === null || $filter($block)) {
                    $results[] = $block;
                    $source = str_replace($block, '', $source);
                }
            }
            return $results;
        };

        $extraHead = [];
        $extraBodyStart = [];
        $extraBodyEnd = [];

        $extraHead = array_merge(
            $extraHead,
            $extractTags($before, 'script', fn($tag) => stripos($tag, 'src=') !== false || trim(strip_tags($tag)) === ''),
            $extractTags($before, 'style')
        );
        if (trim($before) !== '') {
            $extraBodyStart[] = trim($before);
        }

        $extraHead = array_merge(
            $extraHead,
            $extractTags($after, 'script', fn($tag) => stripos($tag, 'src=') !== false || trim(strip_tags($tag)) === ''),
            $extractTags($after, 'style')
        );
        if (trim($after) !== '') {
            $extraBodyEnd[] = trim($after);
        }

        preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $headMatch);
        $headInner = $headMatch[1] ?? '';
        $html = str_replace($headMatch[0], '[#head]', $html);

        $scriptsExternal = $extractTags($headInner, 'script', fn($tag) => stripos($tag, 'src=') !== false || trim(strip_tags($tag)) === '');
        $scriptsInline = $extractTags($headInner, 'script', fn($tag) => stripos($tag, 'src=') === false && trim(strip_tags($tag)) !== '');
        $styles = $extractTags($headInner, 'style');

        $head = [];
        if ($headInner) {
            $head[] = trim($headInner);
        }
        if (!empty($styles)) {
            $head[] = "<style>\n" . implode("\n", array_map('trim', array_map('strip_tags', $styles))) . "\n</style>";
        }
        if (!empty($scriptsExternal)) {
            $head[] = implode("\n", $scriptsExternal);
        }
        if (!empty($scriptsInline)) {
            $code = implode("\n", array_map(function ($block) {
                return trim(preg_replace('#</?script[^>]*>#is', '', $block));
            }, $scriptsInline));
            $head[] = "<script>\n$code\n</script>";
        }
        if (!empty($extraHead)) {
            $head[] = implode("\n", $extraHead);
        }

        $finalHead = "<head>\n" . implode("\n", array_filter($head)) . "\n</head>";
        $html = str_replace('[#head]', $finalHead, $html);

        $html = preg_replace_callback('/<body[^>]*>/', function ($m) use ($extraBodyStart) {
            if (empty($extraBodyStart)) return $m[0];
            return $m[0] . "\n" . implode("\n", $extraBodyStart);
        }, $html);

        $html = preg_replace_callback('/<\/body>/', function ($m) use ($extraBodyEnd) {
            if (empty($extraBodyEnd)) return $m[0];
            return implode("\n", $extraBodyEnd) . "\n" . $m[0];
        }, $html);

        return ($doctype ? "$doctype\n" : '') . $html;
    }
}
