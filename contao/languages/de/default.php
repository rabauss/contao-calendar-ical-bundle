<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Cgoit\ContaoCalendarIcalBundle\Controller\ContentElement\ContentIcalElement;

$GLOBALS['TL_LANG']['CTE'][ContentIcalElement::TYPE]['0'] = 'iCal';
$GLOBALS['TL_LANG']['CTE'][ContentIcalElement::TYPE]['1'] = 'Dieses Element erstellt einen Hyperlink auf eine vCalendar (iCal)-Datei, die heruntergeladen werden kann und dazu verwendet werden kann, die enthaltenen Termine in einer externen Kalenderanwendung anzuzeigen.';

$GLOBALS['TL_LANG']['MSC']['import_calendar']['0'] = 'Kalender importieren';
$GLOBALS['TL_LANG']['MSC']['import_calendar']['1'] = 'Importieren Sie eine iCal (.ics) Kalender-Datei in Ihren Kalender.';

$GLOBALS['TL_LANG']['MSC']['export_calendar']['0'] = 'Kalender exportieren';
$GLOBALS['TL_LANG']['MSC']['export_calendar']['1'] = 'Importieren Sie Ihren Kalender in eine iCal (.ics) Kalender-Datei.';

$GLOBALS['TL_LANG']['MSC']['location'] = 'Ort';
$GLOBALS['TL_LANG']['ERR']['only_one_file'] = 'Bitte wählen Sie nur eine Datei zum Hochladen aus!';
