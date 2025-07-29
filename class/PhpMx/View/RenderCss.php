<?php

namespace PhpMx\View;

use PhpMx\View;

/** Classe responsável por renderizar e formatar arquivos CSS em views. */
abstract class RenderCss extends View
{
    protected static array $IMPORTED_HASH = [];

    protected static array $PREPARE_REPLACE = [
        '/* [#' => '[#',
        '] */' => ']',
        '// [#' => '[#'
    ];

    /** Aplica ações extras ao renderizar uma view */
    protected static function renderizeAction(string $content): string
    {
        $content = str_replace(array_keys(self::$PREPARE_REPLACE), array_values(self::$PREPARE_REPLACE), $content);

        $hash = mx5([$content, self::__currentGet('data'), self::$SCOPE]);

        if (!IS_TERMINAL && isset(self::$IMPORTED_HASH[$hash]))
            return '';

        self::$IMPORTED_HASH[$hash] = true;

        $content = self::applyPrepare($content);

        if (count(self::__currentGet('imports')) > 1 || (count(self::$CURRENT) > 1 && !self::parentType('css')))
            $content = "<style>\n$content\n</style>";

        return $content;
    }

    /** Formata um conteúdo CSS mantendo quebras de linha */
    protected static function format(string $content): string
    {
        $content = self::applyMediaStyle($content);

        $content = preg_replace('!/\*.*?\*/!s', '', $content);

        $content = self::unnesting($content);

        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/^\s+/m', '', $content);
        $content = trim($content);

        return $content;
    }

    protected static function unnesting(string $currentContent, string $currentSelector = ''): string
    {
        $content = [];

        foreach (self::explodeScope($currentContent) as $scope) {
            list($scopeSelector, $scopeContent) = $scope;
            if (empty($scopeSelector)) {
                if (empty($currentSelector)) {
                    $content[] = "$scopeContent\n";
                } else {
                    $content[] = "$currentSelector {\n$scopeContent\n}\n";
                }
            } elseif (str_starts_with($scopeSelector, '@keyframes') || str_starts_with($scopeSelector, '@font-face')) {
                $content[] = "$scopeSelector {\n$scopeContent\n}\n";
            } elseif (str_starts_with($scopeSelector, '@')) {
                $scopeContent = self::unnesting($scopeContent, $currentSelector);
                $content[] = "$scopeSelector {\n$scopeContent\n}\n";
            } else {
                $scopeSelector = self::mergeSelectors($currentSelector, $scopeSelector);
                $content[] = self::unnesting($scopeContent, $scopeSelector);
            }
        }

        $content = implode("\n", $content);
        return $content;
    }

    protected static function mergeSelectors(string $scopeSelector, string $childSelector): string
    {
        $parents = array_map('trim', explode(',', $scopeSelector));
        $children = array_map('trim', explode(',', $childSelector));
        $result = [];
        foreach ($parents as $parent) {
            foreach ($children as $child) {
                if (str_starts_with($child, '&')) {
                    $result[] = str_replace('&', $parent, $child);
                } else {
                    $result[] = trim($parent . ' ' . $child);
                }
            }
        }
        return implode(', ', $result);
    }

    protected static function explodeScope(string $content): array
    {
        $blocks = [];

        if (strpos($content, '{') === false && strpos($content, ';') !== false) {
            $blocks[] = ['', trim($content)];
            return $blocks;
        }

        $firstBracePos = strpos($content, '{');

        if ($firstBracePos !== false) {
            $before = substr($content, 0, $firstBracePos);
            $after = substr($content, $firstBracePos);

            $lastSemicolonPos = strrpos($before, ';');

            if ($lastSemicolonPos !== false) {
                $inlineDeclarations = trim(substr($before, 0, $lastSemicolonPos + 1));
                if ($inlineDeclarations !== '') {
                    $blocks[] = ['', $inlineDeclarations];
                }
                $content = trim(substr($before, $lastSemicolonPos + 1) . $after);
            }
        }

        $length = strlen($content);
        $i = 0;

        while ($i < $length) {
            while ($i < $length && trim($content[$i]) === '') $i++;

            $start = $i;

            while ($i < $length && $content[$i] !== '{') $i++;

            if ($i >= $length) break;

            $selector = trim(substr($content, $start, $i - $start));

            $i++;

            $depth = 1;
            $startContent = $i;

            while ($i < $length && $depth > 0) {
                if ($content[$i] === '{') $depth++;
                if ($content[$i] === '}') $depth--;
                $i++;
            }

            $blockContent = trim(substr($content, $startContent, $i - $startContent - 1));

            if ($selector !== '') {
                $blocks[] = [$selector, $blockContent];
            }
        }

        return $blocks;
    }
}
