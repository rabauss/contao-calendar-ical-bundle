<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Idna;
use Contao\Image;
use Contao\StringUtil;
use Contao\System;

#[AsCallback(table: 'tl_calendar_events', target: 'list.sorting.header')]
class CalendarHeaderCallback
{
    /**
     * @param array<mixed> $labels
     *
     * @return array<mixed>
     */
    public function __invoke(array $labels, DataContainer $dc): array
    {
        if (!$dc->id) {
            return $labels;
        }

        System::loadLanguageFile('tl_calendar');

        $objCalendar = CalendarModel::findById($dc->id);
        if (null !== $objCalendar) {
            if (!empty($objCalendar->make_ical)) {
                $shareDir = StringUtil::stripRootDir(System::getContainer()->getParameter('contao.web_dir').'/share');
                $file = $shareDir.'/'.$objCalendar->ical_alias.'.ics';

                $clipboard = [
                    'content' => Idna::decode(Environment::get('base')).str_replace($shareDir, 'share', $file),
                    'title' => $GLOBALS['TL_LANG']['MSC']['copy_to_clipboard'],
                ];

                $content = sprintf(
                    '%s<a href="%s" target="_blank" title="%s" data-to-clipboard="%s">%s</a> ',
                    $file,
                    $clipboard['content'],
                    StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['copy_to_clipboard']),
                    StringUtil::specialchars(json_encode($clipboard, JSON_THROW_ON_ERROR)),
                    Image::getHtml('share.svg', $GLOBALS['TL_LANG']['MSC']['copy_to_clipboard']),
                );

                $labels[$GLOBALS['TL_LANG']['tl_calendar']['ical_alias']['0']] = $content;
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaocore/clipboard.min.js';
            }
            if (!empty($objCalendar->ical_source)) {
                $labels[$GLOBALS['TL_LANG']['tl_calendar']['ical_url']['0']] = $objCalendar->ical_url;
            }
        }

        return $labels;
    }
}
