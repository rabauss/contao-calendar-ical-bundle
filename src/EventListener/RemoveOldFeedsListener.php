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

use Cgoit\ContaoCalendarIcalBundle\Classes\CalendarExport;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

#[AsHook('removeOldFeeds')]
class RemoveOldFeedsListener
{
    public function __construct(private readonly CalendarExport $calendarExport)
    {
    }

    /**
     * Remove old ics files from the root directory.
     *
     * @return array<mixed>
     */
    public function __invoke(): array
    {
        return $this->calendarExport->removeOldSubscriptions();
    }
}
