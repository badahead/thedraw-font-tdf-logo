<?php
declare(strict_types=1);

use Badahead\TheDraw\Font\Render;

require_once __DIR__ . '/vendor/autoload.php';
echo Render::render('Hello World', __DIR__ . '/tdf/3D-ASCII.TDF', 0, 2, 5);
