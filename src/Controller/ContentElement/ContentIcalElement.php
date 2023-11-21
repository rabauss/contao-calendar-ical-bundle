<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Controller\ContentElement;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Util\BinaryMemoryFileResponse;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\File;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[AsContentElement(type: ContentIcalElement::TYPE, category: 'files')]
class ContentIcalElement extends AbstractContentElementController
{
    final public const TYPE = 'ical';

    protected string $strTitle = '';

    protected Vcalendar $ical;

    public function __construct(
        private readonly string $projectDir,
        private readonly IcsExport $icsExport,
    ) {
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        System::loadLanguageFile('tl_content');

        if (!empty(Input::get('ical'))) {
            $startDate = !empty($model->ical_start) ? (int) $model->ical_start : time();
            $endDate = !empty($model->ical_end) ? (int) $model->ical_end : time() + 365 * 24 * 3600;

            $arrCalendars = CalendarModel::findMultipleByIds(StringUtil::deserialize($model->ical_calendar, true));
            if (!empty($arrCalendars)) {
                $ical = $this->icsExport->getVcalendar($arrCalendars, $startDate, $endDate, $model->ical_title, $model->ical_description, $model->ical_prefix);
                $filename = StringUtil::sanitizeFileName($model->ical_title).'.ics';
                $file = new File('system/tmp/'.$filename);
                $file->write($ical->createCalendar());
                $file->close();
                $binaryFileResponse = new BinaryFileResponse(new SymfonyFile($this->projectDir.'/'.$file->path));
                $binaryFileResponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

                throw new ResponseException($binaryFileResponse);
            }
        }

        $template->link = !empty($model->linkTitle) ? $model->linkTitle : $GLOBALS['TL_LANG']['tl_content']['ical_title'];
        $template->href = Controller::addToUrl('ical=1');
        $template->title = $GLOBALS['TL_LANG']['tl_content']['ical_download_title'];

        return $template->getResponse();
    }
}
