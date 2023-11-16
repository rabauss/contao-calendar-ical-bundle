<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace contao\dca;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\DataContainer;
use Craffft\ContaoCalendarICalBundle\Classes\CalendarExport;

/*
 * Table tl_calendar_events
 */
$GLOBALS['TL_DCA']['contao\dca\tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_ical', 'generateICal'];

$GLOBALS['TL_DCA']['contao\dca\tl_calendar_events']['list']['global_operations']['export'] =
    [
        'label' => &$GLOBALS['TL_LANG']['MSC']['import_calendar'],
        'href' => 'key=import',
        'class' => 'header_import header_icon',
        'attributes' => 'onclick="Backend.getScrollOffset();"',
    ];

$GLOBALS['TL_DCA']['contao\dca\tl_calendar_events']['fields']['icssource'] =
    [
        'label' => &$GLOBALS['TL_LANG']['contao\dca\tl_content']['source'],
        'eval' => ['fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => 'ics,csv'],
    ];

class tl_calendar_events extends Backend
{
    public function generateICal(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $calendarEvent = CalendarEventsModel::findByPk($dc->id);

        if (null !== $calendarEvent) {
            $this->import(CalendarExport::class);
            $this->CalendarExport->exportCalendar($calendarEvent->pid);
        }
    }
}
