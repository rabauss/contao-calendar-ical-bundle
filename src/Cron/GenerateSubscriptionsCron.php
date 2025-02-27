<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Cron;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;

#[AsCronJob('daily')]
class GenerateSubscriptionsCron
{
    public function __construct(private readonly ExportController $calendarExport)
    {
    }

    public function __invoke(): void
    {
        $arrCalendar = CalendarModel::findBy(['make_ical=?'], [1]);

        foreach ($arrCalendar as $objCalendar) {
            $this->calendarExport->generateSubscriptions($objCalendar);
        }
    }
}
