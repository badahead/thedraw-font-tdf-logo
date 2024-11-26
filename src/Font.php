<?php
declare(strict_types=1);

namespace Badahead\TheDraw\Font {

    use Exception;
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
        private string $signature = '';
        private int    $filesize  = 0;
        /* @var FontHeader $headers */
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

        public function __construct(private readonly string $filename, private readonly int $spacing = 2, private readonly int $spaceSize = 5) {
            if (file_exists($this->filename)) {
                $binString      = file_get_contents($this->filename);
                $this->filesize = strlen($binString);
                $this->parse($binString);
            }
            else {
                throw new Exception("Font file does not exist");
            }
        }

        private function parse(string $binString): void {
            $this->signature = substr($binString, 1, 18);
            $offset          = 0;
            $fontId          = 0;
            while ($offset + 20 < $this->filesize) {
                $this->headers[$fontId] = new FontHeader();
                $this->headers[$fontId]->setFontName(substr($binString, $offset + 25, 12));
                $this->headers[$fontId]->setFontType(ord(substr($binString, $offset + 41, 1)));
                $this->headers[$fontId]->setLetterSpacing(ord(substr($binString, $offset + 42, 1)));
                $this->headers[$fontId]->setBlockSize((int)hexdec(sprintf('%02X', ord(substr($binString, $offset + 44, 1))) . sprintf('%02X', ord(substr($binString, $offset + 43, 1)))));
                $n = 0;
                for ($i = 0; $i < 94; $i++) {
                    $this->headers[$fontId]->setLettersOffsets($i, hexdec(sprintf('%02X', ord(substr($binString, $offset + 45 + $n + 1, 1))) . sprintf('%02X', ord(substr($binString, $offset + 45 + $n, 1)))));
                    $n += 2;
                }
                $this->data[$fontId] = substr($binString, $offset + 233, $this->headers[$fontId]->blockSize);
                $offset              += 212 + $this->headers[$fontId]->blockSize + 1;
                $fontId++;
            }
        }

        final public function getSignature(): string {
            return $this->signature;
        }

        final public function render(string $text, int $fontId = 0): string {
            $this->text_renderer($text, $fontId);
            $result     = '';
            $newColConv = '';
            $oldColConv = '';
            for ($i = 0; $i < 12; $i++) {
                if (array_key_last($this->matrix[$i]) != 0) {
                    for ($n = 0; $n <= array_key_last($this->matrix[$i]); $n++) {
                        if (!isset($this->matrix[$i][$n])) {
                            $oldColConv = "\x1b[0m";
                            $result     .= $oldColConv;
                        }
                        elseif ($this->matrix[$i][$n] === "\r") {
                            if ($this->headers[$fontId]->fontType === FontHeader::FONT_TYPE_COLOR && ($newColConv[2] != 4 || $newColConv[3] != 0)) {
                                if ($oldColConv[2] != 0 && $oldColConv[3] !== "m") {
                                    $oldColConv = "\x1b[0m";
                                    $result     .= $oldColConv;
                                }
                            }
                            $result .= " ";
                        }
                        else {
                            if ($this->headers[$fontId]->fontType === FontHeader::FONT_TYPE_COLOR) {
                                $newColConv = $this->colconv($i, $n);
                                if ($newColConv !== $oldColConv) {
                                    $result     .= $newColConv;
                                    $oldColConv = $newColConv;
                                }
                            }
                            $result .= iconv('IBM437', 'UTF8', (string)$this->matrix[$i][$n]);
                        }
                    }
                    $oldColConv = "\x1b[0m";
                    $result     .= $oldColConv . "\n";
                }
            }
            return $result;
        }

        private function text_renderer(string $text, int $fontId = 0): void {
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
                        $offset = $this->headers[$fontId]->lettersoffsets[ord($text[$i]) - 33];
                        if ($offset != 65535) {
                            $maxcharwidth = ord($this->data[$fontId][$offset]);
                            $n            = 2;
                            $OLDPOSX      = $this->posX;
                            do {
                                $char = $this->data[$fontId][$offset + $n];
                                if ($char == "\r") {
                                    $n--;
                                    $this->_PRINTCHAR($char);
                                }
                                elseif ($char !== "\0") {
                                    $col                 = ord($this->data[$fontId][$offset + $n + 1]);
                                    $this->colBackground = (int)floor($col / 16);
                                    $this->colForeground = $col % 16;
                                    $this->_PRINTCHAR($char);
                                }
                                $n += 2;
                            } while ($char !== "\0");
                            $this->posY     = 1;
                            $this->posX     = $OLDPOSX + $maxcharwidth + $this->spacing;
                            $this->charPosX = $this->posX;
                        }
                    }
                    elseif (ord($text[$i]) == 32) {
                        $this->posX     += $this->spaceSize;
                        $this->charPosX = $this->posX;
                    }
                }
            }
            elseif ($this->headers[$fontId]->fontType == FontHeader::FONT_TYPE_BLOCK) {
                $this->colForeground = 15;
                $this->colBackground = 0;
                for ($i = 0; $i < strlen($text); $i++) {
                    if ((ord($text[$i]) >= 33) && (ord($text[$i]) < 126)) {
                        $offset = $this->headers[$fontId]->lettersoffsets[ord($text[$i]) - 33];
                        if ($offset != 65535) {
                            $maxcharwidth = ord($this->data[$fontId][$offset]);
                            $n            = 2;
                            $OLDPOSX      = $this->posX;
                            do {
                                $char = $this->data[$fontId][$offset + $n];
                                if ($char !== "\0") {
                                    $this->_PRINTCHAR($char);
                                }
                                $n++;
                            } while ($char !== "\0");
                            $this->posY     = 1;
                            $this->posX     = $OLDPOSX + $maxcharwidth + $this->spacing;
                            $this->charPosX = $this->posX;
                        }
                    }
                    elseif (ord($text[$i]) === 32) {
                        $this->posX     += $this->spaceSize;
                        $this->charPosX = $this->posX;
                    }
                }
            }
        }

        private function _PRINTCHAR(mixed $char): void {
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

        private function colconv(int $var1, int $var2): string {
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