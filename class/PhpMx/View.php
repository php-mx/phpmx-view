<?php

namespace PhpMx;

use PhpMx\Dir;
use PhpMx\File;
use PhpMx\Import;
use PhpMx\View\RenderCss;
use PhpMx\View\RenderHtml;
use PhpMx\View\RenderJs;

/** Classe responsável por renderizar views e aplicar lógica de apresentação. */
abstract class View
{
    protected static int $SCOPE = 0;

    protected static ?array $SCHEME = null;

    protected static array $CURRENT = [];

    protected static array $PREPARE = [];

    protected static array $MEDIA_STYLE = [];

    static array $RENDER_CLASS = [
        'php' => [RenderHtml::class, true],
        'html' => [RenderHtml::class, true],
        'css' => [RenderCss::class, true],
        'js' => [RenderJs::class, true],
    ];

    /** Renderiza uma view e retorna seu conteúdo em forma de string */
    static function render(string $ref, string|array $data = [], array $params = []): string
    {
        self::$SCHEME = self::$SCHEME ?? self::scheme();
        $ref = self::resolveViewRef($ref);
        $content = '';

        $importOnly = null;

        foreach (array_keys(self::$RENDER_CLASS) as $ex) {
            if (!$importOnly && str_ends_with($ref, ".$ex")) {
                $importOnly = $ex;
                $ref = substr($ref, 0, -strlen($ex) - 1);
            }
        }

        $scheme = self::$SCHEME[$ref] ?? false;

        if ($scheme && self::__currentOpen($ref, $scheme, $data, $importOnly)) {

            if (isset($params['scope'])) self::__currentSet('scope', $params['scope']);

            foreach (self::__currentGet('imports') as $file) {
                $parentFile = self::__currentGet('importing_file');

                self::__currentSet('importing_file', $file);
                self::__currentSet('type', self::__currentGet('type') ?? File::getEx($file));

                if (File::getEx($file) == 'php') {
                    list($contentFile, $data) = self::importViewFilePhp($file, self::__currentGet('data'));
                    self::__currentSet('data', $data);
                } else {
                    $contentFile = Import::content($file);
                }

                $contentFile = self::renderize($contentFile, $params);

                self::__currentSet('importing_file', $parentFile);

                $content .= $contentFile;
            }

            if (count(self::$CURRENT) == 1)
                $content = self::format($content) ?? '';

            self::__currentClose();
        }

        self::$SCOPE++;

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
    static function globalPrepare($tag, $action): void
    {
        self::$PREPARE[$tag] = $action;
    }

    /** Formata o conteúdo final da view */
    protected static function format(string $content): ?string
    {
        $type = File::getEx(self::__currentGet('type') ?? '');

        $class = self::$RENDER_CLASS[$type][0];

        if (!$class) return null;
        if (!class_exists($class)) return null;

        return $class::format($content);
    }

    /** Aplica as regras de renderização da view atual */
    protected static function renderize(string $content, array $params = []): ?string
    {
        $__scope = self::__currentGet('scope');

        $content = str_replace('__scope', "_$__scope", $content);

        $type = File::getEx(self::__currentGet('importing_file') ?? '');
        $class = self::$RENDER_CLASS[$type][0];

        if (!$class) return null;
        if (!class_exists($class)) return null;

        return $class::renderizeAction($content, $params);
    }

    /** Inicializa uma view */
    protected static function __currentOpen(string $call, array $scheme, array $data = [], ?string $importOnly = null)
    {
        $scope = mx5([self::$SCOPE, $scheme['scope']]);

        if (isset(self::$CURRENT[$scope])) return false;

        $current = [];
        $current['call'] = $call;
        $current['scope'] = $scope;
        $current['data'] = [...self::__currentGet('data') ?? [], ...$data];

        if (!$importOnly) {
            $current['imports'] = array_filter($scheme['imports'], fn($v) => self::$RENDER_CLASS[File::getEx($v)][1]);
        } else {
            $current['imports'] = array_filter($scheme['imports'], fn($v) => File::getEx($v) == $importOnly);
            if (!count($current['imports']))
                return false;
        }

        self::$CURRENT[$scope] = $current;

        return true;
    }

    /** Finaliza a view atual */
    protected static function __currentClose()
    {
        $scope = self::__currentGet('scope');
        if ($scope) unset(self::$CURRENT[$scope]);
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
        $scope = self::__currentGet('scope');
        if ($scope) self::$CURRENT[$scope][$var] = $value;
    }

    /** Verifica se a view pai é de um dos tipos fornecidos  */
    protected static function parentType(...$types): bool
    {
        $parentKey = array_keys(self::$CURRENT);

        if (count($parentKey) < 2)
            return false;

        $parentKey = $parentKey[count($parentKey) - 2];

        if (!self::$CURRENT[$parentKey]['importing_file'])
            return false;

        $parentType = File::getEx(self::$CURRENT[$parentKey]['importing_file']);

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

            foreach (Path::seekForDirs('system/view') as $viewPath) {

                foreach (Dir::seekForFile($viewPath, true) as $viewFile) {

                    $fileEx = File::getEx($viewFile);

                    if (isset(self::$RENDER_CLASS[$fileEx])) {
                        $path = Dir::getOnly($viewFile);
                        $file = File::getOnly($viewFile);
                        $fileName = File::getName($file);
                        $import = path($viewPath, $viewFile);

                        $namespace = path($path, $fileName);
                        $scope = md5($namespace);

                        if (!isset($scheme[$namespace]) || $scheme[$namespace]['origin'] == $viewPath) {
                            $scheme[$namespace] = $scheme[$namespace] ?? [
                                'scope' => $scope,
                                'origin' => $viewPath,
                                'direct' => true,
                                'imports' => ['php' => null, 'html' => null]
                            ];

                            if (!$scheme[$namespace]['direct']) {
                                $scheme[$namespace]['direct'] = true;
                                $scheme[$namespace]['imports'] = ['php' => null, 'html' => null];
                            }

                            $scheme[$namespace]['imports'][$fileEx] = $import;
                        }

                        $pathName = explode('/', $path);
                        $pathName = array_pop($pathName);

                        if ($pathName == $fileName) {

                            $namespace = path($path);
                            $scope = md5($namespace);

                            if (!isset($scheme[$namespace]) || $scheme[$namespace]['origin'] == $viewPath) {
                                $scheme[$namespace] = $scheme[$namespace] ?? [
                                    'scope' => $scope,
                                    'origin' => $viewPath,
                                    'direct' => false,
                                    'imports' => ['php' => null, 'html' => null]
                                ];

                                if (!$scheme[$namespace]['direct'])
                                    $scheme[$namespace]['imports'][$fileEx] = $import;
                            }
                        }
                    }
                }
            }

            foreach ($scheme as &$item) {
                $item['imports'] = array_filter($item['imports']);
                $item['imports'] = array_values($item['imports']);
                unset($item['origin']);
                unset($item['direct']);
            }

            return $scheme;
        });
    }

    /** Resolve a referencia de uma view */
    protected static function resolveViewRef($ref): string
    {
        $currentFile = self::__currentGet('importing_file');

        if ($currentFile) {
            $currentFile = Dir::getOnly($currentFile);
            $currentFile = explode('/', $currentFile);
            array_shift($currentFile);
            array_shift($currentFile);

            if (str_starts_with($ref, '.../')) {
                array_pop($currentFile);
                array_pop($currentFile);
                $call = path(...$currentFile);
                $ref = path($call, substr($ref, 4));
            } elseif (str_starts_with($ref, '../')) {
                array_pop($currentFile);
                $call = path(...$currentFile);
                $ref = path($call, substr($ref, 3));
            } elseif (str_starts_with($ref, './')) {
                $call = path(...$currentFile);
                $ref = path($call, substr($ref, 2));
            }
        }

        $ref = trim($ref, '/');

        return $ref;
    }

    /** Realiza a importação do conteúdo de uma view PHP */
    protected static function importViewFilePhp($__FILEPATH__, $__DATA): array
    {
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
}
