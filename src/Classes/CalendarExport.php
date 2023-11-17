<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarICalBundle\Classes;

use Contao\Backend;
use Contao\CalendarModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\File;
use Contao\Folder;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class CalendarExport extends Backend
{
    public function __construct(
        private readonly Connection $db,
        private readonly InsertTagParser $insertTagParser,
    ) {
    }

    /**
     * Update a particular RSS feed.
     *
     * @param int $intId
     */
    public function exportCalendar($intId): void
    {
        $objCalendar = CalendarModel::findById($intId);

        if (empty($objCalendar) || empty($objCalendar->make_ical)) {
            return;
        }

        $filename = !empty($objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar'.$objCalendar->id;

        // Delete ics file
        if ('delete' === Input::get('act')) {
            $this->import('Files');
            $this->Files->delete($filename.'.ics');
        } // Update ics file
        else {
            $this->generateSubscriptions();
        }
    }

    /**
     * Delete old files and generate all feeds.
     */
    public function generateSubscriptions(): void
    {
        $this->removeOldSubscriptions();
        $arrCalendars = CalendarModel::findBy(['make_ical=?'], ['1']);

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $objCalendar) {
                $filename = !empty($objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar'.$objCalendar->id;

                $this->generateFiles($objCalendar->row());
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->info('Generated ical subscription "'.$filename.'.ics"')
                ;
            }
        }
    }

    /**
     * Remove old ics files from the root directory.
     *
     * @return array<string>
     */
    public function removeOldSubscriptions(): array
    {
        $arrFeeds = [];
        $arrCalendars = CalendarModel::findBy(['make_ical=?'], ['1']);

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $objCalendar) {
                $arrFeeds[] = !empty($objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar'.$objCalendar->id;
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
                && !\in_array($objFile->filename, $arrFeeds, true)
                && !preg_match('/^sitemap/i', $objFile->filename)
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
     *
     * @param array<mixed> $arrArchive
     */
    public function generateFiles(array $arrArchive): void
    {
        $startdate = !empty($arrArchive['ical_start']) ? $arrArchive['ical_start'] : time();
        $enddate = !empty($arrArchive['ical_end']) ? $arrArchive['ical_end'] : time() + $GLOBALS['calendar_ical']['endDateTimeDifferenceInDays'] * 24 * 3600;
        $filename = !empty($arrArchive['ical_alias']) ? $arrArchive['ical_alias'] : 'calendar'.$arrArchive['id'];
        if (
            null !== $ical = $this->getVcalendar([$arrArchive['id']], $startdate, $enddate, $arrArchive['title'],
                $arrArchive['ical_description'], $filename, $arrArchive['ical_prefix'])
        ) {
            $content = $ical->createCalendar();
            $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';
            $objFile = new File(StringUtil::stripRootDir($shareDir).'/'.$filename.'.ics');
            $objFile->write($content);
            $objFile->close();
        }
    }

    /**
     * @param array<mixed> $arrCalendars
     */
    protected function getVcalendar(array $arrCalendars, int $intStart, int $intEnd, string $title, string $description, string $filename, string $title_prefix): Vcalendar|null
    {
        if (!\is_array($arrCalendars)) {
            return null;
        }

        $ical = new Vcalendar();
        $ical->setMethod(Vcalendar::PUBLISH);
        $ical->setXprop(Vcalendar::X_WR_CALNAME, $title);
        $ical->setXprop(Vcalendar::X_WR_CALDESC, $description);
        $ical->setXprop(Vcalendar::X_WR_TIMEZONE, $GLOBALS['TL_CONFIG']['timeZone']);
        $time = time();

        foreach ($arrCalendars as $id) {
            // Get events of the current period
            $objEvents = $this->db
                ->prepare("SELECT *, (SELECT title FROM tl_calendar WHERE id=?) AS calendar FROM tl_calendar_events WHERE pid=? AND ((startTime>=? AND startTime<=?) OR (endTime>=? AND endTime<=?) OR (startTime<=? AND endTime>=?) OR (recurring='1' AND (recurrences=0 OR repeatEnd>=?))) AND (start='' OR CAST(start AS UNSIGNED)<?) AND (stop='' OR CAST(stop AS UNSIGNED)>?) AND published='1' ORDER BY startTime")
                ->executeQuery([
                    $id,
                    $id,
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
                continue;
            }

            $arrEvents = $objEvents->fetchAllAssociative();

            foreach ($arrEvents as $arrEvent) {
                $vevent = new Vevent();

                if ($arrEvent['addTime']) {
                    $vevent->setDtstart(date(DateTimeFactory::$YmdTHis, $arrEvent['startTime']), [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                    if (!empty($arrEvent['ignoreEndTime'])) {
                        if ((int) $arrEvent['startTime'] < (int) $arrEvent['endTime']) {
                            $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $arrEvent['endTime']),
                                [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                        } else {
                            $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $arrEvent['startTime'] + 60 * 60),
                                [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                        }
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $arrEvent['startTime']),
                            [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                    }
                } else {
                    $vevent->setDtstart(date(DateTimeFactory::$Ymd, $arrEvent['startDate']), [Vcalendar::VALUE => Vcalendar::DATE]);
                    if (!empty($arrEvent['endDate'])) {
                        $vevent->setDtend(date(DateTimeFactory::$Ymd, $arrEvent['startDate'] + 24 * 60 * 60),
                            [Vcalendar::VALUE => Vcalendar::DATE]);
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$Ymd, $arrEvent['endDate'] + 24 * 60 * 60),
                            [Vcalendar::VALUE => Vcalendar::DATE]);
                    }
                }

                $vevent->setSummary(html_entity_decode((!empty($title_prefix) ? $title_prefix.' ' : '').$arrEvent['title'],
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
                if ($arrEvent['repeatExecptions']) {
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
        }

        return $ical;
    }
}
