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
use Contao\CoreBundle\Slug\Slug;

class AbstractImport extends Backend
{
    public function __construct(protected readonly Slug $slug)
    {
        parent::__construct();
    }

    /**
     * Auto-generate the event alias if it has not been set yet.
     */
    protected function generateAlias(string $varValue, int $id, int $pid): string
    {
        $aliasExists = static function (string $alias) use ($id): bool {
            $objEvents = CalendarEventsModel::findBy(
                ['alias=?', 'id!=?'],
                [$alias, $id],
            );

            return null !== $objEvents && $objEvents->count() > 0;
        };

        // Generate the alias if there is none
        return $this->slug->generate(
            $varValue,
            CalendarModel::findByPk($pid)->jumpTo,
            $aliasExists,
        );
    }
}
