<?php

use PhpMx\View\RenderMd;

if (!function_exists('mdToHtml')) {

    /**
     * Converte uma string Markdown em HTML.
     * Suporta títulos, parágrafos, listas, blockquotes, blocos de código, links, imagens e formatação inline.
     * @param string $md Conteúdo em formato Markdown.
     * @return string HTML gerado a partir do Markdown.
     */
    function mdToHtml(string $md): string
    {
        return RenderMd::toHtml($md);
    }
}
