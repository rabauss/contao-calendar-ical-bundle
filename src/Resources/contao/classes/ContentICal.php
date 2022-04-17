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

class ContentICal extends ContentElement
{
    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'ce_ical';
    protected $strTitle = "";
    protected $ical = null;

    /**
     * Return if the file does not exist
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE') {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ICAL ###';

            return $objTemplate->parse();
        }

        $this->loadLanguageFile('tl_content');
        $this->strTitle = strlen($this->linkTitle) ? $this->linkTitle : $GLOBALS['TL_LANG']['tl_content']['ical_title'];

        if (strlen(\Input::get('ical'))) {
            $startdate = (strlen($this->ical_start)) ? $this->ical_start : time();
            $enddate = (strlen($this->ical_end)) ? $this->ical_end : time() + 365 * 24 * 3600;
            $this->getAllEvents(preg_split('/,/', \Input::get('ical')), $startdate, $enddate);
            $this->ical->returnCalendar(); // redirect calendar file to browser

            return;
        }

        $intStart = (strlen($this->ical_start)) ? $this->ical_start : time();
        $intEnd = (strlen($this->ical_end)) ? $this->ical_end : time() + 365 * 24 * 3600;
        $time = time();
        $nrOfCalendars = 0;
        $arrcalendars = deserialize($this->ical_calendar, true);

        if (is_array($arrcalendars)) {
            foreach ($arrcalendars as $id) {
                $objEvents = $this->Database->prepare("SELECT *, (SELECT title FROM tl_calendar WHERE id=?) AS calendar FROM tl_calendar_events WHERE pid=? AND ((startTime>=? AND startTime<=?) OR (endTime>=? AND endTime<=?) OR (startTime<=? AND endTime>=?) OR (recurring=1 AND (recurrences=0 OR repeatEnd>=?)))" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" : "") . " ORDER BY startTime")
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
                $nrOfCalendars += $objEvents->numRows;
            }
        }

        if ($nrOfCalendars < 1) {
            return;
        }

        return parent::generate();
    }

    /**
     * Generate content element
     */
    protected function compile()
    {
        $src = \Environment::get('request');
        $this->Template->link = $this->strTitle;
        $arrCalendars = deserialize($this->ical_calendar, true);
        $this->Template->href = $this->addToUrl("ical=" .
                                                join(',', $arrCalendars) . "&title=" . urlencode($this->strTitle));
        $this->Template->title = $GLOBALS['TL_LANG']['tl_content']['ical_title'];
    }

    /**
     * Get all events of a certain period
     * @param array
     * @param integer
     * @param integer
     */
    protected function getAllEvents($arrCalendars, $intStart, $intEnd)
    {
        if (!is_array($arrCalendars)) {
            return array();
        }

        $this->ical = new Vcalendar();
        $this->ical->setMethod(Vcalendar::PUBLISH);
        $this->ical->setXprop(Vcalendar::X_WR_CALNAME,
            (strlen(\Input::get('title'))) ? \Input::get('title') : $this->strTitle);
        $this->ical->setXprop(Vcalendar::X_WR_CALDESC,
            (strlen(\Input::get('title'))) ? \Input::get('title') : $this->strTitle);
        $this->ical->setXprop(Vcalendar::X_WR_TIMEZONE, $GLOBALS['TL_CONFIG']['timeZone']);
        $time = time();

        foreach ($arrCalendars as $id) {
            // Get events of the current period
            $objEvents = $this->Database->prepare("SELECT *, (SELECT title FROM tl_calendar WHERE id=?) AS calendar, (SELECT ical_prefix FROM tl_calendar WHERE id=?) AS ical_prefix FROM tl_calendar_events WHERE pid=? AND ((startTime>=? AND startTime<=?) OR (endTime>=? AND endTime<=?) OR (startTime<=? AND endTime>=?) OR (recurring=1 AND (recurrences=0 OR repeatEnd>=?)))" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" : "") . " ORDER BY startTime")
                ->execute($id, $id, $id, $intStart, $intEnd, $intStart, $intEnd, $intStart, $intEnd, $intStart, $time,
                    $time);

            if ($objEvents->numRows < 1) {
                continue;
            }

            while ($objEvents->next()) {
                $vevent = new Vevent();

                if ($objEvents->addTime) {
                    $vevent->setDtstart(date(DateTimeFactory::$YmdTHis, $objEvents->startTime), [ Vcalendar::VALUE => Vcalendar::DATE_TIME ]);
                    $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvents->endTime), [ Vcalendar::VALUE => Vcalendar::DATE_TIME ]);
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

                $ical_prefix = (strlen($this->ical_prefix)) ? $this->ical_prefix : $objEvents->ical_prefix;
                $vevent->setSummary(html_entity_decode((strlen($ical_prefix) ? $ical_prefix . " " : "") . $objEvents->title,
                    ENT_QUOTES, 'UTF-8'));
                $vevent->setDescription(html_entity_decode(strip_tags(preg_replace('/<br \\/>/', "\n",
                    $this->replaceInsertTags($objEvents->teaser))), ENT_QUOTES, 'UTF-8'));

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

                $this->ical->setComponent($vevent);
            }
        }
    }
}

