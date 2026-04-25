<?php

use PhpMx\View\InlineCss;

if (!function_exists('htmlToInlineCss')) {

    /**
     * Converte os estilos CSS de blocos <style> para atributos style inline em cada elemento HTML.
     * Seletores com pseudo-classes (:hover, :focus) e @media são ignorados.
     * Estilos inline já existentes nos elementos têm prioridade sobre os do <style>.
     * @param string $html HTML de entrada com blocos <style> (fragmento ou página completa).
     * @return string HTML com estilos aplicados inline e blocos <style> removidos.
     */
    function htmlToInlineCss(string $html): string
    {
        return InlineCss::apply($html);
    }
}
