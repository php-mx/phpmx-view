<?php

use PhpMx\View;

View::mediaStyle('tablet', 'screen and (min-width: 700px)');
View::mediaStyle('desktop', 'screen and (min-width: 1200px)');
View::mediaStyle('print', 'print');

View::globalPrepare('VIEW', fn($ref, ...$params) => View::render($ref, [], ...$params));
