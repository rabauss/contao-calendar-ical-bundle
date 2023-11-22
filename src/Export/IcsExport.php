<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Export;

use Contao\Backend;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Model\Collection;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class IcsExport extends Backend
{
    public function __construct(
        private readonly Connection $db,
        private readonly InsertTagParser $insertTagParser,
    ) {
        parent::__construct();
    }

    /**
     * @param Collection<CalendarModel>|array<CalendarModel> $arrCalendars
     */
    public function getVcalendar(Collection|array $arrCalendars, int $intStart, int $intEnd, string|null $title = null, string|null $description = null, string|null $prefix = null): Vcalendar|null
    {
        $objCalendar = null;
        if (empty($arrCalendars)) {
            return null;
        }

        $ical = new Vcalendar();
        $ical->setMethod(IcalInterface::PUBLISH);
        $ical->setXprop(IcalInterface::X_WR_CALNAME, $title ?? reset($arrCalendars)->title);
        $ical->setXprop(IcalInterface::X_WR_CALDESC, $description ?? reset($arrCalendars)->ical_description);
        $ical->setXprop(IcalInterface::X_WR_TIMEZONE, Config::get('timeZone'));
        $time = time();

        foreach ($arrCalendars as $objCalendar) {
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
                continue;
            }

            $arrEvents = $objEvents->fetchAllAssociative();

            // HOOK: modify the result set
            if (isset($GLOBALS['TL_HOOKS']['icalGetAllEvents']) && \is_array($GLOBALS['TL_HOOKS']['icalGetAllEvents'])) {
                foreach ($GLOBALS['TL_HOOKS']['icalGetAllEvents'] as $callback) {
                    $this->import($callback[0]);
                    $arrEvents = $this->{$callback[0]}->{$callback[1]}($arrEvents, $arrCalendars, $intStart, $intEnd, $this);
                }
            }

            foreach ($arrEvents as $arrEvent) {
                $vevent = new Vevent();

                $startDate = $arrEvent['startDate'] ?? $arrEvent['startTime'];
                $endDate = $arrEvent['endDate'] ?? $arrEvent['endTime'];

                if (!empty($startDate)) {
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

                    $summary = $arrEvent['title'];
                    if (!empty($prefix)) {
                        $summary = $prefix.' '.$summary;
                    } elseif (!empty($objCalendar->ical_prefix)) {
                        $summary = $objCalendar->ical_prefix.' '.$summary;
                    }
                    $vevent->setSummary(html_entity_decode((string) $summary, ENT_QUOTES, 'UTF-8'));

                    if (!empty($arrEvent['teaser'])) {
                        $vevent->setDescription(html_entity_decode(strip_tags(preg_replace('/<br \\/>/', "\n",
                            $this->insertTagParser->replaceInline($arrEvent['teaser']))),
                            ENT_QUOTES, 'UTF-8'));
                    }

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
                    if (!empty($arrEvent['repeatExceptions'])) {
                        $arrSkipDates = StringUtil::deserialize($arrEvent['repeatExceptions'], true);

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
        }

        return $ical;
    }
}
