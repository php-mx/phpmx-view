<?php

namespace PhpMx\ViewRender;

abstract class ViewRenderHtml extends ViewRender
{
    protected static array $PREPARE_REPLACE = [
        '<!-- [#' => '[#',
        '] -->' => ']',
    ];

    /** Aplica ações extras ao renderizar uma view */
    protected static function renderizeAction(string $content, array $params = []): string
    {
        $content = str_replace(array_keys(self::$PREPARE_REPLACE), array_values(self::$PREPARE_REPLACE), $content);
        return self::applyPrepare($content);
    }
}
