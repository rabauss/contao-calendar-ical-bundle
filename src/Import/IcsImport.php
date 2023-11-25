<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use _PHPStan_532094bc1\Nette\PhpGenerator\Closure;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Contao\File;
use Contao\System;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Pc;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Util\DateTimeZoneFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class IcsImport extends AbstractImport
{
    public function __construct(
        private readonly Connection $db,
        Slug $slug,
        private readonly int $defaultEndTimeDifference,
    ) {
        parent::__construct($slug);
    }

    public function importIcsForCalendar(CalendarModel $objCalendar, bool $force_import = false): void
    {
        if (!empty($objCalendar->ical_source) && !empty($objCalendar->ical_url)) {
            $last_change = (int) $objCalendar->ical_last_sync;
            if (empty($last_change)) {
                $last_change = time();
                $force_import = true;
            }

            if (((time() - $last_change > $objCalendar->ical_cache) && (1 !== $objCalendar->ical_importing || (time() - $objCalendar->tstamp) > 120)) || $force_import) {
                $objCalendar->ical_importing = true;
                $objCalendar->save();

                // create new from ical file
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->error('Reload iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.'): Triggered by '.time().' - '.$last_change.' = '.(time() - $last_change).' > '.$objCalendar->ical_cache)
                ;

                $startDate = !empty((string) $objCalendar->ical_source_start) ?
                    new Date($objCalendar->ical_source_start, Config::get('dateFormat')) :
                    new Date(time(), Config::get('dateFormat'));
                $endDate = !empty((string) $objCalendar->ical_source_end) ?
                    new Date($objCalendar->ical_source_end, Config::get('dateFormat')) :
                    new Date(time() + $this->defaultEndTimeDifference * 24 * 3600, Config::get('dateFormat'));
                $tz = [$objCalendar->ical_timezone, $objCalendar->ical_timezone];
                $this->importFromWebICS($objCalendar, $startDate, $endDate, $tz);

                $objCalendar->tstamp = time();
                $objCalendar->ical_importing = false;
                $objCalendar->ical_last_sync = time();
                $objCalendar->save();
            }
        }
    }

    /**
     * @param array<mixed>|bool $tz
     *
     * @throws \Exception
     */
    public function importFromIcsFile(Vcalendar $cal, CalendarModel $objCalendar, Date $startDate, Date $endDate, array|bool $tz, string|null $filterEventTitle, string|null $patternEventTitle, string|null $replacementEventTitle, bool $deleteCalendar = false, int $timeshift = 0): void
    {
        static::loadDataContainer('tl_calendar_events');

        $schemaManager = $this->db->createSchemaManager();
        $fields = $schemaManager->listTableColumns('tl_calendar_events');

        $fieldNames = [];

        foreach ($fields as $field) {
            if ('id' !== $field->getName()) {
                $fieldNames[] = $field->getName();
            }
        }

        // Get all default values for new entries
        $defaultFields = array_filter($GLOBALS['TL_DCA']['tl_calendar_events']['fields'], static fn ($val) => isset($val['default']));

        $foundevents = [];

        if ($deleteCalendar && !empty($objCalendar->id)) {
            $arrEvents = CalendarEventsModel::findByPid($objCalendar->id);
            if (!empty($arrEvents)) {
                foreach ($arrEvents as $event) {
                    $arrColumns = ['ptable=? AND pid=?'];
                    $arrValues = ['tl_calendar_events', $event->id];
                    $content = ContentModel::findBy($arrColumns, $arrValues);

                    if ($content) {
                        while ($content->next()) {
                            $content->delete();
                        }
                    }

                    $event->delete();
                }
            }
        }

        $eventArray = $cal->selectComponents((int) date('Y', (int) $startDate->tstamp), (int) date('m', (int) $startDate->tstamp),
            (int) date('d', (int) $startDate->tstamp), (int) date('Y', (int) $endDate->tstamp), (int) date('m', (int) $endDate->tstamp),
            (int) date('d', (int) $endDate->tstamp), IcalInterface::VEVENT, true);

        if (\is_array($eventArray)) {
            /** @var Vevent $vevent */
            foreach ($eventArray as $vevent) {
                $objEvent = new CalendarEventsModel();
                $objEvent->tstamp = time();
                $objEvent->pid = $objCalendar->id;
                $objEvent->published = true;

                foreach ($defaultFields as $field => $value) {
                    $varValue = $value['default'];
                    if ($varValue instanceof \Closure) {
                        $varValue = $varValue();
                    }
                    $objEvent->{$field} = $varValue;
                }

                if (!empty(BackendUser::getInstance())) {
                    $objEvent->author = BackendUser::getInstance()->id;
                }

                /** @var Pc|bool|null $dtstart */
                $dtstart = $vevent->getDtstart(true);
                /** @var Pc|bool|null $dtend */
                $dtend = $vevent->getDtend(true);

                $rrule = $vevent->getRrule();
                $summary = $vevent->getSummary() ?? '';
                if (!empty($filterEventTitle) && !str_contains(mb_strtolower($summary), mb_strtolower($filterEventTitle))) {
                    continue;
                }
                $description = $vevent->getDescription() ?: '';
                $location = trim($vevent->getLocation() ?: '');
                $uid = $vevent->getUid();

                $title = $summary;
                if (!empty($patternEventTitle) && !empty($replacementEventTitle)) {
                    $title = preg_replace($patternEventTitle, $replacementEventTitle, $summary);
                }

                // set values from vevent
                $objEvent->title = !empty($title) ? $title : $summary;
                $cleanedup = \strlen($description) ? $description : $summary;
                $cleanedup = preg_replace('/[\\r](\\\\)n(\\t){0,1}/ims', '', $cleanedup);
                $cleanedup = preg_replace('/[\\r\\n]/ims', '', $cleanedup);
                $cleanedup = str_replace('\\n', '<br />', $cleanedup);
                $eventcontent = [];

                if (\strlen($cleanedup)) {
                    $eventcontent[] = '<p>'.$cleanedup.'</p>';
                }

                // calendar_events_plus fields
                if (!empty($location)) {
                    if (\in_array('location', $fieldNames, true)) {
                        $location = preg_replace('/(\\\\r)|(\\\\n)/im', "\n", $location);
                        $objEvent->location = $location;
                    } else {
                        $location = preg_replace('/(\\\\r)|(\\\\n)/im', '<br />', $location);
                        $eventcontent[] = '<p><strong>'.$GLOBALS['TL_LANG']['MSC']['location'].':</strong> '.$location.'</p>';
                    }
                }

                if (\in_array('cep_participants', $fieldNames, true) && \is_array($vevent->getAllAttendee())) {
                    $attendees = [];

                    foreach ($vevent->getAllAttendee() as $attendee) {
                        if (!empty($attendee->getParams('CN'))) {
                            $attendees[] = (string) $attendee->getParams('CN');
                        }
                    }

                    if (!empty($attendees)) {
                        $objEvent->cep_participants = implode(',', $attendees);
                    }
                }

                if (\in_array('location_contact', $fieldNames, true)) {
                    $contact = $vevent->getAllContact();
                    $contacts = [];

                    foreach ($contact as $c) {
                        if (!empty($c->getValue())) {
                            $contacts[] = $c->getValue();
                        }
                    }
                    if (!empty($contacts)) {
                        $objEvent->location_contact = implode(',', $contacts);
                    }
                }

                $objEvent->startDate = null;
                $objEvent->startTime = null;
                $objEvent->addTime = false;
                $objEvent->endDate = null;
                $objEvent->endTime = null;
                $timezone = \is_array($tz) ? $tz[1] : null;

                if (!empty($dtstart)) {
                    [$sDate, $timezone] = $this->getDateFromPc($dtstart, $tz[1]);

                    if (!$dtstart->hasParamValue(IcalInterface::DATE)) {
                        $objEvent->addTime = true;
                    } else {
                        $objEvent->addTime = false;
                    }
                    $objEvent->startDate = $sDate->getTimestamp();
                    $objEvent->startTime = $sDate->getTimestamp();
                }
                if (!empty($dtend)) {
                    [$eDate, $timezone] = $this->getDateFromPc($dtend, $tz[1]);

                    if (true === $objEvent->addTime) {
                        $objEvent->endDate = $eDate->getTimestamp();
                        $objEvent->endTime = $eDate->getTimestamp();
                    } else {
                        $endDate = (clone $eDate)->modify('- 1 day')->getTimestamp();
                        $endTime = (clone $eDate)->modify('- 1 second')->getTimestamp();

                        $objEvent->endDate = $endDate;
                        $objEvent->endTime = min($endTime, $endDate);
                    }
                }

                if (0 !== $timeshift) {
                    $objEvent->startDate += $timeshift * 3600;
                    $objEvent->endDate += $timeshift * 3600;
                    $objEvent->startTime += $timeshift * 3600;
                    $objEvent->endTime += $timeshift * 3600;
                }

                if (\is_array($rrule)) {
                    $objEvent->recurring = true;
                    $objEvent->recurrences = \array_key_exists('COUNT', $rrule) ? $rrule['COUNT'] : 0;
                    $repeatEach = [];

                    switch ($rrule['FREQ']) {
                        case 'DAILY':
                            $repeatEach['unit'] = 'days';
                            break;
                        case 'WEEKLY':
                            $repeatEach['unit'] = 'weeks';
                            break;
                        case 'MONTHLY':
                            $repeatEach['unit'] = 'months';
                            break;
                        case 'YEARLY':
                            $repeatEach['unit'] = 'years';
                            break;
                    }

                    $repeatEach['value'] = $rrule['INTERVAL'] ?? 1;
                    $objEvent->repeatEach = serialize($repeatEach);
                    $objEvent->repeatEnd = $this->getRepeatEnd($objEvent, $rrule, $repeatEach, $timezone, $timeshift);

                    if (\in_array('repeatWeekday', $fieldNames, true) && isset($rrule['WKST']) && \is_array($rrule['WKST'])) {
                        $weekdays = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0];
                        $mapWeekdays = static fn (string $value): ?int => $weekdays[$value] ?? null;
                        $objEvent->repeatWeekday = serialize(array_map($mapWeekdays, $rrule['WKST']));
                    }
                }
                $this->handleRecurringExceptions($objEvent, $fieldNames, $vevent, $timezone, $timeshift);

                if (!isset($foundevents[$uid])) {
                    $foundevents[$uid] = 0;
                }
                ++$foundevents[$uid];

                $objEvent->description = $uid;

                if ($foundevents[$uid] <= 1) {
                    if ('' === $objEvent->singleSRC) {
                        $objEvent->singleSRC = null;
                    }

                    $objEvent = $objEvent->save();
                    if (!empty($eventcontent)) {
                        $this->addEventContent($objEvent, $eventcontent);
                    }

                    $this->generateAlias($objEvent);
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function getDateFromPc(Pc $pc, string $tz): array
    {
        if ($pc->hasParamKey(IcalInterface::TZID)) {
            $timezone = $pc->getParams(IcalInterface::TZID);
            $date = $pc->getValue();
        } else {
            if ($pc->getValue()->getTimezone()) {
                $timezone = $pc->getValue()->getTimezone()->getName();
                $date = new \DateTime(
                    $pc->getValue()->format(DateTimeFactory::$YmdHis),
                    $pc->getValue()->getTimezone(),
                );
            } else {
                $timezone = $tz;
                $date = new \DateTime(
                    $pc->getValue()->format(DateTimeFactory::$YmdHis),
                    DateTimeZoneFactory::factory($tz),
                );
            }
        }

        return [$date, $timezone];
    }

    protected function downloadURLToTempFile(string $url, string|null $proxy, string|null $benutzerpw, int|null $port): File|null
    {
        $url = html_entity_decode((string) $url);

        if ($this->isCurlInstalled()) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (!empty($proxy)) {
                curl_setopt($ch, CURLOPT_PROXY, "$proxy");
                if (!empty($benutzerpw)) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$benutzerpw");
                }
                if (!empty($port)) {
                    curl_setopt($ch, CURLOPT_PROXYPORT, "$port");
                }
            }

            if (preg_match('/^https/', $url)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }

            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $content = curl_exec($ch);
            if (false === $content) {
                $content = null;
            } else {
                $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($responseCode >= 400) {
                    System::getContainer()
                        ->get('monolog.logger.contao.general')
                        ->error('Could not download ics file from URL "'.$url.'". Got response code: '.$responseCode)
                    ;

                    $content = null;
                }
            }
            curl_close($ch);
        } else {
            $content = file_get_contents($url);
        }

        if (empty($content)) {
            System::getContainer()
                ->get('monolog.logger.contao.general')
                ->warn('The downloaded ics file from URL "'.$url.'" seems to be empty.')
            ;

            return null;
        }

        $filename = md5(uniqid((string) random_int(0, mt_getrandmax()), true));
        $objFile = new File('system/tmp/'.$filename);
        $objFile->write($content);
        $objFile->close();

        return $objFile;
    }

    /**
     * @param array<mixed> $timezone
     */
    private function importFromWebICS(CalendarModel $objCalendar, Date $startDate, Date $endDate, array $timezone): void
    {
        if (empty($objCalendar->ical_url)) {
            return;
        }

        $cal = new Vcalendar();
        $cal->setMethod(Vcalendar::PUBLISH);
        $cal->setXprop(Vcalendar::X_WR_CALNAME, $objCalendar->title);
        $cal->setXprop(Vcalendar::X_WR_CALDESC, $objCalendar->title);

        /* start parse of local file */
        $file = $this->downloadURLToTempFile($objCalendar->ical_url, $objCalendar->ical_proxy, $objCalendar->ical_bnpw, $objCalendar->ical_port);
        if (null === $file) {
            return;
        }

        try {
            $cal->parse($file->getContent());
        } catch (\Throwable $e) {
            System::getContainer()
                ->get('monolog.logger.contao.general')
                ->error('Could not import ics file from URL "'.$objCalendar->ical_url.'": '.$e->getMessage())
            ;

            return;
        }

        $tz = $cal->getProperty(IcalInterface::X_WR_TIMEZONE);
        if (false === $tz && !empty($tzComponent = $cal->getComponent(IcalInterface::VTIMEZONE))) {
            $tz = $tzComponent->getXprop(IcalInterface::X_LIC_LOCATION);
        }

        if (!\is_array($tz) || '' === $tz[1]) {
            $tz = $timezone;
        }

        $this->importFromIcsFile($cal, $objCalendar, $startDate, $endDate, $tz, $objCalendar->ical_filter_event_title, $objCalendar->ical_pattern_event_title, $objCalendar->ical_replacement_event_title, true);
    }

    private function isCurlInstalled(): bool
    {
        return \in_array('curl', get_loaded_extensions(), true);
    }

    /**
     * @param array<mixed> $rrule
     * @param array<mixed> $repeatEach
     *
     * @throws \Exception
     */
    private function getRepeatEnd(CalendarEventsModel $objEvent, array $rrule, array $repeatEach, string $timezone, int $timeshift = 0): int
    {
        if (($until = $rrule[IcalInterface::UNTIL] ?? null) instanceof \DateTime) {
            // convert UNTIL date to current timezone
            $until = new \DateTime(
                $until->format(DateTimeFactory::$YmdHis),
                DateTimeZoneFactory::factory($timezone),
            );

            $timestamp = $until->getTimestamp();
            if (0 !== $timeshift) {
                $timestamp += $timeshift * 3600;
            }

            return $timestamp;
        }

        if (0 === $objEvent->recurrences) {
            return (int) min(4_294_967_295, PHP_INT_MAX);
        }

        if (isset($repeatEach['unit'], $repeatEach['value'])) {
            $arg = $repeatEach['value'] * $objEvent->recurrences;
            $unit = $repeatEach['unit'];

            $strtotime = '+ '.$arg.' '.$unit;

            return (int) strtotime($strtotime, $objEvent->endTime);
        }

        return 0;
    }

    /**
     * @param array<mixed> $fieldNames
     * @param Vevent       $vevent
     * @param string       $timezone
     * @param int          $timeshift
     */
    private function handleRecurringExceptions(CalendarEventsModel $objEvent, array $fieldNames, $vevent, $timezone, $timeshift): void
    {
        if (
            !\array_key_exists('useExceptions', $fieldNames)
            && !\array_key_exists('repeatExceptions', $fieldNames)
            && !\array_key_exists('exceptionList', $fieldNames)
        ) {
            return;
        }

        $objEvent->useExceptions = 0;
        $objEvent->repeatExceptions = null;
        $objEvent->exceptionList = null;

        $exDates = [];

        while (false !== ($exDateRow = $vevent->getExdate())) {
            foreach ($exDateRow as $exDate) {
                if ($exDate instanceof \DateTime) {
                    // convert UNTIL date to current timezone
                    $exDate = new \DateTime(
                        $exDate->format(DateTimeFactory::$YmdHis),
                        DateTimeZoneFactory::factory($timezone),
                    );
                    $timestamp = $exDate->getTimestamp();
                    if (0 !== $timeshift) {
                        $timestamp += $timeshift * 3600;
                    }
                    $exDates[$timestamp] = [
                        'exception' => $timestamp,
                        'action' => 'hide',
                    ];
                }
            }
        }

        if (empty($exDates)) {
            return;
        }

        $objEvent->useExceptions = true;
        ksort($exDates);
        $objEvent->exceptionList = $exDates;
        $objEvent->repeatExceptions = array_values($exDates);
    }
}
