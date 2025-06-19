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
    protected static function renderizeAction(string $content, array $params = []): string
    {
        $content = str_replace(array_keys(self::$PREPARE_REPLACE), array_values(self::$PREPARE_REPLACE), $content);

        $hash = Code::on($content, self::__currentGet('data'));
        if (!IS_TERMINAL && isset(self::$IMPORTED_HASH[$hash])) return '';
        self::$IMPORTED_HASH[$hash] = true;

        $content = self::applyPrepare($content);

        $content = minifyCss($content);

        if (count(self::__currentGet('imports')) > 1 || (count(self::$CURRENT) > 1 && !self::parentType('css')))
            $content = "<style>\n$content\n</style>";

        return $content;
    }
}
