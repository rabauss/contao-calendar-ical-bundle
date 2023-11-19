<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Classes;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\File;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class CalendarExport extends Backend
{
    public function __construct(
        private readonly Connection $db,
        private readonly InsertTagParser $insertTagParser,
    ) {
        parent::__construct();
    }

    /**
     * Delete old file and generate feed for calendar(s).
     */
    public function generateSubscriptions(CalendarEventsModel|CalendarModel|null $objCalendar = null): void
    {
        if (empty($objCalendar)) {
            return;
        }

        $arrCalendars = [];
        if ($objCalendar instanceof CalendarEventsModel) {
            if (null === $objCalendar = CalendarModel::findById($objCalendar->pid)) {
                return;
            }

            $arrCalendars[] = $objCalendar;
        } elseif ($objCalendar instanceof CalendarModel) {
            $arrCalendars[] = $objCalendar;
        } else {
            $arrCalendars = CalendarModel::findBy(['make_ical=?'], ['1']);
        }

        foreach ($arrCalendars as $calendar) {
            if (empty($calendar->make_ical)) {
                return;
            }

            $this->removeOldSubscriptions($calendar);
            if (false !== $filename = $this->generateFile($calendar)) {
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->info('Generated ical subscription "'.$filename.'.ics"')
                ;
            } else {
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->error('Could not generate ical subscription "'.$filename.'.ics"')
                ;
            }
        }
    }

    /**
     * Remove old ics files from the root directory.
     *
     * @return array<string>
     */
    public function removeOldSubscriptions(CalendarModel|null $objCalendar = null): array
    {
        $arrFeeds = [];
        $useWhitelist = true;
        if (null === $objCalendar) {
            $arrCalendars = CalendarModel::findBy(['make_ical=?'], ['1']);
            $useWhitelist = false;
        } else {
            if (empty($objCalendar->make_ical)) {
                return $arrFeeds;
            }
            $arrCalendars = [$objCalendar];
        }

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $objCalendar) {
                $arrFeeds[] = $objCalendar->ical_alias ?? 'calendar'.$objCalendar->id;
            }
        }

        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';

        // Delete old files
        foreach (Folder::scan($shareDir) as $file) {
            if (is_dir($shareDir.$file)) {
                continue;
            }

            if (\is_array($GLOBALS['TL_CONFIG']['rootFiles']) && \in_array($file, $GLOBALS['TL_CONFIG']['rootFiles'], true)) {
                continue;
            }

            $objFile = new File(StringUtil::stripRootDir($shareDir).'/'.$file);

            if (
                'ics' === $objFile->extension
                && $useWhitelist ? in_array($objFile->filename, $arrFeeds, true) : !\in_array($objFile->filename, $arrFeeds, true)
            ) {
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->error('delete file '.$objFile->filename)
                ;

                $objFile->delete();
            }
        }

        return [];
    }

    /**
     * Generate an XML file and save it to the root directory.
     */
    private function generateFile(CalendarModel $objCalendar): bool|string
    {
        $startdate = !empty($objCalendar->ical_start) ? (int) $objCalendar->ical_start : time();
        $enddate = !empty($objCalendar->ical_end) ? (int) $objCalendar->ical_end : (time() + $GLOBALS['calendar_ical']['endDateTimeDifferenceInDays'] * 24 * 3600);
        $filename = $objCalendar->ical_alias ?? 'calendar'.$objCalendar->id;
        if (
            null !== $ical = $this->getVcalendar($objCalendar, $startdate, $enddate)
        ) {
            $content = $ical->createCalendar();
            $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';
            $objFile = new File(StringUtil::stripRootDir($shareDir).'/'.$filename.'.ics');
            $objFile->write($content);
            $objFile->close();

            return $filename;
        }

        return false;
    }

    private function getVcalendar(CalendarModel $objCalendar, int $intStart, int $intEnd): Vcalendar|null
    {
        $ical = new Vcalendar();
        $ical->setMethod(Vcalendar::PUBLISH);
        $ical->setXprop(Vcalendar::X_WR_CALNAME, $objCalendar->title);
        $ical->setXprop(Vcalendar::X_WR_CALDESC, $objCalendar->ical_description);
        $ical->setXprop(Vcalendar::X_WR_TIMEZONE, Config::get('timeZone'));
        $time = time();

        // Get events of the current period
        $query = <<<'EOQ'
            SELECT *
            FROM   tl_calendar_events
            WHERE  pid=?
              AND  ((startTime>=? AND startTime<=?)
                    OR  (endTime>=? AND endTime<=?)
                    OR (startTime<=? AND endTime>=?)
                    OR (recurring='1' AND (recurrences=0 OR repeatEnd>=?)))
              AND  (start=''
                        OR CAST(start AS UNSIGNED)<?)
              AND  (stop=''
                        OR CAST(stop AS UNSIGNED)>?)
              AND  published='1'
            ORDER BY startTime
            EOQ;

        $objEvents = $this->db
            ->prepare($query)
            ->executeQuery([
                $objCalendar->id,
                $intStart,
                $intEnd,
                $intStart,
                $intEnd,
                $intStart,
                $intEnd,
                $intStart,
                $time,
                $time,
            ])
        ;

        if ($objEvents->rowCount() < 1) {
            return null;
        }

        $arrEvents = $objEvents->fetchAllAssociative();

        foreach ($arrEvents as $arrEvent) {
            $vevent = new Vevent();

            if (!empty($arrEvent['addTime'])) {
                $vevent->setDtstart(date(DateTimeFactory::$YmdTHis, $arrEvent['startTime']), [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                if (!empty($arrEvent['endTime'])) {
                    if ((int) $arrEvent['startTime'] < (int) $arrEvent['endTime']) {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $arrEvent['endTime']),
                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $arrEvent['startTime'] + 60 * 60),
                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                    }
                } else {
                    $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $arrEvent['startTime'] + 60 * 60),
                        [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                }
            } else {
                $startDate = $arrEvent['startDate'] ?? $arrEvent['startTime'];
                $endDate = $arrEvent['endDate'] ?? $arrEvent['endTime'];
                $vevent->setDtstart(date(DateTimeFactory::$Ymd, $startDate), [IcalInterface::VALUE => IcalInterface::DATE]);
                if (!empty($endDate)) {
                    if ((int) $startDate < (int) $endDate) {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $endDate),
                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $startDate + 60 * 60),
                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                    }
                } else {
                    $vevent->setDtend(date(DateTimeFactory::$Ymd, $startDate + 24 * 60 * 60),
                        [IcalInterface::VALUE => IcalInterface::DATE]);
                }
            }

            $vevent->setSummary(html_entity_decode((!empty($objCalendar->ical_prefix) ? $objCalendar->ical_prefix.' ' : '').$arrEvent['title'],
                ENT_QUOTES, 'UTF-8'));
            $vevent->setDescription(html_entity_decode(strip_tags(preg_replace('/<br \\/>/', "\n",
                $this->insertTagParser->replaceInline($arrEvent['teaser']))),
                ENT_QUOTES, 'UTF-8'));

            if (!empty($arrEvent['location'])) {
                $vevent->setLocation(trim(html_entity_decode((string) $arrEvent['location'], ENT_QUOTES, 'UTF-8')));
            }

            if (!empty($arrEvent['cep_participants'])) {
                $attendees = preg_split('/,/', (string) $arrEvent['cep_participants']);
                if (is_countable($attendees) ? \count($attendees) : 0) {
                    foreach ($attendees as $attendee) {
                        $attendee = trim((string) $attendee);
                        if (str_contains($attendee, '@')) {
                            $vevent->setAttendee($attendee);
                        } else {
                            $vevent->setAttendee($attendee, ['CN' => $attendee]);
                        }
                    }
                }
            }

            if (!empty($arrEvent['location_contact'])) {
                $contact = trim((string) $arrEvent['location_contact']);
                $vevent->setContact($contact);
            }

            if ($arrEvent['recurring']) {
                $arrRepeat = StringUtil::deserialize($arrEvent['repeatEach'], true);
                $arg = $arrRepeat['value'];

                $freq = 'YEARLY';

                switch ($arrRepeat['unit']) {
                    case 'days':
                        $freq = 'DAILY';
                        break;
                    case 'weeks':
                        $freq = 'WEEKLY';
                        break;
                    case 'months':
                        $freq = 'MONTHLY';
                        break;
                    case 'years':
                        $freq = 'YEARLY';
                        break;
                }

                $rrule = ['FREQ' => $freq];

                if ($arrEvent['recurrences'] > 0) {
                    $rrule['count'] = $arrEvent['recurrences'];
                }

                if ($arg > 1) {
                    $rrule['INTERVAL'] = $arg;
                }

                $vevent->setRrule($rrule);
            }

            /*
            * begin module event_recurrences handling
            */
            if (!empty($arrEvent['repeatExecptions'])) {
                $arrSkipDates = StringUtil::deserialize($arrEvent['repeatExecptions'], true);

                foreach ($arrSkipDates as $skipDate) {
                    $exTStamp = strtotime((string) $skipDate);
                    $exdate =
                        \DateTime::createFromFormat(DateTimeFactory::$YmdHis,
                            date('Y', $exTStamp).
                            date('m', $exTStamp).
                            date('d', $exTStamp).
                            date('H', $arrEvent['startTime']).
                            date('i', $arrEvent['startTime']).
                            date('s', $arrEvent['startTime']),
                        );
                    $vevent->setExdate($exdate);
                }
            }
            /*
            * end module event_recurrences handling
            */

            $ical->setComponent($vevent);
        }

        return $ical;
    }
}
