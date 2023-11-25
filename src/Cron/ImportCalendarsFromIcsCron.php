<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Cron;

use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;

#[AsCronJob('daily')]
class ImportCalendarsFromIcsCron
{
    public function __construct(
        private readonly IcsImport $icsImport,
    ) {
    }

    public function __invoke(): void
    {
        $arrCalendars = CalendarModel::findBy(['ical_source != ?'], ['']);

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $arrCalendar) {
                $this->icsImport->importIcsForCalendar($arrCalendar, true);
            }
        }
    }
}
