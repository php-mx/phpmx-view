<?php

namespace PhpMx\ViewRender;

use PhpMx\Code;

abstract class ViewRenderCss extends ViewRender
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

        $hash = Code::on([$content, self::__currentGet('data'), self::$SCOPE]);

        if (!IS_TERMINAL && isset(self::$IMPORTED_HASH[$hash]))
            return '';

        self::$IMPORTED_HASH[$hash] = true;

        $content = self::applyPrepare($content);
        $content = trim($content);

        if (count(self::__currentGet('imports')) > 1 || (count(self::$CURRENT) > 1 && !self::parentType('css')))
            $content = "<style>\n$content\n</style>";

        return $content;
    }

    /** Formata um conteúdo CSS mantendo quebras de linha */
    protected static function format(string $content): string
    {
        $content = preg_replace('!/\*.*?\*/!s', '', $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/^\s+/m', '', $content);

        return $content;
    }
}
