<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Contao\ClassLoader;
use Contao\TemplateLoader;

/*
 * Register the classes
 */
ClassLoader::addClasses(
    [
        // Classes
        \Craffft\ContaoCalendarICalBundle\Classes\CalendarExport::class => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Classes/CalendarExport.php',
        \Craffft\ContaoCalendarICalBundle\Classes\CalendarImport::class => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Classes/CalendarImport.php',
        \Craffft\ContaoCalendarICalBundle\Classes\ContentICal::class => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Classes/ContentICal.php',
        \Craffft\ContaoCalendarICalBundle\Classes\Csv::class => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Classes/Csv.php',
        \Craffft\ContaoCalendarICalBundle\Classes\CsvParser::class => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Classes/CsvParser.php',
        \Craffft\ContaoCalendarICalBundle\Classes\CsvReader::class => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Classes/CsvReader.php',
    ]);

/*
 * Register the templates
 */
TemplateLoader::addFiles(
    [
        'be_import_calendar' => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Resources/contao/templates',
        'be_import_calendar_confirmation' => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Resources/contao/templates',
        'be_import_calendar_csv_headers' => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Resources/contao/templates',
        'ce_ical' => 'vendor/cgoit/contao-calendar-ical-php8-bundle/src/Resources/contao/templates',
    ]);
