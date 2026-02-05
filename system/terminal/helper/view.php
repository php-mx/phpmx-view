<?php

use PhpMx\Dir;
use PhpMx\File;
use PhpMx\Trait\TerminalHelperTrait;

/** Lista e detalha todas as views disponíveis no projeto */
return new class {

    use TerminalHelperTrait;

    function __invoke($filter = null)
    {
        $this->handle(
            'system/view',
            $filter,
            ' - [#c:p,#ref] [#c:dd,#description]'
        );
    }

    protected function scan($viewPath)
    {
        $scheme = [];

        foreach (Dir::seekForFile($viewPath, true) as $viewFile) {
            $path = Dir::getOnly($viewFile);
            $file = File::getOnly($viewFile);
            $fileEx = File::getEx($viewFile);
            $fileName = File::getName($file);

            $namespace = path($path, $fileName);

            $scheme[$namespace] = $scheme[$namespace] ?? [
                'ref' => $namespace,
                'imports' => ['php' => null, 'html' => null],
                'direct' => true,
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

                $scheme[$namespace] = $scheme[$namespace] ?? [
                    'ref' => $namespace,
                    'imports' => ['php' => null, 'html' => null],
                    'direct' => false,
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

        foreach ($scheme as &$file) {
            $file['description'] = "[" . implode(', ', $file['imports']) . "]";
        }

        return $scheme;
    }
};
