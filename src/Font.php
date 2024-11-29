<?php
declare(strict_types=1);

namespace Badahead\TheDraw\Font {

    use Exception;
    use function array_key_last;
    use function file_exists;
    use function file_get_contents;
    use function hexdec;
    use function iconv;
    use function ord;
    use function sprintf;
    use function strlen;
    use function substr;

    class Font
    {
        private static array $fonts    = [];
        private int          $filesize = 0;
        /* @var FontHeader[] $headers */
        private array $headers          = [];
        private array $data             = [];
        private array $matrixForeground = [];
        private array $matrixBackground = [];
        private int   $posX;
        private int   $posY;
        private int   $colBackground;
        private int   $colForeground;
        private int   $charPosX;
        private array $matrix;

        /**
         * @param string $filename The file to be processed.
         * @param int $letterSpacing The letterSpacing to be used (default is 2).
         * @param int $realSpaceSize The size of the space to be used (default is 5).
         *
         * @return void
         * @throws Exception If the font file does not exist.
         */
        public function __construct(private readonly string $filename, private readonly int $letterSpacing = 2, private readonly int $realSpaceSize = 5, private readonly bool $convertToUtf8 = false) {
            if (file_exists($this->filename)) {
                $binString      = file_get_contents($this->filename);
                $this->filesize = strlen($binString);
                $this->parse(binString: $binString);
            }
            else {
                throw new Exception("Font file does not exist");
            }
        }

        /**
         * Parses the binary string to extract font headers and data.
         *
         * @param string $binString The binary string containing font data to be parsed.
         *
         * @return void
         * @throws Exception
         */
        private function parse(string $binString): void {
            $offset = 0;
            $fontId = 0;
            while ($offset + 20 < $this->filesize) {
                $this->headers[$fontId] = new FontHeader(fontName: substr($binString, $offset + 25, 12));
                $this->headers[$fontId]->setFontType(fontType: ord(substr($binString, $offset + 41, 1)));
                $this->headers[$fontId]->setBlockSize(blockSize: (int)hexdec(sprintf('%02X', ord(substr($binString, $offset + 44, 1))) . sprintf('%02X', ord(substr($binString, $offset + 43, 1)))));
                $n = 0;
                for ($i = 0; $i < 94; $i++) {
                    $this->headers[$fontId]->setLettersOffsets(key: $i, offset: hexdec(sprintf('%02X', ord(substr($binString, $offset + 45 + $n + 1, 1))) . sprintf('%02X', ord(substr($binString, $offset + 45 + $n, 1)))));
                    $n += 2;
                }
                $this->data[$fontId] = substr($binString, $offset + 233, $this->headers[$fontId]->blockSize);
                $offset              += 212 + $this->headers[$fontId]->blockSize + 1;
                $fontId++;
            }
        }

        /**
         * Renders text using specified font settings.
         *
         * @param string $text The text to be rendered.
         * @param string $filename The filename of the font to be used.
         * @param int $fontId The font identifier. Default is 0.
         * @param int $letterSpacing The letterSpacing between characters. Default is 2.
         * @param int $realSpaceSize The size of the space character. Default is 5.
         * @return string The rendered text.
         * @throws Exception
         */
        public static function render(string $text, string $filename, int $fontId = 0, int $letterSpacing = 2, int $realSpaceSize = 5, bool $convertToUtf8 = false): string {
            $index = str_replace('/', '', $filename . '-' . $letterSpacing . '-' . $realSpaceSize);
            if (!isset(self::$fonts[$index])) {
                self::$fonts[$index] = new self(filename: $filename, letterSpacing: $letterSpacing, realSpaceSize: $realSpaceSize, convertToUtf8: $convertToUtf8);
            }
            return self::$fonts[$index]->textRender(text: $text, fontId: $fontId);
        }

        /**
         * Renders the given text with the specified font ID.
         *
         * @param string $text The text to be rendered.
         * @param int $fontId The ID of the font to render the text with. Defaults to 0 (first font inside a set).
         * @return string The rendered text.
         * @throws Exception
         */
        private function textRender(string $text, int $fontId = 0): string {
            $this->textRenderPrepare(text: $text, fontId: $fontId);
            $result     = '';
            $newColConv = '';
            $oldColConv = '';
            for ($i = 0; $i < 12; $i++) {
                if (array_key_last($this->matrix[$i]) != 0) {
                    for ($n = 0; $n <= array_key_last($this->matrix[$i]); $n++) {
                        if (!isset($this->matrix[$i][$n])) {
                            $oldColConv = "\x1b[0m";
                            $result     .= $oldColConv . ' ';
                        }
                        elseif ($this->matrix[$i][$n] === "\r") {
                            if ($this->headers[$fontId]->fontType === FontHeader::FONT_TYPE_COLOR && ($newColConv[2] != 4 || $newColConv[3] != 0)) {
                                if ($oldColConv[2] != 0 && $oldColConv[3] !== 'm') {
                                    $oldColConv = "\x1b[0m";
                                    $result     .= $oldColConv;
                                }
                            }
                            $result .= ' ';
                        }
                        else {
                            if ($this->headers[$fontId]->fontType === FontHeader::FONT_TYPE_COLOR) {
                                $newColConv = $this->colConv(var1: $i, var2: $n);
                                if ($newColConv !== $oldColConv) {
                                    $result     .= $newColConv;
                                    $oldColConv = $newColConv;
                                }
                            }
                            if ($this->convertToUtf8) {
                                $result .= iconv('IBM437', 'UTF8', (string)$this->matrix[$i][$n]);
                            }
                            else {
                                $result .= $this->matrix[$i][$n];
                            }
                        }
                    }
                    $oldColConv = "\x1b[0m";
                    $result     .= $oldColConv . "\n";
                }
            }
            return $result;
        }

        /**
         * Performs the rendering of text using the specified font ID and sets up internal structures.
         *
         * @param string $text The text to be rendered.
         * @param int $fontId The ID of the font to render the text with. Defaults to 0 (first font inside a set).
         * @return void
         * @throws Exception If the specified font does not exist in the font file.
         */
        private function textRenderPrepare(string $text, int $fontId = 0): void {
            if (!isset($this->headers[$fontId]) || !isset($this->data[$fontId])) {
                throw new Exception("Font does not exist in font file");
            }
            $this->posX             = 1;
            $this->posY             = 1;
            $this->charPosX         = 1;
            $this->matrix           = [];
            $this->matrixForeground = [];
            $this->matrixBackground = [];
            for ($i = 0; $i < 12; $i++) {
                $this->matrix[$i]           = [];
                $this->matrixForeground[$i] = [];
                $this->matrixBackground[$i] = [];
            }
            if ($this->headers[$fontId]->fontType === FontHeader::FONT_TYPE_COLOR) {
                for ($i = 0; $i < strlen($text); $i++) {
                    if ((ord($text[$i]) >= 33) && (ord($text[$i]) < 126)) {
                        $offset = $this->headers[$fontId]->lettersOffsets[ord($text[$i]) - 33];
                        if ($offset != 65535) {
                            $maxCharWidth = ord($this->data[$fontId][$offset]);
                            $n            = 2;
                            $oldPosX      = $this->posX;
                            do {
                                $char = $this->data[$fontId][$offset + $n];
                                if ($char == "\r") {
                                    $n--;
                                    $this->printChar(char: $char);
                                }
                                elseif ($char !== "\0") {
                                    $col                 = ord($this->data[$fontId][$offset + $n + 1]);
                                    $this->colBackground = (int)floor($col / 16);
                                    $this->colForeground = $col % 16;
                                    $this->printChar(char: $char);
                                }
                                $n += 2;
                            } while ($char !== "\0");
                            $this->posY     = 1;
                            $this->posX     = $oldPosX + $maxCharWidth + $this->letterSpacing;
                            $this->charPosX = $this->posX;
                        }
                    }
                    elseif (ord($text[$i]) == 32) {
                        $this->posX     += $this->realSpaceSize;
                        $this->charPosX = $this->posX;
                    }
                }
            }
            elseif ($this->headers[$fontId]->fontType == FontHeader::FONT_TYPE_BLOCK) {
                $this->colForeground = 15;
                $this->colBackground = 0;
                for ($i = 0; $i < strlen($text); $i++) {
                    if ((ord($text[$i]) >= 33) && (ord($text[$i]) < 126)) {
                        $offset = $this->headers[$fontId]->lettersOffsets[ord($text[$i]) - 33];
                        if ($offset != 65535) {
                            $maxCharWidth = ord($this->data[$fontId][$offset]);
                            $n            = 2;
                            $oldPosX      = $this->posX;
                            do {
                                $char = $this->data[$fontId][$offset + $n];
                                if ($char !== "\0") {
                                    $this->printChar(char: $char);
                                }
                                $n++;
                            } while ($char !== "\0");
                            $this->posY     = 1;
                            $this->posX     = $oldPosX + $maxCharWidth + $this->letterSpacing;
                            $this->charPosX = $this->posX;
                        }
                    }
                    elseif (ord($text[$i]) === 32) {
                        $this->posX     += $this->realSpaceSize;
                        $this->charPosX = $this->posX;
                    }
                }
            }
        }

        /**
         * Prints the specified character at the current cursor position.
         *
         * @param mixed $char The character to be printed.
         * @return void
         */
        private function printChar(mixed $char): void {
            $this->matrix[$this->posY - 1][$this->posX - 1]           = $char;
            $this->matrixBackground[$this->posY - 1][$this->posX - 1] = $this->colBackground;
            $this->matrixForeground[$this->posY - 1][$this->posX - 1] = $this->colForeground;
            if (ord($char[0]) == 13) {
                $this->posX = $this->charPosX;
                $this->posY++;
            }
            else {
                $this->posX++;
            }
        }

        /**
         * Converts the given matrix coordinates into corresponding ANSI color codes.
         *
         * @param int $var1 The row index in the matrix.
         * @param int $var2 The column index in the matrix.
         * @return string The ANSI color code sequence for the specified matrix cell.
         */
        private function colConv(int $var1, int $var2): string {
            $col1 = match ($this->matrixForeground[$var1][$var2]) {
                0  => 30,
                1  => 34,
                2  => 32,
                3  => 36,
                4  => 31,
                5  => 35,
                6  => 33,
                7  => 37,
                8  => 90,
                9  => 94,
                10 => 92,
                11 => 96,
                12 => 91,
                13 => 95,
                14 => 93,
                15 => 97,
            };
            $col2 = match ($this->matrixBackground[$var1][$var2]) {
                0 => 40,
                1 => 44,
                2 => 42,
                3 => 46,
                4 => 41,
                5 => 45,
                6 => 43,
                7 => 47,
            };
            return "\x1b[{$col2};{$col1}m";
        }
    }
}