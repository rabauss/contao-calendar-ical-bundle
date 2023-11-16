<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace contao\dca;

use Contao\Backend;

$GLOBALS['TL_DCA']['contao\dca\tl_content']['palettes']['ical'] = '{type_legend},type,headline;{calendar_legend},ical_calendar,ical_start,ical_end,ical_prefix;{link_legend},linkTitle;{protected_legend:hide},protected;{expert_legend},{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['contao\dca\tl_content']['fields']['ical_calendar'] =
    [
        'label' => &$GLOBALS['TL_LANG']['contao\dca\tl_content']['ical_calendar'],
        'exclude' => true,
        'inputType' => 'checkbox',
        'options_callback' => ['tl_content_ical', 'getCalendars'],
        'eval' => ['mandatory' => true, 'multiple' => true],
        'sql' => 'blob NULL',
    ];

$GLOBALS['TL_DCA']['contao\dca\tl_content']['fields']['ical_prefix'] =
    [
        'label' => &$GLOBALS['TL_LANG']['contao\dca\tl_content']['ical_prefix'],
        'exclude' => true,
        'search' => true,
        'inputType' => 'text',
        'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
        'sql' => "varchar(128) NOT NULL default ''",
    ];

$GLOBALS['TL_DCA']['contao\dca\tl_content']['fields']['ical_start'] =
    [
        'label' => &$GLOBALS['TL_LANG']['contao\dca\tl_content']['ical_start'],
        'default' => time(),
        'exclude' => true,
        'filter' => true,
        'flag' => 8,
        'inputType' => 'text',
        'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => $this->getDatePickerString(), 'tl_class' => 'w50 wizard'],
        'sql' => "varchar(12) NOT NULL default ''",
    ];

$GLOBALS['TL_DCA']['contao\dca\tl_content']['fields']['ical_end'] =
    [
        'label' => &$GLOBALS['TL_LANG']['contao\dca\tl_content']['ical_end'],
        'default' => time() + 365 * 24 * 3600,
        'exclude' => true,
        'filter' => true,
        'flag' => 8,
        'inputType' => 'text',
        'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => $this->getDatePickerString(), 'tl_class' => 'w50 wizard'],
        'sql' => "varchar(12) NOT NULL default ''",
    ];

class tl_content extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        $this->import('BackendUser', 'User');
    }

    /**
     * Get all calendars and return them as array.
     *
     * @return array
     */
    public function getCalendars()
    {
        if (!$this->User->isAdmin && !\is_array($this->User->calendars)) {
            return [];
        }

        $arrForms = [];
        $objForms = $this->Database->execute('SELECT id, title FROM tl_calendar ORDER BY title');

        while ($objForms->next()) {
            if ($this->User->isAdmin || \in_array($objForms->id, $this->User->calendars, true)) {
                $arrForms[$objForms->id] = $objForms->title;
            }
        }

        return $arrForms;
    }
}
