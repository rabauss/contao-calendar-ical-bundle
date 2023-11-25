<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Slug\Slug;

class AbstractImport extends Backend
{
    public function __construct(protected readonly Slug $slug)
    {
        parent::__construct();
    }

    /**
     * @param array<mixed> $eventcontent
     */
    protected function addEventContent(CalendarEventsModel $objEvent, array $eventcontent): void
    {
        $step = 128;

        foreach ($eventcontent as $content) {
            $cm = new ContentModel();
            $cm->tstamp = time();
            $cm->pid = $objEvent->id;
            $cm->ptable = 'tl_calendar_events';
            $cm->sorting = $step;
            $step *= 2;
            $cm->type = 'text';
            $cm->text = $content;
            $cm->save();
        }
    }

    /**
     * Auto-generate the event alias if it has not been set yet.
     */
    protected function generateAlias(CalendarEventsModel $objEvent): CalendarEventsModel
    {
        $aliasExists = static function (string $alias) use ($objEvent): bool {
            $objEvents = CalendarEventsModel::findBy(
                ['alias=?', 'id!=?'],
                [$alias, $objEvent->id],
            );

            return null !== $objEvents && $objEvents->count() > 0;
        };

        // Generate the alias if there is none
        $objEvent->alias = $this->slug->generate(
            $objEvent->title,
            CalendarModel::findByPk($objEvent->pid)->jumpTo,
            $aliasExists,
        );

        return $objEvent->save();
    }
}
