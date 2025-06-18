<?php

namespace PhpMx;

use PhpMx\ViewRender\ViewRender;
use PhpMx\ViewRender\ViewRenderCss;
use PhpMx\ViewRender\ViewRenderHtml;
use PhpMx\ViewRender\ViewRenderJs;

abstract class View
{
    protected static ?array $SCHEME = null;

    protected static array $CURRENT = [];

    protected static array $PREPARE = [];

    static array $RENDER_EX_CLASS = [
        'php' => ViewRenderHtml::class,
        'html' => ViewRenderHtml::class,
        'css' => ViewRenderCss::class,
        'js' => ViewRenderJs::class,
    ];

    /** Define uma tag de prepare disponivel em todas as views */
    static function globalPrepare($tag, $action): void
    {
        self::$PREPARE[$tag] = $action;
    }

    /** Renderiza uma view e retorna seu conteúdo em forma de string */
    static function render(string $ref, string|array $data = [], ...$params): string
    {
        $content = '';

        $parentFile = self::__currentGet('importing_file');

        if ($parentFile) {
            if (substr($ref, 0, 4) == '.../') {
                $path = Dir::getOnly($parentFile);
                $path = explode("/", $path);
                array_pop($path);
                array_pop($path);
                $path = path(...$path);
                $ref = path($path, substr($ref, 4));
            }

            if (substr($ref, 0, 3) == '../') {
                $path = Dir::getOnly($parentFile);
                $path = explode("/", $path);
                array_pop($path);
                $path = path(...$path);
                $ref = path($path, substr($ref, 3));
            }

            if (substr($ref, 0, 2) == './') {
                $path = Dir::getOnly($parentFile);
                $ref = path($path, substr($ref, 2));
            }
        }

        $ref = trim($ref, '/');

        self::$SCHEME = self::$SCHEME ?? self::scheme();

        $scheme = self::$SCHEME[$ref] ?? false;

        if ($scheme && self::__currentOpen($scheme, $data)) {

            if (isset($params['scope'])) self::__currentSet('scope', $params['scope']);

            foreach (self::__currentGet('imports') as $file) {
                $parentFile = self::__currentGet('importing_file');
                self::__currentSet('importing_file', $file);

                if (File::getEx($file) == 'php') {
                    list($contentFile, $data) = (
                        function ($__FILEPATH__, $__DATA) {
                            foreach (array_keys($__DATA) as $__KEY__)
                                if (!is_numeric($__KEY__))
                                    $$__KEY__ = $__DATA[$__KEY__];

                            ob_start();
                            $__RETURN__ = require $__FILEPATH__;
                            $__OUTPUT__ = ob_get_clean();

                            if (is_stringable($__RETURN__) && !is_numeric($__RETURN__))
                                $__OUTPUT__ = $__RETURN__;

                            return [$__OUTPUT__, $__DATA];
                        }
                    )($file, self::__currentGet('data'));
                    self::__currentSet('data', $data);
                } else {
                    $contentFile = Import::content($file);
                }

                $contentFile = self::renderize($contentFile, $params);

                self::__currentSet('importing_file', $parentFile);

                $content .= $contentFile;
            }

            self::__currentClose();
        }

        return $content;
    }

    /** Renderiza uma sting aplicando os prepares globais */
    static function renderString(string $viewContent, string|array $data = []): string
    {
        $data = is_array($data) ? $data : ['CONTENT' => $data];
        return prepare($viewContent, [...$data, ...self::$PREPARE]);
    }

    /** Aplica as regras de renderização da view atual */
    protected static function renderize(string $content, array $params = []): ?string
    {
        $__scope = self::__currentGet('scope');
        $__onescope = self::__currentGet('onescope');

        $content = str_replace('__scope', "_$__scope", $content);
        $content = str_replace('__onescope', "_$__onescope", $content);

        $renderizeClass = self::$RENDER_EX_CLASS[File::getEx(self::__currentGet('importing_file'))];

        if (
            !$renderizeClass
            || !class_exists($renderizeClass)
            || ($renderizeClass != ViewRender::class && !is_extend($renderizeClass, ViewRender::class))
        )
            return null;

        return $renderizeClass::renderizeAction($content, $params);
    }

    /** Inicializa uma view */
    protected static function __currentOpen(array $scheme, array $data = [])
    {
        if (isset(self::$CURRENT[$scheme['scope']])) return false;

        $current = [];
        $current['key'] = $scheme['scope'];
        $current['scope'] = $scheme['scope'];
        $current['onescope'] = md5(uniqid());
        $current['mode'] = count($scheme['imports']) > 1 ? 'path' : 'file';
        $current['imports'] = $scheme['imports'];
        $current['data'] = [...self::__currentGet('data') ?? [], ...$data];

        self::$CURRENT[$scheme['scope']] = $current;

        return true;
    }

    /** Finaliza a view atual */
    protected static function __currentClose()
    {
        $key = self::__currentGet('key');
        if ($key) unset(self::$CURRENT[$key]);
    }

    /** Retorna uma variavel da view atual */
    protected static function __currentGet($var)
    {
        if (count(self::$CURRENT))
            return end(self::$CURRENT)[$var] ?? null;

        return null;
    }

    /** Define uma variavel da view atual */
    protected static function __currentSet($var, $value)
    {
        $key = self::__currentGet('key');
        if ($key) self::$CURRENT[$key][$var] = $value;
    }

    /** Verifica se a view pai é de um dos tipos fornecidos  */
    protected static function parentType(...$types): bool
    {
        $parentKey = array_keys(self::$CURRENT);
        $parentKey = $parentKey[count($parentKey) - 2];
        $parentType = File::getEx(self::$CURRENT[$parentKey]['importing_file'] ?? '');
        return in_array($parentType, $types);
    }

    /** Aplica os prepare de view em uma string */
    protected static function applyPrepare($string)
    {
        $string = prepare($string, [...self::__currentGet('data'), ...self::$PREPARE]);
        return $string;
    }

    /** Carrega o esquema de views */
    protected static function scheme(): array
    {
        return cache('view-scheme', function () {
            $scheme = [];

            $viewPaths = Path::seekDirs('view');
            $viewPaths = array_reverse($viewPaths);

            foreach ($viewPaths as $viewPath) {
                $viewFiles = Dir::seekForFile($viewPath, true);

                foreach ($viewFiles as $viewFile) {
                    if (isset(self::$RENDER_EX_CLASS[File::getEx($viewFile)])) {
                        $path = Dir::getOnly($viewFile);
                        $file = File::getOnly($viewFile);
                        $name = File::getName($file);
                        $import = path($viewPath, $viewFile);

                        $scheme[$viewFile] = [
                            'scope' => md5($viewFile),
                            'origin' => $viewPath,
                            'imports' => [$name => $import]
                        ];

                        if (str_starts_with($name, '_')) {
                            $scheme[$path] = $scheme[$path] ?? [
                                'scope' => md5($path),
                                'origin' => $viewPath,
                                'imports' => []
                            ];
                            $scheme[$path]['imports'][$name] = $import;
                            $scheme[$path]['imports'] = array_filter(
                                $scheme[$path]['imports'],
                                fn($v, $k) => substr($k, 0, 1) === '_',
                                ARRAY_FILTER_USE_BOTH
                            );
                        } else if (File::getEx($file) == 'php') {
                            $alias = path($path, $name);
                            if (!isset($scheme[$alias]) || $scheme[$alias]['origin'] != $viewPath)
                                $scheme[$alias] =  [
                                    'scope' => md5($viewFile),
                                    'origin' => $viewPath,
                                    'imports' => [$name => $import]
                                ];
                        }
                    }
                }
            }

            return $scheme;
        });
    }
}
