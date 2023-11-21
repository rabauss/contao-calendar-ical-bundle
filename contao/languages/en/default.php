<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Cgoit\ContaoCalendarIcalBundle\Controller\ContentElement\ContentIcalElement;

$GLOBALS['TL_LANG']['CTE'][ContentIcalElement::TYPE] = ['iCal', 'This element contains a hyperlink to an vCalendar (iCal) which can be downloaded and used to display calendar dates in an external calendar application.'];

$GLOBALS['TL_LANG']['MSC']['import_calendar'] = ['Import calendar', 'Import an iCal (.ics) file with events into your calendar.'];
$GLOBALS['TL_LANG']['MSC']['export_calendar'] = ['Export calendar', 'Export an iCal (.ics) file with events from your calendar.'];
$GLOBALS['TL_LANG']['MSC']['location'] = 'Location';
$GLOBALS['TL_LANG']['ERR']['only_one_file'] = 'Please choose only one file for upload!';
