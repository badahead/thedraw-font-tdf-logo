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
        public string $fontType       = self::FONT_TYPE_UNKNOWN;
        public int    $blockSize      = 0;
        public array  $lettersOffsets = [];

        /**
         * Class constructor.
         *
         * @param string $fontName The name of the font to be used.
         *
         * @return void
         */
        public function __construct(public string $fontName) {}

        /**
         * Sets the font type for the object.
         *
         * @param int $fontType The type of the font to be used, where 0 is Outline, 1 is Block, 2 is Color.
         *
         * @return void
         *
         * @throws Exception if the font type is Outline or Unknown.
         */
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

        /**
         * Sets the block size.
         *
         * @param int $blockSize The size of the block to be set.
         *
         * @return void
         */
        final public function setBlockSize(int $blockSize): void {
            $this->blockSize = $blockSize;
        }

        /**
         * Sets the offset value for a specific letter.
         *
         * @param int $key The key representing the letter.
         * @param int $offset The offset value to be set for the specified letter.
         *
         * @return void
         */
        final public function setLettersOffsets(int $key, int $offset): void {
            $this->lettersOffsets[$key] = $offset;
        }
    }
}