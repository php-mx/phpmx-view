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
                self::echo(' - [#namespace] ([#imports])[#status]', $file);
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

    protected function getFilesIn($viewPath, $originName)
    {
        $scheme = [];

        foreach (Dir::seekForFile($viewPath, true) as $viewFile) {
            $path = Dir::getOnly($viewFile);
            $file = File::getOnly($viewFile);
            $fileEx = File::getEx($viewFile);
            $fileName = File::getName($file);

            $namespace = path($path, $fileName);

            $this->used[$namespace] = $this->used[$namespace] ?? $originName;

            $scheme[$namespace] = $scheme[$namespace] ?? [
                'namespace' => $namespace,
                'imports' => ['php' => null, 'html' => null],
                'direct' => true,
                'status' => $this->used[$namespace] == $originName ? '' : ' [replaced in ' . $this->used[$namespace] . ']'
            ];

            if (!$scheme[$namespace]['direct']) {
                $scheme[$namespace]['direct'] = true;
                $scheme[$namespace]['imports'] = ['php' => null, 'html' => null];
            }

            $scheme[$namespace]['imports'][$fileEx] = true;

            $pathName = explode('/', $path);
            $pathName = array_pop($pathName);

            if ($pathName == $fileName) {

                $namespace = path($path);

                $this->used[$namespace] = $this->used[$namespace] ?? $originName;

                $scheme[$namespace] = $scheme[$namespace] ?? [
                    'namespace' => $namespace,
                    'imports' => ['php' => null, 'html' => null],
                    'direct' => false,
                    'status' => $this->used[$namespace] == $originName ? '' : ' [replaced in ' . $this->used[$namespace] . ']'
                ];


                if (!$scheme[$namespace]['direct'])
                    $scheme[$namespace]['imports'][$fileEx] = true;
            }
        }

        foreach ($scheme as &$item) {
            $item['imports'] = array_filter($item['imports']);
            $item['imports'] = array_keys($item['imports']);
            unset($item['direct']);
        }

        foreach ($scheme as &$file)
            $file['imports'] = implode(', ', $file['imports']);

        ksort($scheme);

        return $scheme;
    }
};
