<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Craffft\ContaoCalendarICalBundle\Classes;

class CsvReader implements \Iterator
{
    protected $fileHandle;

    protected $position;

    protected $filename;

    protected $currentLine;

    protected $currentArray;

    protected $separator = ',';

    protected $encoding = 'utf8';

    public function __construct($filename, $separator = ',', $encoding = 'utf8')
    {
        $this->separator = $separator;
        $this->fileHandle = fopen($filename, 'r');
        if (!$this->fileHandle) {
            return;
        }
        $this->filename = $filename;
        $this->position = 0;
        $this->encoding = $encoding;
        $this->_readLine();
    }

    public function __destruct()
    {
        $this->close();
    }

    // You should not have to call it unless you need to
    // explicitly free the file descriptor
    public function close(): void
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    public function rewind(): void
    {
        if ($this->fileHandle) {
            $this->position = 0;
            rewind($this->fileHandle);
        }

        $this->_readLine();
    }

    public function current()
    {
        return $this->currentArray;
    }

    public function key()
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
        $this->_readLine();
    }

    public function valid()
    {
        return null !== $this->currentArray;
    }

    protected function _readLine(): void
    {
        if (!feof($this->fileHandle)) {
            $this->currentLine = trim(fgets($this->fileHandle));
        } else {
            $this->currentLine = null;
        }
        if (0 !== strcmp($this->encoding, 'utf8') && null !== $this->currentLine) {
            $this->currentLine = utf8_encode($this->currentLine);
        }
        if ('' !== $this->currentLine) {
            $this->currentArray = Csv::parseString($this->currentLine, $this->separator);
        } else {
            $this->currentArray = null;
        }
    }
}
