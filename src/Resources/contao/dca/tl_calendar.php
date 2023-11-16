<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Craffft\ContaoCalendarICalBundle\Classes\CalendarExport;

$GLOBALS['TL_DCA']['tl_calendar']['config']['onload_callback'][] = ['tl_calendar_ical', 'generate_ical'];
$GLOBALS['TL_DCA']['tl_calendar']['config']['onsubmit_callback'][] = ['CalendarImport', 'importFromURL'];

$GLOBALS['TL_DCA']['tl_calendar']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar']['palettes']['default'].';{ical_legend:hide},make_ical,ical_source';
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'make_ical';
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'ical_source';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['make_ical'] = 'ical_alias,ical_prefix,ical_description,ical_start,ical_end';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['ical_source'] = 'ical_url,ical_proxy,ical_bnpw,ical_port,ical_filter_event_title,ical_pattern_event_title,ical_replacement_event_title,ical_timezone,ical_cache,ical_source_start,ical_source_end';

$GLOBALS['TL_DCA']['tl_calendar']['fields']['make_ical'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['make_ical'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_timezone'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_timezone'],
    'default' => 0,
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'options_callback' => ['tl_calendar_ical', 'getTZ'],
    'eval' => ['doNotCopy' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_source'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_source'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_alias'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_alias'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'alnum', 'unique' => true, 'spaceToUnderscore' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
    'sql' => "varbinary(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_prefix'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_prefix'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_description'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_description'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'textarea',
    'eval' => ['style' => 'height:60px;', 'tl_class' => 'clr'],
    'sql' => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_url'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_url'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'long'],
    'sql' => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_proxy'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_proxy'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'long'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_bnpw'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_bnpw'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_port'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_port'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 35, 'tl_class' => 'w50'],
    'sql' => 'varchar(32) NULL',
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_filter_event_title'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_filter_event_title'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'clr'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_pattern_event_title'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_pattern_event_title'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_replacement_event_title'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_replacement_event_title'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_cache'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_cache'],
    'default' => 86400,
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default '86400'",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_start'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_start'],
    'default' => time(),
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => $this->getDatePickerString(), 'tl_class' => 'clr w50 wizard'],
    'sql' => "varchar(12) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_end'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_end'],
    'default' => time() + 365 * 24 * 3600,
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => $this->getDatePickerString(), 'tl_class' => 'w50 wizard'],
    'sql' => "varchar(12) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_source_start'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_start'],
    'default' => time(),
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => $this->getDatePickerString(), 'tl_class' => 'clr w50 wizard'],
    'sql' => "varchar(12) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_source_end'] =
[
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_end'],
    'default' => time() + 365 * 24 * 3600,
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => $this->getDatePickerString(), 'tl_class' => 'w50 wizard'],
    'sql' => "varchar(12) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_importing'] =
[
    'sql' => "char(1) NOT NULL default ''",
];

class tl_calendar extends Backend
{
    public function getTZ()
    {
        return $this->getTimezones();
    }

    /**
     * Update the RSS feed.
     *
     * @param object $dc
     */
    public function generate_ical(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $this->import(CalendarExport::class);
        $this->CalendarExport->exportCalendar($dc->id);
    }
}
