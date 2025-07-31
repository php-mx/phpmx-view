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

        if ($ex && !isset(View::$RENDER_CLASS[$ex]))
            throw new Exception("View type [$ex] not supported");

        $view = explode('/', $view);
        $view = array_map(fn($v) => str_replace(' ', '_', remove_accents(trim($v))), $view);
        $view = path('system/view', ...$view);

        if ($ex) {
            $this->createViewFile("$view.$ex", "library/template/terminal/view/$ex.txt");
        } else {
            $this->createViewFile("$view.html", "library/template/terminal/view/html.txt");
            $this->createViewFile("$view.css", "library/template/terminal/view/css.txt");
            $this->createViewFile("$view.js", "library/template/terminal/view/js.txt");
        }
    }

    protected function createViewFile($file, $template)
    {
        if (File::check($file))
            return self::echo("[ignored] file [$file] already exists");

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
