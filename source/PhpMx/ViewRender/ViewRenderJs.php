<?php

namespace PhpMx\ViewRender;

use PhpMx\Code;
use PhpMx\View;

abstract class ViewRenderJs extends View
{
    protected static array $IMPORTED_HASH = [];

    protected static array $PREPARE_REPLACE = [
        '// [#' => '[#'
    ];

    /** Aplica ações extras ao renderizar uma view */
    protected static function renderizeAction(string $content): string
    {
        $content = str_replace(array_keys(self::$PREPARE_REPLACE), array_values(self::$PREPARE_REPLACE), $content);

        $hash = Code::on([$content, self::__currentGet('data'), self::$SCOPE]);

        if (!IS_TERMINAL && isset(self::$IMPORTED_HASH[$hash]))
            return '';

        self::$IMPORTED_HASH[$hash] = true;

        $content = self::applyPrepare($content);
        $content = trim($content);

        if (count(self::__currentGet('imports')) > 1 || (count(self::$CURRENT) > 1 && !self::parentType('js')))
            $content = "<script>\n$content\n</script>";

        return $content;
    }

    /** Formata um conteúdo JS mantendo quebras de linha */
    protected static function format(string $content): string
    {
        $preserved = [];

        $content = preg_replace_callback('/`(?:\\\\`|[^`])*`/s', function ($m) use (&$preserved) {
            $key = '@@JS_TEMPLATE_' . count($preserved) . '@@';
            $preserved[$key] = $m[0];
            return $key;
        }, $content);

        $content = preg_replace_callback("/'(?:\\\\'|[^'])*'/s", function ($m) use (&$preserved) {
            $key = '@@JS_SINGLE_' . count($preserved) . '@@';
            $preserved[$key] = $m[0];
            return $key;
        }, $content);

        $content = preg_replace_callback('/"(?:\\\\"|[^"])*"/s', function ($m) use (&$preserved) {
            $key = '@@JS_DOUBLE_' . count($preserved) . '@@';
            $preserved[$key] = $m[0];
            return $key;
        }, $content);

        $content = preg_replace('!/\*.*?\*/!s', '', $content);
        $content = preg_replace('/\s*\/\/[^\n\r]*/', '', $content);

        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/^\s+/m', '', $content);

        $content = strtr($content, $preserved);
        $content = strtr($content, $preserved);
        $content = strtr($content, $preserved);
        $content = trim($content);

        return $content;
    }
}
