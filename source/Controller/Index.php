<?php

namespace Controller;

use PhpMx\Response;
use PhpMx\View;

/** */
class Index
{
    /** */
    function __invoke()
    {
        Response::type('md');

        Response::content(View::render('teste.md'));

        Response::send();
    }
}
