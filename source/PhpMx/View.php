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

    protected static array $MEDIA_STYLE = [];

    static array $RENDER_EX_CLASS = [
        'html' => ViewRenderHtml::class,
        'css' => ViewRenderCss::class,
        'js' => ViewRenderJs::class,
    ];

    /** Renderiza uma view e retorna seu conteúdo em forma de string */
    static function render(string $ref, string|array $data = [], ...$params): string
    {
        $content = '';

        $parentFile = self::__currentGet('importing_file');

        if ($parentFile) {
            $path = explode('view/', $parentFile);
            array_shift($path);
            $path = implode('view/', $path);
            $path = Dir::getOnly($path);
            if (str_starts_with($ref, '.../')) {
                $path = explode("/", $path);
                array_pop($path);
                array_pop($path);
                $path = path(...$path);
                $ref = path($path, substr($ref, 4));
            } elseif (str_starts_with($ref, '../')) {
                $path = explode("/", $path);
                array_pop($path);
                $path = path(...$path);
                $ref = path($path, substr($ref, 3));
            } elseif (str_starts_with($ref, './')) {
                $ref = path($path, substr($ref, 2));
            }
        }

        $ref = trim($ref, '/');

        self::$SCHEME = self::$SCHEME ?? self::scheme();

        $importOnly = null;

        foreach (array_keys(self::$RENDER_EX_CLASS) as $ex) {
            if (!$importOnly && str_ends_with($ref, $ex)) {
                $importOnly = $ex;
                $ref = substr($ref, 0, -strlen($ex) - 1);
            }
        }


        $scheme = self::$SCHEME[$ref] ?? false;

        if ($scheme && self::__currentOpen($scheme, $data)) {

            if (isset($params['scope'])) self::__currentSet('scope', $params['scope']);

            foreach (self::__currentGet('imports') as $file) {
                if (!$importOnly || $importOnly == File::getEx($file)) {
                    $parentFile = self::__currentGet('importing_file');
                    $parentType = self::__currentGet('importing_type');

                    self::__currentSet('importing_file', $file);
                    self::__currentSet('importing_type', File::getEx($file));

                    if (File::getEx($file) == 'php') {
                        list($contentFile, $data, $type) = (
                            function ($__FILEPATH__, $__DATA, $__TYPE) {
                                foreach (array_keys($__DATA) as $__KEY__)
                                    if (!is_numeric($__KEY__))
                                        $$__KEY__ = $__DATA[$__KEY__];

                                ob_start();
                                $__RETURN__ = require $__FILEPATH__;
                                $__OUTPUT__ = ob_get_clean();

                                if (is_stringable($__RETURN__) && !is_numeric($__RETURN__))
                                    $__OUTPUT__ = $__RETURN__;

                                return [$__OUTPUT__, $__DATA, $__TYPE];
                            }
                        )($file, self::__currentGet('data'), 'html');

                        self::__currentSet('importing_type', $type);
                        self::__currentSet('data', $data);
                    } else {
                        $contentFile = Import::content($file);
                    }

                    $contentFile = self::renderize($contentFile, $params);

                    self::__currentSet('importing_file', $parentFile);
                    self::__currentSet('importing_type', $parentType);

                    $content .= $contentFile;
                }
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

    /** Define media queries dinamicas para folhas de estilo */
    static function mediaStyle($media, $queries): void
    {
        self::$MEDIA_STYLE[$media] = $queries;
    }

    /** Define uma tag de prepare disponivel em todas as views */
    static function prepare($tag, $action): void
    {
        self::$PREPARE[$tag] = $action;
    }

    /** Aplica as regras de renderização da view atual */
    protected static function renderize(string $content, array $params = []): ?string
    {
        $__scope = self::__currentGet('scope');

        $content = str_replace('__scope', "_$__scope", $content);

        $renderizeClass = self::$RENDER_EX_CLASS[self::__currentGet('importing_type')];

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

        if (count($parentKey) < 2)
            return false;

        $parentKey = $parentKey[count($parentKey) - 2];
        $parentType = self::$CURRENT[$parentKey]['importing_type'] ?? '';

        return in_array($parentType, $types);
    }

    /** Aplica os prepare de view em uma string */
    protected static function applyPrepare($string)
    {
        $string = prepare($string, [...self::__currentGet('data'), ...self::$PREPARE]);
        return $string;
    }

    /** Aplica os prepare de view em uma string */
    protected static function applyMediaStyle($string)
    {
        foreach (self::$MEDIA_STYLE as $media => $value)
            $string = str_replace("@media $media", "@media $value", $string);

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
                        $ex = File::getEx($file);
                        $alias = path($path, $name);
                        $import = path($viewPath, $viewFile);

                        if (!isset($scheme[$alias]) || $scheme[$alias]['origin'] != $viewPath) {
                            $scheme[$alias]  = [
                                'scope' => md5($alias),
                                'origin' => $viewPath,
                                'imports' => []
                            ];
                        }

                        if ($ex == 'php') {
                            array_unshift($scheme[$alias]['imports'], $import);
                        } else {
                            $scheme[$alias]['imports'][] = $import;
                        }
                    }
                }

                foreach ($scheme as &$item)
                    unset($item['origin']);
            }

            return $scheme;
        });
    }
}
