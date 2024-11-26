<?php
declare(strict_types=1);

namespace Badahead\TheDraw\Font {

    use Exception;

    class FontHeader
    {
        public const string FONT_TYPE_OUTLINE = 'OUTLINE';
        public const string FONT_TYPE_BLOCK   = 'BLOCK';
        public const string FONT_TYPE_COLOR   = 'COLOR';
        public const string FONT_TYPE_UNKNOWN = 'UNKNOWN';
        public string $fontName       = '';
        public string $fontType       = self::FONT_TYPE_UNKNOWN;
        public int    $letterSpacing  = 0;
        public int    $blockSize      = 0;
        public array  $lettersOffsets = [];

        final public function setFontName(string $fontName): void {
            $this->fontName = $fontName;
        }

        final public function setFontType(int $fontType): void {
            $this->fontType = match ($fontType) {
                0       => self::FONT_TYPE_OUTLINE,
                1       => self::FONT_TYPE_BLOCK,
                2       => self::FONT_TYPE_COLOR,
                default => self::FONT_TYPE_UNKNOWN,
            };
            if ($this->fontType === self::FONT_TYPE_OUTLINE) {
                throw new Exception('Outline Font Type not supported');
            }
            elseif ($this->fontType === self::FONT_TYPE_UNKNOWN) {
                throw new Exception('Unknown Font Type');
            }
        }

        final public function setLetterSpacing(int $letterSpacing): void {
            $this->letterSpacing = $letterSpacing;
        }

        final public function setBlockSize(int $blockSize): void {
            $this->blockSize = $blockSize;
        }

        final public function setLettersOffsets(int $key, int $offset): void {
            $this->lettersOffsets[$key] = $offset;
        }
    }
}