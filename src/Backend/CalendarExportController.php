<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Backend;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\File;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;

class CalendarExportController extends Backend
{
    public function __construct(
        private readonly IcsExport $icsExport,
        private readonly int $defaultEndTimeDifference,
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

            $objFile = new File(StringUtil::stripRootDir($shareDir).'/'.$file);

            if (
                'ics' === $objFile->extension
                && $useWhitelist ? \in_array($objFile->filename, $arrFeeds, true) : !\in_array($objFile->filename, $arrFeeds, true)
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
        $enddate = !empty($objCalendar->ical_end) ? (int) $objCalendar->ical_end :
            (time() + $this->defaultEndTimeDifference * 24 * 3600);
        $filename = $objCalendar->ical_alias ?? 'calendar'.$objCalendar->id;
        if (
            null !== $ical = $this->icsExport->getVcalendar([$objCalendar], $startdate, $enddate)
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
}
