<?php

namespace PhpMx\View;

use PhpMx\View;

/** @ignore */
abstract class RenderMd extends View
{
    protected static array $PREPARE_REPLACE = [
        '<!-- [#' => '[#',
        '] -->' => ']',
    ];

    protected static function renderizeAction(string $content): string
    {
        $content = str_replace(array_keys(self::$PREPARE_REPLACE), array_values(self::$PREPARE_REPLACE), $content);

        if (self::parentType('md'))
            return $content;

        $content = self::applyPrepare($content);

        if (count(self::__currentGet('imports')) > 1 || count(self::$CURRENT) > 1)
            $content = self::toHtml($content);

        return $content;
    }

    protected static function format(string $content): string
    {
        return trim($content);
    }

    static function toHtml(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);

        $preserved = [];
        $idx = 0;

        $md = preg_replace_callback(
            '/^(`{3,}|~{3,})([^\n]*)\n(.*?)^\1[ \t]*$/ms',
            function ($m) use (&$preserved, &$idx) {
                $key = "\x02" . $idx++ . "\x03";
                $lang = trim($m[2]);
                $code = htmlspecialchars($m[3], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $langAttr = $lang ? " class=\"language-$lang\"" : '';
                $preserved[$key] = "<pre><code$langAttr>$code</code></pre>";
                return "\n$key\n";
            },
            $md
        );

        $lines = explode("\n", $md);
        $html = '';
        $n = count($lines);
        $i = 0;

        while ($i < $n) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if (isset($preserved[$trimmed])) {
                $html .= $preserved[$trimmed] . "\n";
                $i++;
                continue;
            }

            if ($trimmed === '') {
                $i++;
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)(?:\s+#+\s*)?$/', $trimmed, $m)) {
                $level = strlen($m[1]);
                $html .= "<h$level>" . self::parseInline($m[2]) . "</h$level>\n";
                $i++;
                continue;
            }

            if (preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $trimmed)) {
                $html .= "<hr>\n";
                $i++;
                continue;
            }

            if (str_starts_with($trimmed, '>')) {
                $quoteLines = [];
                while ($i < $n && (str_starts_with(trim($lines[$i]), '>') || (trim($lines[$i]) !== '' && !str_starts_with(trim($lines[$i]), '>') && !empty($quoteLines)))) {
                    if (str_starts_with(trim($lines[$i]), '>')) {
                        $quoteLines[] = preg_replace('/^[ \t]*>[ \t]?/', '', $lines[$i]);
                    } else {
                        break;
                    }
                    $i++;
                }
                $inner = self::toHtml(implode("\n", $quoteLines));
                $html .= "<blockquote>\n$inner\n</blockquote>\n";
                continue;
            }

            if (preg_match('/^[ \t]*[-*+]\s/', $line)) {
                $items = [];
                $item = null;
                while ($i < $n) {
                    $cur = $lines[$i];
                    if (preg_match('/^[ \t]*[-*+]\s+(.*)/', $cur, $m)) {
                        if ($item !== null) $items[] = $item;
                        $item = $m[1];
                        $i++;
                    } elseif ($item !== null && trim($cur) !== '' && preg_match('/^[ \t]{2,}/', $cur)) {
                        $item .= "\n" . ltrim($cur);
                        $i++;
                    } else {
                        break;
                    }
                }
                if ($item !== null) $items[] = $item;
                $html .= "<ul>\n";
                foreach ($items as $it)
                    $html .= "<li>" . self::parseInline(trim($it)) . "</li>\n";
                $html .= "</ul>\n";
                continue;
            }

            if (preg_match('/^[ \t]*\d+\.\s/', $line)) {
                $items = [];
                $item = null;
                while ($i < $n) {
                    $cur = $lines[$i];
                    if (preg_match('/^[ \t]*\d+\.\s+(.*)/', $cur, $m)) {
                        if ($item !== null) $items[] = $item;
                        $item = $m[1];
                        $i++;
                    } elseif ($item !== null && trim($cur) !== '' && preg_match('/^[ \t]{2,}/', $cur)) {
                        $item .= "\n" . ltrim($cur);
                        $i++;
                    } else {
                        break;
                    }
                }
                if ($item !== null) $items[] = $item;
                $html .= "<ol>\n";
                foreach ($items as $it)
                    $html .= "<li>" . self::parseInline(trim($it)) . "</li>\n";
                $html .= "</ol>\n";
                continue;
            }

            $paraLines = [];
            while ($i < $n) {
                $cur = $lines[$i];
                $curTrimmed = trim($cur);

                if ($curTrimmed === '') break;
                if (isset($preserved[$curTrimmed])) break;
                if (preg_match('/^#{1,6}\s/', $curTrimmed)) break;
                if (preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $curTrimmed)) break;
                if (str_starts_with($curTrimmed, '>')) break;
                if (preg_match('/^[ \t]*[-*+]\s/', $cur)) break;
                if (preg_match('/^[ \t]*\d+\.\s/', $cur)) break;

                $paraLines[] = $cur;
                $i++;

                if ($i < $n && preg_match('/^=+\s*$/', trim($lines[$i]))) {
                    $html .= "<h1>" . self::parseInline(implode(' ', $paraLines)) . "</h1>\n";
                    $paraLines = [];
                    $i++;
                    break;
                }

                if ($i < $n && preg_match('/^-{2,}\s*$/', trim($lines[$i]))) {
                    $html .= "<h2>" . self::parseInline(implode(' ', $paraLines)) . "</h2>\n";
                    $paraLines = [];
                    $i++;
                    break;
                }
            }

            if (!empty($paraLines)) {
                $text = implode("\n", $paraLines);
                $html .= "<p>" . self::parseInline($text) . "</p>\n";
            }
        }

        return trim($html);
    }

    protected static function parseInline(string $text): string
    {
        $preserved = [];
        $idx = 0;

        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$preserved, &$idx) {
            $key = "\x02" . $idx++ . "\x03";
            $preserved[$key] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
            return $key;
        }, $text);

        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s"]+)(?:\s+"([^"]*)")?\)/', function ($m) use (&$preserved, &$idx) {
            $key = "\x02" . $idx++ . "\x03";
            $alt   = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $src   = $m[2];
            $title = !empty($m[3]) ? ' title="' . htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8') . '"' : '';
            $preserved[$key] = "<img src=\"$src\" alt=\"$alt\"$title>";
            return $key;
        }, $text);

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s"]+)(?:\s+"([^"]*)")?\)/', function ($m) use (&$preserved, &$idx) {
            $key   = "\x02" . $idx++ . "\x03";
            $label = $m[1];
            $href  = $m[2];
            $title = !empty($m[3]) ? ' title="' . htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8') . '"' : '';
            $preserved[$key] = "<a href=\"$href\"$title>$label</a>";
            return $key;
        }, $text);

        $text = preg_replace('/\*{3}(.+?)\*{3}/s', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/_{3}(.+?)_{3}/s',    '<strong><em>$1</em></strong>', $text);

        $text = preg_replace('/\*{2}(.+?)\*{2}/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/_{2}(.+?)_{2}/s',    '<strong>$1</strong>', $text);

        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
        $text = preg_replace('/(?<![a-zA-Z0-9_])_(.+?)_(?![a-zA-Z0-9_])/s', '<em>$1</em>', $text);

        $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text);

        $text = preg_replace('/  \n/', "<br>\n", $text);
        $text = preg_replace('/\\\\\n/', "<br>\n", $text);

        return strtr($text, $preserved);
    }
}
