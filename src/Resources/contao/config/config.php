<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Craffft\ContaoCalendarICalBundle\Classes\CalendarExport;
use Craffft\ContaoCalendarICalBundle\Classes\CalendarImport;

/*
 * Content elements
 */
$GLOBALS['TL_CTE']['files']['ical'] = 'ContentICal';

$GLOBALS['BE_MOD']['content']['calendar']['import'] = ['CalendarImport', 'importCalendar'];
$GLOBALS['BE_MOD']['content']['calendar']['stylesheet'] = 'bundles/craffftcontaocalendarical/calendar-ical.css';

/*
 * Cron jobs
 */
$GLOBALS['TL_CRON']['daily'][] = [CalendarExport::class, 'generateSubscriptions'];

/*
 * Add 'ical' to the URL keywords to prevent problems with URL manipulating modules like folderurl
 */
if (!array_key_exists('urlKeywords', $GLOBALS['TL_CONFIG'])) {
    $GLOBALS['TL_CONFIG'] += ['urlKeywords' => ''];
}
$GLOBALS['TL_CONFIG']['urlKeywords'] .= (strlen(trim($GLOBALS['TL_CONFIG']['urlKeywords'])) ? ',' : '').'ical';

$GLOBALS['TL_HOOKS']['removeOldFeeds'][] = [CalendarExport::class, 'removeOldSubscriptions'];
$GLOBALS['TL_HOOKS']['getAllEvents'][] = [CalendarImport::class, 'getAllEvents'];

/*
 * Module variables
 */
$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays'] = 365;
