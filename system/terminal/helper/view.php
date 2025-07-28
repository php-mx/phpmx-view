<?php

use PhpMx\Dir;
use PhpMx\Path;
use PhpMx\Terminal;

return new class extends Terminal {

    protected $used = [];

    function __invoke()
    {
        foreach (Path::seekForDirs('system/view') as $path) {
            $origin = $this->getOrigim($path);

            self::echo();
            self::echo('[[#]]', $origin);
            self::echoLine();

            foreach ($this->getFilesIn($path, $origin) as $file) {
                self::echo(' - [#ref] ([#file])[#status]', $file);
            };

            self::echo();
        }
    }

    protected function getOrigim($path)
    {
        if ($path === 'system/view') return 'CURRENT-PROJECT';

        if (str_starts_with($path, 'vendor/')) {
            $parts = explode('/', $path);
            return $parts[1] . '-' . $parts[2];
        }

        return 'unknown';
    }

    protected function getFilesIn($path, $origin)
    {
        $files = [];
        foreach (Dir::seekForFile($path, true) as $ref) {
            $file = path($path, $ref);
            $this->used[$ref] = $this->used[$ref] ?? $origin;

            $files[$ref] = [
                'ref' => $ref,
                'file' => $file,
                'status' => $this->used[$ref] == $origin ? '' : ' [replaced in ' . $this->used[$ref] . ']'
            ];
        }
        ksort($files);
        return $files;
    }
};
