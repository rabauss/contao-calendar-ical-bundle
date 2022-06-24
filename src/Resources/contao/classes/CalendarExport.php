<?php

/*
 * This file is part of the Contao Calendar iCal Bundle.
 *
 * (c) Helmut Schottmüller 2009-2013 <https://github.com/hschottm>
 * (c) Daniel Kiesel 2017 <https://github.com/iCodr8>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Contao;

use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class CalendarExport extends \Backend
{
    /**
     * Update a particular RSS feed
     * @param integer
     */
    public function exportCalendar($intId)
    {
        $objCalendar = $this->Database->prepare("SELECT * FROM tl_calendar WHERE id=? AND make_ical=?")
            ->limit(1)
            ->execute($intId, 1);

        if ($objCalendar->numRows < 1) {
            return;
        }

        $filename = strlen($objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar' . $objCalendar->id;

        // Delete ics file
        if (\Input::get('act') == 'delete') {
            $this->import('Files');
            $this->Files->delete($filename . '.ics');
        } // Update ics file
        else {
            $this->generateSubscriptions();
        }
    }


    /**
     * Delete old files and generate all feeds
     */
    public function generateSubscriptions()
    {
        $this->removeOldSubscriptions();
        $objCalendar = $this->Database->prepare("SELECT * FROM tl_calendar WHERE make_ical=?")->execute(1);

        while ($objCalendar->next()) {
            $filename = strlen($objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar' . $objCalendar->id;

            $this->generateFiles($objCalendar->row());
            \System::log('Generated ical subscription "' . $filename . '.ics"', __METHOD__, TL_CRON);
        }
    }


    /**
     * Generate an XML file and save it to the root directory
     * @param array
     */
    protected function generateFiles($arrArchive)
    {
        $this->arrEvents = array();

        $startdate = (strlen($arrArchive['ical_start'])) ? $arrArchive['ical_start'] : time();
        $enddate = (strlen($arrArchive['ical_end'])) ? $arrArchive['ical_end'] : time() + $GLOBALS['calendar_ical']['endDateTimeDifferenceInDays'] * 24 * 3600;
        $filename = strlen($arrArchive['ical_alias']) ? $arrArchive['ical_alias'] : 'calendar' . $arrArchive['id'];
        $ical = $this->getAllEvents(array($arrArchive['id']), $startdate, $enddate, $arrArchive['title'],
            $arrArchive['ical_description'], $filename, $arrArchive['ical_prefix']);
        $content = $ical->createCalendar();
        $objFile = new \File('web/share/' . $filename . '.ics');
        $objFile->write($content);
        $objFile->close();
    }

    /**
     * Remove old ics files from the root directory
     */
    public function removeOldSubscriptions()
    {
        $arrFeeds = array();
        $objFeeds = $this->Database->prepare("SELECT id, ical_alias FROM tl_calendar WHERE make_ical=?")->execute(1);

        while ($objFeeds->next()) {
            $arrFeeds[] = strlen($objFeeds->ical_alias) ? $objFeeds->ical_alias : 'calendar' . $objFeeds->id;
        }

        // Make sure dcaconfig.php is loaded TEST
        // include(TL_ROOT . '/system/config/dcaconfig.php');

        // Delete old files
        foreach (scan(TL_ROOT . '/web/share') as $file) {
            if (is_dir(TL_ROOT . '/web/share/' . $file)) {
                continue;
            }

            if (is_array($GLOBALS['TL_CONFIG']['rootFiles']) && in_array($file, $GLOBALS['TL_CONFIG']['rootFiles'])) {
                continue;
            }

            $objFile = new \File('web/share/' . $file);

            if (
                $objFile->extension === 'ics'
                && !in_array($objFile->filename, $arrFeeds)
                && !preg_match('/^sitemap/i', $objFile->filename)
            ) {
                \System::log('file ' . $objFile->filename, __METHOD__, TL_CRON);
                $objFile->delete();
            }
        }

        return array();
    }

    protected function getAllEvents(
        $arrCalendars,
        $intStart,
        $intEnd,
        $title,
        $description,
        $filename = "",
        $title_prefix
    ) {
        if (!is_array($arrCalendars)) {
            return array();
        }

        $ical = new Vcalendar();
        $ical->setMethod('PUBLISH');
        $ical->setXprop("x-wr-calname", $title);
        $ical->setXprop("X-WR-CALDESC", $description);
        $ical->setXprop("X-WR-TIMEZONE", $GLOBALS['TL_CONFIG']['timeZone']);
        $time = time();

        foreach ($arrCalendars as $id) {
            // Get events of the current period
            $objEvents = $this->Database
                ->prepare("SELECT *, (SELECT title FROM tl_calendar WHERE id=?) AS calendar FROM tl_calendar_events WHERE pid=? AND ((startTime>=? AND startTime<=?) OR (endTime>=? AND endTime<=?) OR (startTime<=? AND endTime>=?) OR (recurring=1 AND (recurrences=0 OR repeatEnd>=?))) AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1 ORDER BY startTime")
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
                    $time
                );

            if ($objEvents->numRows < 1) {
                continue;
            }

            while ($objEvents->next()) {
                $vevent = new Vevent();

                if ($objEvents->addTime) {
                    $vevent->setDtstart(date(DateTimeFactory::$YmdTHis, $objEvents->startTime), [ Vcalendar::VALUE => Vcalendar::DATE_TIME ]);
                    if (!strlen($objEvents->ignoreEndTime) || $objEvents->ignoreEndTime == 0 || !strlen($objEvents->endDate) || $objEvents->endDate == 0) {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvents->endTime), [Vcalendar::VALUE => Vcalendar::DATE_TIME]);
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvents->startTime), [ Vcalendar::VALUE => Vcalendar::DATE_TIME ]);
                    }
                } else {
                    $vevent->setDtstart(date(DateTimeFactory::$Ymd, $objEvents->startDate), [ Vcalendar::VALUE => Vcalendar::DATE ]);
                    if (!strlen($objEvents->endDate) || $objEvents->endDate == 0) {
                        $vevent->setDtend(date(DateTimeFactory::$Ymd, $objEvents->startDate + 24 * 60 * 60),
                                          [ Vcalendar::VALUE => Vcalendar::DATE ]);
                    } else {
                        $vevent->setDtend(date(DateTimeFactory::$Ymd, $objEvents->endDate + 24 * 60 * 60),
                                          [ Vcalendar::VALUE => Vcalendar::DATE ]);
                    }
                }

                $vevent->setSummary(html_entity_decode((strlen($title_prefix) ? $title_prefix . " " : "") . $objEvents->title,
                        ENT_QUOTES, 'UTF-8'));
                $vevent->setDescription(html_entity_decode(strip_tags(preg_replace('/<br \\/>/', "\n",
                    $this->replaceInsertTags($objEvents->teaser))), ENT_QUOTES, 'UTF-8'));

                if ($objEvents->cep_location) {
                    $vevent->setDescription(trim(html_entity_decode($objEvents->cep_location, ENT_QUOTES, 'UTF-8')));
                }

                if ($objEvents->cep_participants) {
                    $attendees = preg_split("/,/", $objEvents->cep_participants);
                    if (count($attendees)) {
                        foreach ($attendees as $attendee) {
                            $attendee = trim($attendee);
                            if (strpos($attendee, "@") !== false) {
                                $vevent->setAttendee($attendee);
                            } else {
                                $vevent->setAttendee($attendee, array('CN' => $attendee));
                            }
                        }
                    }
                }

                if ($objEvents->cep_contact) {
                    $contact = trim($objEvents->cep_contact);
                    $vevent->setContact($contact);
                }

                if ($objEvents->recurring) {
                    $count = 0;
                    $arrRepeat = deserialize($objEvents->repeatEach);
                    $arg = $arrRepeat['value'];
                    $unit = $arrRepeat['unit'];

                    if ($arg == 1) {
                        $unit = substr($unit, 0, -1);
                    }

                    $strtotime = '+ ' . $arg . ' ' . $unit;
                    $newstart = strtotime($strtotime, $objEvents->startTime);
                    $newend = strtotime($strtotime, $objEvents->endTime);
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

                    $rrule = array('FREQ' => $freq);

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
                    $arrSkipDates = deserialize($objEvents->repeatExecptions);
                    foreach ($arrSkipDates as $skipDate) {
                        $exTStamp = strtotime($skipDate);
                        $exdate = array(
                            date(DateTimeFactory::$YmdHis,
                                date('Y', $exTStamp) .
                                date('m', $exTStamp) .
                                date('d', $exTStamp) .
                                date('H', $objEvents->startTime) .
                                date('i', $objEvents->startTime) .
                                date('s', $objEvents->startTime)
                            )
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
