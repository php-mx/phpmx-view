<?php

use PhpMx\File;
use PhpMx\Import;
use PhpMx\Path;
use PhpMx\Terminal;
use PhpMx\View;

return new class extends Terminal {

    function __invoke($view)
    {
        $ex = str_contains($view, '.') ? File::getEx($view) : null;

        if ($ex)
            $view = substr($view, 0, -strlen($ex) - 1);

        $ex = $ex ?? 'html';

        if ($ex && !isset(View::$RENDER_CLASS[$ex]))
            throw new Exception("View type [$ex] not supported");

        $view = explode('/', $view);
        $view = array_map(fn($v) => str_replace(' ', '_', remove_accents(trim($v))), $view);
        $view = path('system/view', ...$view);

        $file = "$view.$ex";

        if (File::check($file))
            return self::echo("[ignored] file [$file] already exists");

        $template = "library/template/terminal/view/$ex.txt";

        $content = Path::seekForFile($template);

        if (!$content)
            return self::echo("[ignored] template [$template] not found");

        if ($content) {
            $content = Import::content($content);
            File::create($file, $content);
            self::echo("[created] file [$file] created");
        }
    }
};
