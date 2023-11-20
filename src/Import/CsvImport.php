<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Cgoit\ContaoCalendarIcalBundle\Import\Csv\CsvParser;
use Cgoit\ContaoCalendarIcalBundle\Widgets\WidgetTrait;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

class CsvImport extends AbstractImport
{
    use WidgetTrait;

    public function __construct(
        private readonly Connection $db,
        private readonly string $projectDir,
        Slug $slug,
    ) {
        parent::__construct($slug);
    }

    public function importFromCSVFile(bool $prepare = true): string
    {
        static::loadDataContainer('tl_calendar_events');

        $fieldnames = [];

        $schemaManager = $this->db->createSchemaManager();
        $dbfields = $schemaManager->listTableColumns('tl_calendar_events');

        foreach ($dbfields as $dbfield) {
            $fieldnames[] = $dbfield->getName();
        }

        $calfields =
            [
                ['title', $GLOBALS['TL_LANG']['tl_calendar_events']['title'][0]],
                ['startTime', $GLOBALS['TL_LANG']['tl_calendar_events']['startTime'][0]],
                ['endTime', $GLOBALS['TL_LANG']['tl_calendar_events']['endTime'][0]],
                ['startDate', $GLOBALS['TL_LANG']['tl_calendar_events']['startDate'][0]],
                ['endDate', $GLOBALS['TL_LANG']['tl_calendar_events']['endDate'][0]],
                ['details', $GLOBALS['TL_LANG']['tl_calendar_events']['details'][0]],
                ['teaser', $GLOBALS['TL_LANG']['tl_calendar_events']['teaser'][0]],
            ];

        if (\in_array('location', $fieldnames, true)) {
            $calfields[] = ['location', $GLOBALS['TL_LANG']['tl_calendar_events']['location'][0]];
        }
        if (\in_array('cep_participants', $fieldnames, true)) {
            $calfields[] = ['cep_participants', $GLOBALS['TL_LANG']['tl_calendar_events']['cep_participants'][0]];
        }
        if (\in_array('location_contact', $fieldnames, true)) {
            $calfields[] = ['location_contact', $GLOBALS['TL_LANG']['tl_calendar_events']['location_contact'][0]];
        }

        $dateFormat = Input::post('dateFormat');
        $timeFormat = Input::post('timeFormat');
        $fields = [];
        $csvvalues = Input::post('csvfield');
        $calvalues = Input::post('calfield');
        $encoding = Input::post('encoding');

        if (!\is_array($csvvalues)) {
            $sessiondata = StringUtil::deserialize(Config::get('calendar_ical.csvimport'), true);
            if (\is_array($sessiondata) && 5 === \count($sessiondata)) {
                $csvvalues = $sessiondata[0];
                $calvalues = $sessiondata[1];
                $dateFormat = $sessiondata[2];
                $timeFormat = $sessiondata[3];
                $encoding = $sessiondata[4];
            }
        }

        $data = $this->projectDir.'/'.$this->Session->get('csv_filename');
        $parser = new CsvParser($data, !empty((string) $encoding) ? $encoding : 'utf8');
        $header = $parser->extractHeader();

        for ($i = 0; $i < (is_countable($header) ? \count($header) : 0); ++$i) {
            $objCSV = $this->getFieldSelector($i, 'csvfield', $header,
                \is_array($csvvalues) ? $csvvalues[$i] : $header[$i]);
            $objCal = $this->getFieldSelector($i, 'calfield', $calfields, $calvalues[$i] ?? null);
            $fields[] = [$objCSV, $objCal];
        }

        if ($prepare) {
            $preview = $parser->getDataArray(5);
            $objTemplate = new BackendTemplate('be_import_calendar_csv_headers');
            $objTemplate->lngFields = $GLOBALS['TL_LANG']['tl_calendar_events']['fields'];
            $objTemplate->lngPreview = $GLOBALS['TL_LANG']['tl_calendar_events']['preview'];
            $objTemplate->check = $GLOBALS['TL_LANG']['tl_calendar_events']['check'];
            $objTemplate->header = $header;

            if (!empty($preview)) {
                foreach ($preview as $idx => $line) {
                    if (\is_array($line)) {
                        foreach ($line as $key => $value) {
                            $preview[$idx][$key] = StringUtil::specialchars($value);
                        }
                    }
                }
            }

            $objTemplate->preview = $preview;
            $objTemplate->encoding = $this->getEncodingWidget($encoding);

            if (\function_exists('date_parse_from_format')) {
                $objTemplate->dateFormat = $this->getDateFormatWidget($dateFormat);
                $objTemplate->timeFormat = $this->getTimeFormatWidget($timeFormat);
            }

            $objTemplate->hrefBack = StringUtil::ampersand(str_replace('&key=import', '', (string) Environment::get('request')));
            $objTemplate->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
            $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['import_calendar'][0];
            $objTemplate->request = StringUtil::ampersand(Environment::get('request'), true);
            $objTemplate->submit = StringUtil::specialchars($GLOBALS['TL_LANG']['tl_calendar_events']['proceed'][0]);
            $objTemplate->fields = $fields;

            return $objTemplate->parse();
        }
        // save config
        Config::set('calendar_ical.csvimport', serialize([
            $csvvalues,
            $calvalues,
            Input::post('dateFormat'),
            Input::post('timeFormat'),
            Input::post('encoding'),
        ]));

        if ($this->Session->get('csv_deletecalendar') && $this->Session->get('csv_pid')) {
            $objEvents = CalendarEventsModel::findByPid($this->Session->get('csv_pid'));
            if (!empty($objEvents)) {
                foreach ($objEvents as $event) {
                    $arrColumns = ['ptable=? AND pid=?'];
                    $arrValues = ['tl_calendar_events', $event->id];
                    $content = ContentModel::findBy($arrColumns, $arrValues);
                    if ($content) {
                        while ($content->next()) {
                            $content->delete();
                        }
                    }
                    $event->delete();
                }
            }
        }

        $done = false;

        while (!$done) {
            $data = $parser->getDataArray();

            if (false !== $data) {
                $eventcontent = [];
                $arrFields = [];
                $arrFields['tstamp'] = time();
                $arrFields['pid'] = $this->Session->get('csv_pid');
                $arrFields['published'] = 1;
                $arrFields['author'] = BackendUser::getInstance()->id ?: 0;

                foreach ($calvalues as $idx => $value) {
                    if (!empty($value)) {
                        $indexfield = $csvvalues[$idx];
                        $foundindex = array_search($indexfield, $header, true);

                        if (false !== $foundindex && !empty($data[$foundindex])) {
                            switch ($value) {
                                case 'startDate':
                                case 'endDate':
                                    if (\function_exists('date_parse_from_format')) {
                                        $res = date_parse_from_format(Input::post('dateFormat'), $data[$foundindex]);

                                        if (false !== $res) {
                                            $arrFields[$value] = mktime(0, 0, 0, $res['month'], $res['day'], $res['year']);
                                        }
                                    } else {
                                        $arrFields[$value] = $this->getTimestampFromDefaultDatetime($data[$foundindex]);
                                    }
                                    break;
                                case 'details':
                                    $eventcontent[] = StringUtil::specialchars($data[$foundindex]);
                                    break;
                                case 'title':
                                    $arrFields[$value] = StringUtil::specialchars($data[$foundindex]);
                                    $filterEventTitle = $this->Session->get('csv_filterEventTitle');
                                    if (!empty($filterEventTitle) && !str_contains(mb_strtolower(StringUtil::specialchars($data[$foundindex])), mb_strtolower((string) $filterEventTitle))) {
                                        continue 3;
                                    }
                                // no break
                                default:
                                    $arrFields[$value] = StringUtil::specialchars($data[$foundindex]);
                                    break;
                            }
                        }
                    }
                }

                if (!\array_key_exists('startDate', $arrFields)) {
                    $today = getdate();
                    $arrFields['startDate'] = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
                    $arrFields['startTime'] = $arrFields['startDate'];
                }

                if (!\array_key_exists('endDate', $arrFields) || empty($arrFields['endDate'])) {
                    $arrFields['endDate'] = $arrFields['startDate'];
                }

                foreach ($calvalues as $idx => $value) {
                    if (!empty($value)) {
                        $indexfield = $csvvalues[$idx];
                        $foundindex = array_search($indexfield, $header, true);

                        if (false !== $foundindex) {
                            switch ($value) {
                                case 'startTime':
                                    if (\function_exists('date_parse_from_format')) {
                                        $res = date_parse_from_format(Input::post('timeFormat'), $data[$foundindex]);

                                        if (false !== $res) {
                                            $arrFields[$value] = $arrFields['startDate'] + $res['hour'] * 60 * 60 + $res['minute'] * 60 + $res['second'];
                                        }
                                    } else {
                                        if (preg_match('/(\\d+):(\\d+)/', (string) $data[$foundindex], $matches)) {
                                            $arrFields[$value] = $arrFields['startDate'] + (int) $matches[1] * 60 * 60 + (int) $matches[2] * 60;
                                        }
                                    }
                                    break;
                                case 'endTime':
                                    if (\function_exists('date_parse_from_format')) {
                                        $res = date_parse_from_format(Input::post('timeFormat'), $data[$foundindex]);

                                        if (false !== $res) {
                                            $arrFields[$value] = $arrFields['endDate'] + $res['hour'] * 60 * 60 + $res['minute'] * 60 + $res['second'];
                                        }
                                    } else {
                                        if (preg_match('/(\\d+):(\\d+)/', (string) $data[$foundindex], $matches)) {
                                            $arrFields[$value] = $arrFields['endDate'] + (int) $matches[1] * 60 * 60 + (int) $matches[2] * 60;
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                }

                if ($arrFields['startDate'] !== $arrFields['startTime'] || $arrFields['endDate'] !== $arrFields['endTime']) {
                    $arrFields['addTime'] = 1;
                }

                if (!\array_key_exists('title', $arrFields)) {
                    $arrFields['title'] = $GLOBALS['TL_LANG']['tl_calendar_events']['untitled'];
                }

                $timeshift = (int) $this->Session->get('csv_timeshift');

                if (0 !== $timeshift) {
                    $arrFields['startDate'] += $timeshift * 3600;
                    $arrFields['endDate'] += $timeshift * 3600;
                    $arrFields['startTime'] += $timeshift * 3600;
                    $arrFields['endTime'] += $timeshift * 3600;
                }

                $startDate = new Date($this->Session->get('csv_startdate'), $GLOBALS['TL_CONFIG']['dateFormat']);
                $endDate = new Date($this->Session->get('csv_enddate'), $GLOBALS['TL_CONFIG']['dateFormat']);

                if (!\array_key_exists('source', $arrFields)) {
                    $arrFields['source'] = 'default';
                }

                if ($arrFields['endDate'] < $startDate->tstamp || (\strlen((string) $this->Session->get('csv_enddate')) && ($arrFields['startDate'] > $endDate->tstamp))) {
                    // date is not in range
                } else {
                    $objInsertStmt = $this->Database->prepare('INSERT INTO tl_calendar_events %s')
                        ->set($arrFields)
                        ->execute()
                    ;

                    if ($objInsertStmt->affectedRows) {
                        $insertID = (int) $objInsertStmt->insertId;

                        if (\count($eventcontent)) {
                            $step = 128;

                            foreach ($eventcontent as $content) {
                                $cm = new ContentModel();
                                $cm->tstamp = time();
                                $cm->pid = $insertID;
                                $cm->ptable = 'tl_calendar_events';
                                $cm->sorting = $step;
                                $step *= 2;
                                $cm->type = 'text';
                                $cm->text = $content;
                                $cm->save();
                            }
                        }

                        $alias = $this->generateAlias($arrFields['title'], $insertID, (int) $this->Session->get('csv_pid'));
                        $this->Database->prepare('UPDATE tl_calendar_events SET alias = ? WHERE id = ?')
                            ->execute($alias, $insertID)
                        ;
                    }
                }
            } else {
                $done = true;
            }
        }

        static::redirect(str_replace('&key=import', '', (string) Environment::get('request')));

        return '';
    }

    private function getTimestampFromDefaultDatetime(string $strDate): bool|int
    {
        $tstamp = time();

        if (preg_match('/(\\d{4})-(\\d{2})-(\\d{2})\\s+(\\d{2}):(\\d{2}):(\\d{2})/', $strDate, $matches)) {
            $tstamp = mktime((int) $matches[4], (int) $matches[5], (int) $matches[6], (int) $matches[2], (int) $matches[3],
                (int) $matches[1]);
        } elseif (preg_match('/(\\d{4})-(\\d{2})-(\\d{2})\\s+(\\d{2}):(\\d{2})/', $strDate, $matches)) {
            $tstamp = mktime((int) $matches[4], (int) $matches[5], 0, (int) $matches[2], (int) $matches[3],
                (int) $matches[1]);
        } elseif (preg_match('/(\\d{4})-(\\d{2})-(\\d{2})/', $strDate, $matches)) {
            $tstamp = mktime(0, 0, 0, (int) $matches[2], (int) $matches[3], (int) $matches[1]);
        } else {
            if (false !== strtotime($strDate)) {
                $tstamp = strtotime($strDate);
            }
        }

        return $tstamp;
    }
}
