<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\RowIteratorInterface;
use RuntimeException;
use XMLReader;

final class XlsxRowIterator implements RowIteratorInterface
{
    private string $xlsxPath;
    private string $stringsFile = '';
    private string $offsetsFile = '';

    /** @var resource|null */
    private mixed $stringsHandle = null;

    /** @var resource|null */
    private mixed $offsetsHandle = null;

    public function __construct(string $xlsxPath)
    {
        $this->xlsxPath = $xlsxPath;
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    public function rows(): iterable
    {
        if (!file_exists($this->xlsxPath)) {
            throw new RuntimeException('XLSX file not found: ' . $this->xlsxPath);
        }

        $this->loadSharedStrings();

        $reader = new XMLReader();

        $uri = 'zip://' . realpath($this->xlsxPath) . '#xl/worksheets/sheet1.xml';

        if (!$reader->open($uri)) {
            throw new RuntimeException('Cannot open sheet1.xml from XLSX.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                    $rowXml = $reader->readOuterXML();
                    $row = $this->parseRowXml($rowXml);

                    if ($row !== []) {
                        yield $row;
                    }
                }
            }
        } finally {
            $reader->close();
            $this->cleanup();
        }
    }

    /**
     * Writes shared strings to two temp files:
     *   offsets.tmp — 4 bytes per entry: byte position of that string in strings.tmp
     *   strings.tmp — [uint32 length][raw UTF-8 bytes] per entry
     *
     * This keeps PHP memory flat — no array grows with the number of unique strings.
     */
    private function loadSharedStrings(): void
    {
        $this->stringsFile = tempnam(sys_get_temp_dir(), 'xlsx_str_');
        $this->offsetsFile = tempnam(sys_get_temp_dir(), 'xlsx_off_');

        $strWrite = fopen($this->stringsFile, 'wb');
        $offWrite = fopen($this->offsetsFile, 'wb');

        $uri = 'zip://' . realpath($this->xlsxPath) . '#xl/sharedStrings.xml';
        $reader = new XMLReader();

        libxml_use_internal_errors(true);
        $opened = $reader->open($uri);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($opened) {
            $position = 0;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'si') {
                    $text = $this->extractTextFromSharedString($reader->readOuterXML());
                    $length = strlen($text);

                    fwrite($offWrite, pack('V', $position));
                    fwrite($strWrite, pack('V', $length) . $text);

                    $position += 4 + $length;
                }
            }

            $reader->close();
        }

        fclose($strWrite);
        fclose($offWrite);

        $this->stringsHandle = fopen($this->stringsFile, 'rb');
        $this->offsetsHandle = fopen($this->offsetsFile, 'rb');
    }

    private function resolveSharedString(int $index): string
    {
        if ($this->offsetsHandle === null || $this->stringsHandle === null) {
            return '';
        }

        fseek($this->offsetsHandle, $index * 4);
        $packed = fread($this->offsetsHandle, 4);

        if ($packed === false || strlen($packed) < 4) {
            return '';
        }

        $offset = unpack('V', $packed)[1];

        fseek($this->stringsHandle, $offset);
        $lenPacked = fread($this->stringsHandle, 4);

        if ($lenPacked === false || strlen($lenPacked) < 4) {
            return '';
        }

        $length = unpack('V', $lenPacked)[1];

        if ($length === 0) {
            return '';
        }

        $result = fread($this->stringsHandle, $length);

        return $result !== false ? $result : '';
    }

    private function cleanup(): void
    {
        if ($this->stringsHandle !== null) {
            fclose($this->stringsHandle);
            $this->stringsHandle = null;
        }

        if ($this->offsetsHandle !== null) {
            fclose($this->offsetsHandle);
            $this->offsetsHandle = null;
        }

        if ($this->stringsFile !== '' && file_exists($this->stringsFile)) {
            unlink($this->stringsFile);
            $this->stringsFile = '';
        }

        if ($this->offsetsFile !== '' && file_exists($this->offsetsFile)) {
            unlink($this->offsetsFile);
            $this->offsetsFile = '';
        }
    }

    private function extractTextFromSharedString(string $xml): string
    {
        $reader = new XMLReader();

        if (!$reader->XML($xml)) {
            return '';
        }

        $text = '';

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 't') {
                $text .= $reader->readString();
            }
        }

        $reader->close();

        return $text;
    }

    private function parseRowXml(string $xml): array
    {
        $reader = new XMLReader();

        if (!$reader->XML($xml)) {
            return [];
        }

        $cells = [];

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                $cellRef = $reader->getAttribute('r') ?? '';
                $cellType = $reader->getAttribute('t') ?? '';

                $column = $this->columnFromCellRef($cellRef);
                $value = $this->readCellValue($reader, $cellType);

                if ($column !== '') {
                    $cells[$column] = $value;
                }
            }
        }

        $reader->close();

        ksort($cells);

        return $cells;
    }

    private function readCellValue(XMLReader $reader, string $cellType): string
    {
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'v') {
                $rawValue = $reader->readString();

                if ($cellType === 's') {
                    return $this->resolveSharedString((int) $rawValue);
                }

                return $rawValue;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 't') {
                return $reader->readString();
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'c') {
                break;
            }
        }

        return '';
    }

    private function columnFromCellRef(string $cellRef): string
    {
        return preg_replace('/\d+/', '', $cellRef) ?? '';
    }
}
