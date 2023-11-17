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

use Cgoit\ContaoCalendarIcalBundle\Classes\CalendarExport;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\System;

#[AsCronJob('daily')]
class GenerateSubscriptionsCron
{
    public function __construct(private readonly CalendarExport $calendarExport)
    {
    }

    public function __invoke(): void
    {
        $this->calendarExport->removeOldSubscriptions();
        $arrCalendar = CalendarModel::findBy(['make_ical=?'], [1]);

        foreach ($arrCalendar as $objCalendar) {
            $filename = $objCalendar->ical_alias ?? 'calendar'.$objCalendar->id;

            $this->calendarExport->generateFiles($objCalendar->row());
            System::getContainer()
                ->get('monolog.logger.contao.cron')
                ->info('Generated ical subscription "'.$filename.'.ics"')
            ;
        }
    }
}
