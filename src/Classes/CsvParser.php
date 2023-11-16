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

class CsvParser
{
    protected $filename;

    protected $separator;

    protected $encoding;

    protected $reader;

    public function __construct($filename, $encoding = 'utf8')
    {
        $this->filename = $filename;
        $this->separator = $this->determineSeparator();
        $this->encoding = $encoding;
        $this->reader = new CsvReader($filename, $this->separator, $this->encoding);
    }

    public function extractHeader()
    {
        $this->reader->rewind();

        return $this->reader->current();
    }

    public function getDataArray($lines = 1)
    {
        if (1 === $lines) {
            $this->reader->next();
            if ($this->reader->valid()) {
                return $this->reader->current();
            }

            return false;
        }

        $res = [];

        do {
            $this->reader->next();
            $res[] = $this->reader->current();
            --$lines;
        } while ($this->reader->valid() && $lines > 0);

        if (\count($res)) {
            return $res;
        }

        return false;
    }

    protected function determineSeparator()
    {
        $separators = [',', ';'];
        $file = fopen($this->filename, 'r');
        $string = fgets($file);
        fclose($file);
        $matched = [];

        foreach ($separators as $separator) {
            if (preg_match("/$separator/", $string)) {
                $matched[] = $separator;
            }
        }

        if (1 === \count($matched)) {
            return $matched[0];
        }

        return null;
    }
}
