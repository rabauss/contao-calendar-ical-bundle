<?php

namespace Cgoit\ContaoCalendarIcalBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\CoreBundle\Migration\Version500\AbstractBasicEntitiesMigration;

if (class_exists(AbstractBasicEntitiesMigration::class)) {
    class BasicEntitiesMigration extends AbstractBasicEntitiesMigration
    {
        protected function getDatabaseColumns(): array
        {
            return [
                ['tl_calendar', 'make_ical'],
                ['tl_calendar', 'ical_timezone'],
                ['tl_calendar', 'ical_source'],
                ['tl_calendar', 'ical_alias'],
                ['tl_calendar', 'ical_prefix'],
                ['tl_calendar', 'ical_description'],
                ['tl_calendar', 'ical_url'],
                ['tl_calendar', 'ical_proxy'],
                ['tl_calendar', 'ical_bnpw'],
                ['tl_calendar', 'ical_port'],
                ['tl_calendar', 'ical_filter_event_title'],
                ['tl_calendar', 'ical_pattern_event_title'],
                ['tl_calendar', 'ical_replacement_event_title'],
                ['tl_calendar', 'ical_cache'],
                ['tl_calendar', 'ical_start'],
                ['tl_calendar', 'ical_end'],
                ['tl_calendar', 'ical_source_start'],
                ['tl_calendar', 'ical_source_end'],
                ['tl_calendar', 'ical_last_sync'],
                ['tl_calendar', 'ical_importing'],

                ['tl_calendar_events', 'icssource'],

                ['tl_content', 'ical_calendar'],
                ['tl_content', 'ical_title'],
                ['tl_content', 'ical_description'],
                ['tl_content', 'ical_prefix'],
                ['tl_content', 'ical_start'],
                ['tl_content', 'ical_end'],
                ['tl_content', 'ical_download_template'],
            ];
        }
    }
} else {
    class BasicEntitiesMigration extends AbstractMigration
    {
        public function shouldRun(): bool
        {
            return false;
        }

        public function run(): MigrationResult
        {
            throw new \LogicException();
        }
    }
}
