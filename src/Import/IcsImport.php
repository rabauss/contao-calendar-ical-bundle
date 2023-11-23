<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Import;

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
        if (!empty($objCalendar->ical_source)) {
            $arrLastchange = $this->db->executeQuery('SELECT MAX(tstamp) lastchange FROM tl_calendar_events WHERE pid = ?', [$objCalendar->id])
                ->fetchAssociative()
            ;

            $last_change = $arrLastchange['lastchange'];

            if (0 === $last_change) {
                $last_change = $objCalendar->tstamp;
            }

            if (((time() - $last_change > $objCalendar->ical_cache) && (1 !== $objCalendar->ical_importing || (time() - $objCalendar->tstamp) > 120)) || $force_import) {
                $this->db->update('tl_calendar', ['tstamp' => time(), 'ical_importing' => '1'], ['id' => $objCalendar->id]);

                // create new from ical file
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->error('Reload iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.'): Triggered by '.time().' - '.$last_change.' = '.(time() - $arrLastchange['lastchange']).' > '.$objCalendar->ical_cache)
                ;

                $startDate = !empty((string) $objCalendar->ical_source_start) ?
                    new Date($objCalendar->ical_source_start, Config::get('dateFormat')) :
                    new Date(time(), Config::get('dateFormat'));
                $endDate = !empty((string) $objCalendar->ical_source_end) ?
                    new Date($objCalendar->ical_source_end, Config::get('dateFormat')) :
                    new Date(time() + $this->defaultEndTimeDifference * 24 * 3600, Config::get('dateFormat'));
                $tz = [$objCalendar->ical_timezone, $objCalendar->ical_timezone];
                $this->importFromWebICS($objCalendar, $startDate, $endDate, $tz);
                $this->db->update('tl_calendar', ['tstamp' => time(), 'ical_importing' => ''], ['id' => $objCalendar->id]);
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
        $arrFields = [];
        $defaultFields = [];

        foreach ($fields as $field) {
            if ('id' !== $field->getName()) {
                $fieldNames[] = $field->getName();
            }
        }

        // Get all default values for new entries
        foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] as $k => $v) {
            if (isset($v['default'])) {
                $defaultFields[$k] = \is_array($v['default']) ? serialize($v['default']) : $v['default'];
            }
        }

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
            foreach ($eventArray as $vevent) {
                /** @var Vevent $vevent */
                $arrFields = $defaultFields;

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

                $arrFields['tstamp'] = time();
                $arrFields['pid'] = $objCalendar->id;
                $arrFields['published'] = 1;
                $arrFields['author'] = BackendUser::getInstance()->id ?: 0;

                $title = $summary;
                if (!empty($patternEventTitle) && !empty($replacementEventTitle)) {
                    $title = preg_replace($patternEventTitle, $replacementEventTitle, $summary);
                }

                // set values from vevent
                $arrFields['title'] = !empty($title) ? $title : $summary;
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
                        $arrFields['location'] = $location;
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
                        $arrFields['cep_participants'] = implode(',', $attendees);
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
                        $arrFields['location_contact'] = implode(',', $contacts);
                    }
                }

                $arrFields['startDate'] = 0;
                $arrFields['startTime'] = 0;
                $arrFields['addTime'] = '';
                $arrFields['endDate'] = 0;
                $arrFields['endTime'] = 0;
                $timezone = \is_array($tz) ? $tz[1] : null;

                if (!empty($dtstart)) {
                    [$sDate, $timezone] = $this->getDateFromPc($dtstart, $tz[1]);

                    if (!$dtstart->hasParamValue(IcalInterface::DATE)) {
                        $arrFields['addTime'] = 1;
                    } else {
                        $arrFields['addTime'] = 0;
                    }
                    $arrFields['startDate'] = $sDate->getTimestamp();
                    $arrFields['startTime'] = $sDate->getTimestamp();
                }
                if (!empty($dtend)) {
                    [$eDate, $timezone] = $this->getDateFromPc($dtend, $tz[1]);

                    if (1 === $arrFields['addTime']) {
                        $arrFields['endDate'] = $eDate->getTimestamp();
                        $arrFields['endTime'] = $eDate->getTimestamp();
                    } else {
                        $endDate = (clone $eDate)->modify('- 1 day')->getTimestamp();
                        $endTime = (clone $eDate)->modify('- 1 second')->getTimestamp();

                        $arrFields['endDate'] = $endDate;
                        $arrFields['endTime'] = min($endTime, $endDate);
                    }
                }

                if (0 !== $timeshift) {
                    $arrFields['startDate'] += $timeshift * 3600;
                    $arrFields['endDate'] += $timeshift * 3600;
                    $arrFields['startTime'] += $timeshift * 3600;
                    $arrFields['endTime'] += $timeshift * 3600;
                }

                if (\is_array($rrule)) {
                    $arrFields['recurring'] = 1;
                    $arrFields['recurrences'] = \array_key_exists('COUNT', $rrule) ? $rrule['COUNT'] : 0;
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
                    $arrFields['repeatEach'] = serialize($repeatEach);
                    $arrFields['repeatEnd'] = $this->getRepeatEnd($arrFields, $rrule, $repeatEach, $timezone, $timeshift);

                    if (isset($rrule['WKST']) && \is_array($rrule['WKST'])) {
                        $weekdays = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0];
                        $mapWeekdays = static fn (string $value): ?int => $weekdays[$value] ?? null;
                        $arrFields['repeatWeekday'] = serialize(array_map($mapWeekdays, $rrule['WKST']));
                    }
                }
                $this->handleRecurringExceptions($arrFields, $vevent, $timezone, $timeshift);

                if (!isset($foundevents[$uid])) {
                    $foundevents[$uid] = 0;
                }
                ++$foundevents[$uid];

                $arrFields['description'] = $uid;

                if ($foundevents[$uid] <= 1) {
                    if (\array_key_exists('singleSRC', $arrFields) && '' === $arrFields['singleSRC']) {
                        $arrFields['singleSRC'] = null;
                    }

                    if ($this->db->insert('tl_calendar_events', $arrFields)) {
                        $insertID = (int) $this->db->lastInsertId();

                        if (\count($eventcontent)) {
                            $step = 128;

                            foreach ($eventcontent as $content) {
                                $cm = new ContentModel();
                                $cm->tstamp = time();
                                $cm->pid = $insertID;
                                $cm->ptable = 'tl_calendar_events';
                                $cm->sorting = $step;
                                $step *= 2;
                                $cm->type = 'text';
                                $cm->text = $content;
                                $cm->save();
                            }
                        }

                        $alias = $this->generateAlias($arrFields['title'], $insertID, $objCalendar->id);
                        $this->db->update('tl_calendar_events', ['alias' => $alias], ['id' => $insertID]);
                    }
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
     * @param array<mixed> $arrFields
     * @param array<mixed> $rrule
     * @param array<mixed> $repeatEach
     *
     * @throws \Exception
     */
    private function getRepeatEnd(array $arrFields, array $rrule, array $repeatEach, string $timezone, int $timeshift = 0): int
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

        if (0 === (int) $arrFields['recurrences']) {
            return (int) min(4_294_967_295, PHP_INT_MAX);
        }

        if (isset($repeatEach['unit'], $repeatEach['value'])) {
            $arg = $repeatEach['value'] * $arrFields['recurrences'];
            $unit = $repeatEach['unit'];

            $strtotime = '+ '.$arg.' '.$unit;

            return (int) strtotime($strtotime, $arrFields['endTime']);
        }

        return 0;
    }

    /**
     * @param array  $arrFields
     * @param Vevent $vevent
     * @param string $timezone
     * @param int    $timeshift
     */
    private function handleRecurringExceptions(&$arrFields, $vevent, $timezone, $timeshift): void
    {
        if (
            !\array_key_exists('useExceptions', $arrFields)
            && !\array_key_exists('repeatExceptions', $arrFields)
            && !\array_key_exists('exceptionList', $arrFields)
        ) {
            return;
        }

        $arrFields['useExceptions'] = 0;
        $arrFields['repeatExceptions'] = null;
        $arrFields['exceptionList'] = null;

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

        $arrFields['useExceptions'] = 1;
        ksort($exDates);
        $arrFields['exceptionList'] = $exDates;
        $arrFields['repeatExceptions'] = array_values($exDates);
    }
}
