<?php

use PhpMx\Dir;
use PhpMx\File;
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
                self::echo(' - [#alias] ([#types])[#status]', $file);
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

            $type = File::getEx($file);
            $alias = substr($ref, 0, (strlen($type) + 1) * -1);

            $this->used[$alias] = $this->used[$alias] ?? $origin;

            $files[$alias] = $files[$alias] ?? [
                'alias' => $alias,
                'types' => [],
                'status' => $this->used[$alias] == $origin ? '' : ' [replaced in ' . $this->used[$alias] . ']'
            ];
            $files[$alias]['types'][] = $type;
        }

        foreach ($files as &$file)
            $file['types'] = implode(', ', $file['types']);

        ksort($files);
        return $files;
    }
};
