<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Craffft\ContaoCalendarICalBundle\Classes;

use Contao\Backend;
use Contao\File;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class CalendarExport extends Backend
{
    /**
     * Update a particular RSS feed.
     *
     * @param int $intId
     */
    public function exportCalendar($intId): void
    {
        $objCalendar = $this->Database->prepare('SELECT * FROM tl_calendar WHERE id=? AND make_ical=?')
            ->limit(1)
            ->execute($intId, 1)
        ;

        if ($objCalendar->numRows < 1) {
            return;
        }

        $filename = \strlen((string) $objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar'.$objCalendar->id;

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
        $objCalendar = $this->Database->prepare('SELECT * FROM tl_calendar WHERE make_ical=?')->execute(1);

        while ($objCalendar->next()) {
            $filename = \strlen((string) $objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar'.$objCalendar->id;

            $this->generateFiles($objCalendar->row());
            System::log('Generated ical subscription "'.$filename.'.ics"', __METHOD__, TL_CRON);
        }
    }

    /**
     * Remove old ics files from the root directory.
     */
    public function removeOldSubscriptions()
    {
        $arrFeeds = [];
        $objFeeds = $this->Database->prepare('SELECT id, ical_alias FROM tl_calendar WHERE make_ical=?')->execute(1);

        while ($objFeeds->next()) {
            $arrFeeds[] = \strlen((string) $objFeeds->ical_alias) ? $objFeeds->ical_alias : 'calendar'.$objFeeds->id;
        }

        // Make sure dcaconfig.php is loaded TEST
        // include(TL_ROOT . '/system/config/dcaconfig.php');

        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';

        // Delete old files
        foreach (\Contao\Folder::scan($shareDir) as $file) {
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
                System::log('file '.$objFile->filename, __METHOD__, TL_CRON);
                $objFile->delete();
            }
        }

        return [];
    }

    /**
     * Generate an XML file and save it to the root directory.
     *
     * @param array $arrArchive
     */
    protected function generateFiles($arrArchive): void
    {
        $this->arrEvents = [];

        $startdate = \strlen((string) $arrArchive['ical_start']) ? $arrArchive['ical_start'] : time();
        $enddate = \strlen((string) $arrArchive['ical_end']) ? $arrArchive['ical_end'] : time() + $GLOBALS['calendar_ical']['endDateTimeDifferenceInDays'] * 24 * 3600;
        $filename = \strlen((string) $arrArchive['ical_alias']) ? $arrArchive['ical_alias'] : 'calendar'.$arrArchive['id'];
        $ical = $this->getAllEvents([$arrArchive['id']], $startdate, $enddate, $arrArchive['title'],
            $arrArchive['ical_description'], $filename, $arrArchive['ical_prefix']);
        $content = $ical->createCalendar();
        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';
        $objFile = new File(StringUtil::stripRootDir($shareDir).'/'.$filename.'.ics');
        $objFile->write($content);
        $objFile->close();
    }

    protected function getAllEvents($arrCalendars, $intStart, $intEnd, $title, $description, $filename, $title_prefix)
    {
        if (!\is_array($arrCalendars)) {
            return [];
        }

        $ical = new Vcalendar();
        $ical->setMethod(Vcalendar::PUBLISH);
        $ical->setXprop(Vcalendar::X_WR_CALNAME, $title);
        $ical->setXprop(Vcalendar::X_WR_CALDESC, $description);
        $ical->setXprop(Vcalendar::X_WR_TIMEZONE, $GLOBALS['TL_CONFIG']['timeZone']);
        $time = time();

        foreach ($arrCalendars as $id) {
            // Get events of the current period
            $objEvents = $this->Database
                ->prepare("SELECT *, (SELECT title FROM tl_calendar WHERE id=?) AS calendar FROM tl_calendar_events WHERE pid=? AND ((startTime>=? AND startTime<=?) OR (endTime>=? AND endTime<=?) OR (startTime<=? AND endTime>=?) OR (recurring='1' AND (recurrences=0 OR repeatEnd>=?))) AND (start='' OR CAST(start AS UNSIGNED)<?) AND (stop='' OR CAST(stop AS UNSIGNED)>?) AND published='1' ORDER BY startTime")
                ->execute(
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
                )
            ;

            if ($objEvents->numRows < 1) {
                continue;
            }

            while ($objEvents->next()) {
                $vevent = new Vevent();

                if ($objEvents->addTime) {
                    $vevent->setDtstart(date(DateTimeFactory::$YmdTHis, $objEvents->startTime), [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                    if (!\strlen((string) $objEvents->ignoreEndTime) || 0 === $objEvents->ignoreEndTime) {
                        if ($objEvents->startTime < $objEvents->endTime) {
                            $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvents->endTime),
                                [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                        } else {
                            $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvents->startTime + 60 * 60),
                                [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                        }
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvents->startTime),
                            [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                    }
                } else {
                    $vevent->setDtstart(date(DateTimeFactory::$Ymd, $objEvents->startDate), [Vcalendar::VALUE => Vcalendar::DATE]);
                    if (!\strlen((string) $objEvents->endDate) || 0 === $objEvents->endDate) {
                        $vevent->setDtend(date(DateTimeFactory::$Ymd, $objEvents->startDate + 24 * 60 * 60),
                            [Vcalendar::VALUE => Vcalendar::DATE]);
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$Ymd, $objEvents->endDate + 24 * 60 * 60),
                            [Vcalendar::VALUE => Vcalendar::DATE]);
                    }
                }

                $vevent->setSummary(html_entity_decode((\strlen((string) $title_prefix) ? $title_prefix.' ' : '').$objEvents->title,
                    ENT_QUOTES, 'UTF-8'));
                $vevent->setDescription(html_entity_decode(strip_tags(preg_replace('/<br \\/>/', "\n",
                    (string) $this->replaceInsertTags($objEvents->teaser))),
                    ENT_QUOTES, 'UTF-8'));

                if (!empty($objEvents->location)) {
                    $vevent->setLocation(trim(html_entity_decode((string) $objEvents->location, ENT_QUOTES, 'UTF-8')));
                }

                if (!empty($objEvents->cep_participants)) {
                    $attendees = preg_split('/,/', (string) $objEvents->cep_participants);
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

                if (!empty($objEvents->location_contact)) {
                    $contact = trim((string) $objEvents->location_contact);
                    $vevent->setContact($contact);
                }

                if ($objEvents->recurring) {
                    $arrRepeat = StringUtil::deserialize($objEvents->repeatEach, true);
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

                    if ($objEvents->recurrences > 0) {
                        $rrule['count'] = $objEvents->recurrences;
                    }

                    if ($arg > 1) {
                        $rrule['INTERVAL'] = $arg;
                    }

                    $vevent->setRrule($rrule);
                }

                /*
                * begin module event_recurrences handling
                */
                if ($objEvents->repeatExecptions) {
                    $arrSkipDates = StringUtil::deserialize($objEvents->repeatExecptions, true);

                    foreach ($arrSkipDates as $skipDate) {
                        $exTStamp = strtotime((string) $skipDate);
                        $exdate = [
                            date(DateTimeFactory::$YmdHis,
                                date('Y', $exTStamp).
                                date('m', $exTStamp).
                                date('d', $exTStamp).
                                date('H', $objEvents->startTime).
                                date('i', $objEvents->startTime).
                                date('s', $objEvents->startTime),
                            ),
                        ];
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
