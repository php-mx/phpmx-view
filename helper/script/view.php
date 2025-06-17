<?php

use PhpMx\View;

View::globalPrepare('VIEW', fn($ref, ...$params) => View::render($ref, [], ...$params));
