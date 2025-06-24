<?php

use PhpMx\Terminal;
use PhpMx\View;

return new class extends Terminal {

    function __invoke()
    {
        View::render('teste');
    }
};
