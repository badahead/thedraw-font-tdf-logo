<?php
declare(strict_types=1);

use Badahead\TheDraw\Font\Font;

require_once __DIR__ . '/vendor/autoload.php';
echo Font::render(text: 'HELLO WORLD', filename: __DIR__ . '/tdf/ROYFNT1.TDF', fontId: 0, letterSpacing: 1);
