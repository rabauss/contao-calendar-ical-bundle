<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener;

use Cgoit\ContaoCalendarIcalBundle\Classes\CalendarImport;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;

#[AsHook('getAllEvents')]
class GetAllEventsListener
{
    public function __construct(private readonly CalendarImport $calendarImport)
    {
    }

    /**
     * @param array<mixed> $events
     * @param array<mixed> $calendars
     */
    public function __invoke(array $events, array $calendars, int $timeStart, int $timeEnd, Module $module): array
    {
        $arrCalendars = CalendarModel::findBy(
            ['id IN ('.implode(',', $calendars).')', 'ical_source=?'],
            ['1'],
        );

        foreach ($arrCalendars as $calendar) {
            $this->calendarImport->importCalendarWithData($calendar);
        }

        return $events;
    }
}
