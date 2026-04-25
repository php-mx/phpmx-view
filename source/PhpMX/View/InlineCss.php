<?php

namespace PhpMx\View;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** @ignore */
abstract class InlineCss
{

    static function apply(string $html): string
    {
        $css = '';

        $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function ($m) use (&$css) {
            $css .= "\n" . $m[1];
            return '';
        }, $html);

        if (empty(trim($css)))
            return $html;

        $rules = self::parseRules($css);

        if (empty($rules))
            return $html;

        $isPage = (bool) preg_match('/<html[\s>]/i', $html);

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        if ($isPage) {
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD);
        } else {
            $doc->loadHTML('<?xml encoding="UTF-8"><div id="__inlinecss_root__">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        }

        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        foreach ($rules as [$selector, $declarations]) {
            if (str_starts_with(trim($selector), '@')) continue;
            if (str_contains($selector, ':')) continue;

            try {
                $nodes = $xpath->query(self::toXPath($selector));
                if (!$nodes) continue;
                foreach ($nodes as $node) {
                    if ($node instanceof DOMElement)
                        self::mergeStyle($node, $declarations);
                }
            } catch (\Exception) {
                continue;
            }
        }

        if ($isPage) {
            $html = $doc->saveHTML();
            $html = preg_replace('/^<!DOCTYPE[^>]*>\n?/i', '', $html);
            $html = preg_replace('/<\?xml[^>]*>\n?/', '', $html);
        } else {
            $root = $xpath->query('//*[@id="__inlinecss_root__"]')->item(0);
            $html = '';
            foreach ($root->childNodes as $child)
                $html .= $doc->saveHTML($child);
        }

        return trim($html);
    }

    // ── CSS parsing ───────────────────────────────────────────────────────────

    protected static function parseRules(string $css): array
    {
        $rules = [];

        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        preg_match_all('/([^{]+)\{([^}]*)\}/s', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $declarations = self::parseDeclarations($match[2]);
            if (empty($declarations)) continue;

            foreach (array_map('trim', explode(',', $match[1])) as $selector) {
                if ($selector) $rules[] = [trim($selector), $declarations];
            }
        }

        return $rules;
    }

    protected static function parseDeclarations(string $block): array
    {
        $declarations = [];

        foreach (explode(';', $block) as $part) {
            $part = trim($part);
            $pos  = strpos($part, ':');
            if (!$part || $pos === false) continue;

            $prop = trim(substr($part, 0, $pos));
            $val  = trim(substr($part, $pos + 1));

            if ($prop && $val)
                $declarations[$prop] = $val;
        }

        return $declarations;
    }

    // ── Inline style merging ──────────────────────────────────────────────────

    protected static function mergeStyle(DOMElement $node, array $declarations): void
    {
        $existing = [];

        foreach (explode(';', $node->getAttribute('style')) as $decl) {
            $decl = trim($decl);
            $pos  = strpos($decl, ':');
            if (!$decl || $pos === false) continue;
            $existing[trim(substr($decl, 0, $pos))] = trim(substr($decl, $pos + 1));
        }

        // Inline existente tem prioridade sobre o <style>
        $merged = array_merge($declarations, $existing);

        $style = implode('; ', array_map(fn($p, $v) => "$p: $v", array_keys($merged), $merged));
        $node->setAttribute('style', $style);
    }

    // ── Selector → XPath ─────────────────────────────────────────────────────

    protected static function toXPath(string $selector): string
    {
        $selector = trim($selector);
        $parts    = preg_split('/\s*([>+~])\s*|\s+/', $selector, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $xpath      = '';
        $combinator = '//';

        foreach ($parts as $part) {
            if ($part === '>') {
                $combinator = '/';
                continue;
            }
            if ($part === '+' || $part === '~') {
                $combinator = '//';
                continue;
            }

            $xpath     .= $combinator . self::simpleToXPath($part);
            $combinator = '//';
        }

        return $xpath ?: '//*';
    }

    protected static function simpleToXPath(string $sel): string
    {
        $tag        = '*';
        $conditions = [];

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*)/', $sel, $m)) {
            $tag = $m[1];
            $sel = substr($sel, strlen($m[0]));
        }

        // #id
        while (preg_match('/^#([\w-]+)/', $sel, $m)) {
            $conditions[] = "@id='{$m[1]}'";
            $sel = substr($sel, strlen($m[0]));
        }

        // .class
        while (preg_match('/^\.([\w-]+)/', $sel, $m)) {
            $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$m[1]} ')";
            $sel = substr($sel, strlen($m[0]));
        }

        // [attr], [attr=val], [attr~=val], [attr^=val], [attr$=val], [attr*=val]
        while (preg_match('/^\[([\w-]+)(?:([~|^$*]?=)["\']?([^"\'>\]]*)["\']?)?\]/', $sel, $m)) {
            [$full, $attr, $op, $val] = array_pad($m, 4, '');
            $conditions[] = match ($op) {
                ''   => "@$attr",
                '='  => "@$attr='$val'",
                '~=' => "contains(concat(' ', normalize-space(@$attr), ' '), ' $val ')",
                '^=' => "starts-with(@$attr, '$val')",
                '$=' => "substring(@$attr, string-length(@$attr) - string-length('$val') + 1) = '$val'",
                '*=' => "contains(@$attr, '$val')",
                default => "@$attr",
            };
            $sel = substr($sel, strlen($full));
        }

        return $tag . (empty($conditions) ? '' : '[' . implode(' and ', $conditions) . ']');
    }
}
