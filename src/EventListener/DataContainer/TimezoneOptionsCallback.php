<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Util\TimezoneUtil;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_calendar', target: 'fields.ical_timezone.options')]
class TimezoneOptionsCallback
{
    public function __construct(private readonly TimezoneUtil $timezoneUtil)
    {
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(DataContainer|null $dc): array
    {
        return $this->timezoneUtil->getTimezones();
    }
}
