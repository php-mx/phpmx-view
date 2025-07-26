<?php

use PhpMx\File;
use PhpMx\Import;
use PhpMx\Path;
use PhpMx\Terminal;

return new class extends Terminal {

    function __invoke($view, $types = null)
    {
        $view = explode('/', $view);
        $view = array_map(fn($v) => strToCamelCase($v), $view);
        $view = path('system/view', ...$view);

        if ($types) {
            $types = explode(',', $types);
            $types = array_map(fn($type) => trim(strtolower($type)), $types);
            $types = array_values($types);
            foreach ($types as $type)
                $this->createViewFile("$view.$type", "library/template/terminal/view/$type.txt");
        } else {
            $this->createViewFile("$view.html", "library/template/terminal/view/full.txt");
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
