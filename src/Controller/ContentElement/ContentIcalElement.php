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
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[AsContentElement(type: ContentIcalElement::TYPE, category: 'file')]
class ContentIcalElement extends AbstractContentElementController
{
    final public const TYPE = 'ical';

    protected string $strTitle = '';

    protected Vcalendar $ical;

    public function __construct(
        private readonly IcsExport $icsExport,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        System::loadLanguageFile('tl_content');
        $template->title = !empty($model->linkTitle) ? $model->linkTitle : $GLOBALS['TL_LANG']['tl_content']['ical_title'];

        if (!empty(Input::get('ical'))) {
            $startDate = !empty($model->ical_start) ? $model->ical_start : time();
            $endDate = !empty($model->ical_end) ? $model->ical_end : time() + 365 * 24 * 3600;

            $arrCalendars = CalendarModel::findMultipleByIds(explode(',', urldecode(Input::get('ical'))));
            if (!empty($arrCalendars)) {
                $ical = $this->icsExport->getVcalendar($arrCalendars, $startDate, $endDate, urldecode(Input::get('title')), urldecode(Input::get('description')), urldecode(Input::get('prefix')));
                $file = new File('php://temp');
                $file->openFile('w+')->fwrite($ical->createCalendar());
                $binaryFileResponse = new BinaryFileResponse($file);
                $binaryFileResponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

                throw new ResponseException($binaryFileResponse);
            }
        }

        $template->link = $model->title;
        $arrCalendars = StringUtil::deserialize($model->ical_calendar, true);
        $template->href = Controller::addToUrl(
            'ical='.implode(',', $arrCalendars).
            '&title='.urlencode((string) $model->ical_title).
            '&description='.urlencode((string) $model->ical_description).
            '&prefix='.urlencode((string) $model->ical_prefix),
        );
        $template->title = $GLOBALS['TL_LANG']['tl_content']['ical_download_title'];

        return $template->getResponse();
    }
}
