<?php
declare(strict_types=1);

namespace Badahead\TheDraw\Font {
    class Render
    {
        private static array $fonts = [];

        public static function render(string $text, string $filename, int $fontId = 0, int $spacing = 2, int $spaceSize = 5): string {
            $index = str_replace('/', '', $filename . '-' . $spacing . '-' . $spaceSize);
            if (!isset(self::$fonts[$index])) {
                self::$fonts[$index] = new Font(filename: $filename, spacing: $spacing, spaceSize: $spaceSize);
            }
            return self::$fonts[$index]->render($text, $fontId);
        }
    }
}