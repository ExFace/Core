<?php
namespace exface\Core\CommonLogic\Utils;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Borders;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the fixed Terminübersicht workbook from normalized Gantt export data.
 */
class GanttXlsxBuilder
{
    private const DATA_START_ROW = 6;
    private const STATUS_HEADER_COLORS = [
        'A5A5A5', 'D9E1F2', 'D9E1F2', 'F2E3B3', 'F2D06B', 'D98943', 'D96D48', 'FFB1A8',
        'D9C7A7', '65B6BF', '176A73', '83A603', '618C03', '365902', 'A68660', '555643',
        '4E7FBF', '4E7FBF', '4E7FBF', '4E7FBF', '4E7FBF', '4E7FBF', '23468C', '23468C',
        '4D6C73', '85A0A6', 'A8BBBF', 'C1D4D9', '9C797C', '9C797C',
    ];

    private array $semanticColors;
    private bool $mergeCells;
    private float $textColorPreference;
    private int $freezeColumns;
    private int $defaultTaskDurationDays;
    private array $printSettings;

    /**
     * Creates a builder that resolves semantic colors with the active facade's CSS color map.
     *
     * @param array<string, string> $semanticColors
     * @param bool $mergeCells
     * @param float $textColorPreference
     * @param int $freezeColumns
     * @param int $defaultTaskDurationDays
     * @param array<string, mixed> $printSettings
     */
    public function __construct(
        array $semanticColors = [],
        bool $mergeCells = false,
        float $textColorPreference = 0.5,
        int $freezeColumns = 0,
        int $defaultTaskDurationDays = 2,
        array $printSettings = []
    )
    {
        if ($freezeColumns < 0) {
            throw new \InvalidArgumentException('The number of frozen columns cannot be negative.');
        }
        if ($defaultTaskDurationDays < 0) {
            throw new \InvalidArgumentException('The default task duration cannot be negative.');
        }
        $this->semanticColors = array_change_key_case($semanticColors, CASE_UPPER);
        $this->mergeCells = $mergeCells;
        $this->textColorPreference = max(0.0, min(1.0, $textColorPreference));
        $this->freezeColumns = $freezeColumns;
        $this->defaultTaskDurationDays = $defaultTaskDurationDays;
        $this->printSettings = $printSettings + [
            'orientation' => PageSetup::ORIENTATION_LANDSCAPE,
            'paper_size' => PageSetup::PAPERSIZE_A4,
            'page_order' => PageSetup::PAGEORDER_DOWN_THEN_OVER,
            'scale' => 100,
            'page_margins' => [],
        ];
        $this->printSettings['page_margins'] += [
            'left' => 0.25,
            'right' => 0.25,
            'top' => 0.75,
            'bottom' => 0.75,
            'header' => 0.3,
            'footer' => 0.3,
        ];
    }

    /**
     * Builds and saves the workbook from normalized Gantt data.
     *
     * @param mixed $data
     * @param string $outputPath
     * @return void
     */
    public function build($data, string $outputPath): void
    {
        $items = $this->extractItems($data);
        $basicHeaders = $this->collectHeaders($items, 'BasicInfo');
        $statusHeaders = $this->collectHeaders($items, 'StatusInfo');
        $timeline = $this->buildTimeline($items);
        $layout = $this->calculateLayout(count($basicHeaders), count($statusHeaders));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Terminübersicht');
        $spreadsheet->getDefaultStyle()->getFont()->setName('AvenirNext LT Com Regular')->setSize(12);
        $this->writeHeaders($sheet, $layout, $basicHeaders, $statusHeaders, $timeline);
        $this->writeDataRows($sheet, $layout, $basicHeaders, $statusHeaders, $timeline, $items);
        $this->applyWorksheetSettings($sheet, $layout, count($timeline));

        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Cannot create XLSX output directory: ' . $directory);
        }
        (new Xlsx($spreadsheet))->save($outputPath);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * Validates and extracts the normalized location records.
     *
     * @param mixed $data
     * @return list<array<string, mixed>>
     */
    private function extractItems($data): array
    {
        if (! is_array($data)) {
            throw new \RuntimeException('Gantt export data must be an array.');
        }
        $items = $data['Verortungen'] ?? $data;
        if (! is_array($items) || $items === []) {
            throw new \RuntimeException('Gantt export data must contain non-empty "Verortungen".');
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new \RuntimeException('Each Gantt location must be an object.');
            }
        }
        return array_values($items);
    }

    /**
     * Collects section headers in input order while omitting color helper fields.
     *
     * @param list<array<string, mixed>> $items
     * @param string $section
     * @return list<string>
     */
    private function collectHeaders(array $items, string $section): array
    {
        $headers = [];
        foreach ($items as $item) {
            $values = $item[$section] ?? [];
            if (! is_array($values)) {
                continue;
            }
            foreach (array_keys($values) as $key) {
                if (! is_string($key) || str_ends_with($key, '_Farbe')) {
                    continue;
                }
                $headers[$key] = true;
            }
        }
        return array_keys($headers);
    }

    /**
     * Creates the quarter-bounded ISO week timeline for complete task intervals or an empty timeline.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array{year:int,week:int,key:string}>
     */
    private function buildTimeline(array $items): array
    {
        $min = null;
        $max = null;
        foreach ($items as $item) {
            foreach ($this->extractRawTasks($item) as $task) {
                $interval = $this->resolveTaskInterval($task);
                if ($interval === null) {
                    continue;
                }
                $start = $interval['start'];
                $end = $interval['end'];
                if ($end < $start) {
                    [$start, $end] = [$end, $start];
                }
                $min = $min === null || $start < $min ? $start : $min;
                $max = $max === null || $end > $max ? $end : $max;
            }
        }
        if ($min === null || $max === null) {
            return [];
        }

        $startQuarterMonth = (($this->quarterForMonth((int) $min->format('n')) - 1) * 3) + 1;
        $endQuarterMonth = $this->quarterForMonth((int) $max->format('n')) * 3;
        $timelineStart = $min->setDate((int) $min->format('Y'), $startQuarterMonth, 1);
        $timelineEnd = $max->setDate((int) $max->format('Y'), $endQuarterMonth, 1)
            ->modify('last day of this month');
        $timelineStart = $timelineStart->modify('-' . ((int) $timelineStart->format('N') - 1) . ' days');
        $timelineEnd = $timelineEnd->modify('-' . ((int) $timelineEnd->format('N') - 1) . ' days');

        $timeline = [];
        for ($weekStart = $timelineStart; $weekStart <= $timelineEnd; $weekStart = $weekStart->modify('+1 week')) {
            $year = (int) $weekStart->format('o');
            $week = (int) $weekStart->format('W');
            $timeline[] = ['year' => $year, 'week' => $week, 'key' => $this->weekKey($year, $week)];
        }
        return $timeline;
    }

    /**
     * Extracts task rows from one normalized location.
     *
     * @param array<string, mixed> $item
     * @return list<array<string, mixed>>
     */
    private function extractRawTasks(array $item): array
    {
        $rows = $item['VerortungZuMassnahmeSichtbar']['rows'] ?? [];
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * Calculates fixed column regions from dynamic header counts.
     *
     * @return array<string, int>
     */
    private function calculateLayout(int $basicCount, int $statusCount): array
    {
        $basicEnd = $basicCount;
        $statusStart = $basicEnd + 1;
        $statusEnd = $statusStart + $statusCount - 1;
        return [
            'basicStart' => 1, 'basicEnd' => $basicEnd, 'statusStart' => $statusStart,
            'statusEnd' => $statusEnd, 'spacer' => $statusEnd + 1, 'verortung' => $statusEnd + 2,
            'ganttLabel' => $statusEnd + 3, 'timelineStart' => $statusEnd + 4,
        ];
    }

    /**
     * Writes all five workbook header rows and their fixed styles.
     *
     * @param array<string, int> $layout
     * @param list<string> $basicHeaders
     * @param list<string> $statusHeaders
     * @param list<array{year:int,week:int,key:string}> $timeline
     */
    private function writeHeaders(Worksheet $sheet, array $layout, array $basicHeaders, array $statusHeaders, array $timeline): void
    {
        $end = $this->getTimelineEndColumn($layout, count($timeline));
        $sheet->mergeCells($this->range($layout['basicStart'], 1, $layout['basicEnd'], 1));
        $sheet->setCellValue($this->cell($layout['basicStart'], 1), 'Mast-Basis-Informationen');
        $sheet->mergeCells($this->range($layout['statusStart'], 1, $layout['statusEnd'], 1));
        $sheet->setCellValue($this->cell($layout['statusStart'], 1), 'Relevanz + Status');
        $sheet->mergeCells($this->range($layout['verortung'], 1, $layout['verortung'], 5));
        $sheet->setCellValue($this->cell($layout['verortung'], 1), 'Verortung');
        $sheet->mergeCells($this->range($layout['ganttLabel'], 1, $end, 1));
        $sheet->setCellValue($this->cell($layout['ganttLabel'], 1), 'Ablauf-Gantt');
        foreach (['Jahr 20..', 'Quartal', 'Monat', 'KW'] as $index => $label) {
            $sheet->setCellValue($this->cell($layout['ganttLabel'], $index + 2), $label);
        }
        foreach ($basicHeaders as $index => $header) {
            $sheet->setCellValue($this->cell($layout['basicStart'] + $index, 4), $header);
        }
        foreach ($statusHeaders as $index => $header) {
            $column = $layout['statusStart'] + $index;
            $sheet->setCellValue($this->cell($column, 4), $header);
            $this->fill($sheet, $this->cell($column, 4), self::STATUS_HEADER_COLORS[$index] ?? 'A5A5A5');
        }
        $this->writeTimelineHeaders($sheet, $layout, $timeline);
        $this->styleHeaders($sheet, $layout, count($timeline));
    }

    /**
     * Writes and groups year, quarter, month, and week timeline headers.
     *
     * @param array<string, int> $layout
     * @param list<array{year:int,week:int,key:string}> $timeline
     */
    private function writeTimelineHeaders(Worksheet $sheet, array $layout, array $timeline): void
    {
        $groups = ['year' => [], 'quarter' => [], 'month' => []];
        foreach ($timeline as $index => $week) {
            $column = $layout['timelineStart'] + $index;
            $sheet->setCellValue($this->cell($column, 5), $week['week']);
            $groups['year'][] = ['column' => $column, 'label' => 'Ausführungsjahr: ' . $week['year']];
            $groups['quarter'][] = ['column' => $column, 'label' => 'Quartal ' . $this->quarterForWeek($week['week'])];
            $groups['month'][] = ['column' => $column, 'label' => $this->monthLabelForWeek($week['year'], $week['week'])];
        }
        $this->mergeGroups($sheet, $groups['year'], 2);
        $this->mergeGroups($sheet, $groups['quarter'], 3);
        $this->mergeGroups($sheet, $groups['month'], 4);
        $this->outlineGroups($sheet, $groups['year'], 2, 5);
    }

    /**
     * Merges adjacent timeline cells with equal labels.
     *
     * @param list<array{column:int,label:string}> $groups
     */
    private function mergeGroups(Worksheet $sheet, array $groups, int $row): void
    {
        $this->forEachGroup($groups, function (int $start, int $end, string $label) use ($sheet, $row): void {
            if ($end > $start) {
                $sheet->mergeCells($this->range($start, $row, $end, $row));
            }
            $sheet->setCellValue($this->cell($start, $row), $label);
        });
    }

    /**
     * Applies medium outlines around adjacent year groups.
     *
     * @param list<array{column:int,label:string}> $groups
     */
    private function outlineGroups(Worksheet $sheet, array $groups, int $startRow, int $endRow): void
    {
        $this->forEachGroup($groups, function (int $start, int $end) use ($sheet, $startRow, $endRow): void {
            $this->mediumOutline($sheet, $start, $startRow, $end, $endRow);
        });
    }

    /**
     * Calls a callback once for every adjacent equal-label group.
     *
     * @param list<array{column:int,label:string}> $groups
     */
    private function forEachGroup(array $groups, callable $callback): void
    {
        if ($groups === []) {
            return;
        }
        $start = $last = $groups[0]['column'];
        $label = $groups[0]['label'];
        foreach (array_slice($groups, 1) as $group) {
            if ($group['label'] !== $label) {
                $callback($start, $last, $label);
                $start = $group['column'];
                $label = $group['label'];
            }
            $last = $group['column'];
        }
        $callback($start, $last, $label);
    }

    /**
     * Applies all fixed header typography, alignment, and borders.
     */
    private function styleHeaders(Worksheet $sheet, array $layout, int $timelineCount): void
    {
        $end = $this->getTimelineEndColumn($layout, $timelineCount);
        $sheet->getStyle($this->range(1, 1, $end, 5))->applyFromArray([
            'font' => ['name' => 'AvenirNext LT Com Regular', 'color' => ['argb' => 'FF000000']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        foreach ([[$layout['basicStart'], $layout['basicEnd']], [$layout['statusStart'], $layout['statusEnd']],
                     [$layout['verortung'], $layout['verortung']], [$layout['ganttLabel'], $end]] as [$start, $stop]) {
            $sheet->getStyle($this->range($start, 1, $stop, 1))->getFont()->setBold(true)->setSize(12);
        }
        $sheet->getStyle($this->range($layout['basicStart'], 4, $layout['basicEnd'], 4))->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle($this->range($layout['statusStart'], 4, $layout['statusEnd'], 4))->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'textRotation' => 90, 'wrapText' => true],
        ]);
        foreach (range($layout['statusStart'], $layout['statusEnd']) as $column) {
            $rgb = $sheet->getStyle($this->cell($column, 4))->getFill()->getStartColor()->getRGB();
            $textColor = $this->resolveColor(
                ColorTools::pickTextColorForBackgroundColor('#' . $rgb, $this->textColorPreference),
                '000000'
            );
            $sheet->getStyle($this->cell($column, 4))->getFont()->getColor()->setARGB('FF' . $textColor);
        }
        $sheet->getStyle($this->range($layout['ganttLabel'], 2, $layout['ganttLabel'], 5))->getFont()->setSize(8);
        if ($timelineCount > 0) {
            $sheet->getStyle($this->range($layout['timelineStart'], 2, $end, 2))->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle($this->range($layout['timelineStart'], 3, $end, 3))->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle($this->range($layout['timelineStart'], 4, $end, 4))->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_BOTTOM],
            ]);
            $sheet->getStyle($this->range($layout['timelineStart'], 5, $end, 5))->applyFromArray([
                'font' => ['size' => 7],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_TOP],
            ]);
            $sheet->getStyle($this->range($layout['timelineStart'], 5, $end, 5))->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        }
        $sheet->getStyle($this->range(1, 4, $layout['ganttLabel'], 5))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $this->mediumOutline($sheet, 1, 1, $layout['basicEnd'], 5);
        $this->mediumOutline($sheet, $layout['statusStart'], 1, $layout['statusEnd'], 5);
        $this->mediumOutline($sheet, $layout['ganttLabel'], 1, $layout['ganttLabel'], 5);
        $this->mediumOutline($sheet, 1, 1, $layout['basicEnd'], 1);
        $this->mediumOutline($sheet, $layout['statusStart'], 1, $layout['statusEnd'], 1);
        $this->mediumOutline($sheet, $layout['ganttLabel'], 1, $end, 1);
    }

    /**
     * Writes merged basic/status cells and packed Gantt task lanes.
     *
     * @param list<string> $basicHeaders
     * @param list<string> $statusHeaders
     * @param list<array{year:int,week:int,key:string}> $timeline
     * @param list<array<string,mixed>> $items
     */
    private function writeDataRows(Worksheet $sheet, array $layout, array $basicHeaders, array $statusHeaders, array $timeline, array $items): void
    {
        $timelineIndex = [];
        foreach ($timeline as $index => $week) {
            $timelineIndex[$week['key']] = $index;
        }
        $timelineEnd = $this->getTimelineEndColumn($layout, count($timeline));

        $rowPlans = [];
        $row = self::DATA_START_ROW;
        foreach ($items as $item) {
            $lanes = $this->packTasks($this->normalizeTasks($item, $timelineIndex));
            $endRow = $row + max(1, count($lanes)) - 1;
            $rowPlans[] = ['item' => $item, 'lanes' => $lanes, 'startRow' => $row, 'endRow' => $endRow];
            $row = $endRow + 1;
        }

        if ($rowPlans === []) {
            return;
        }

        $lastRow = $row - 1;
        for ($dataRow = self::DATA_START_ROW; $dataRow <= $lastRow; $dataRow++) {
            $sheet->getRowDimension($dataRow)->setRowHeight(28.45);
        }
        $sheet->getStyle($this->range(1, self::DATA_START_ROW, $timelineEnd, $lastRow))->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF808080']]],
        ]);
        if ($statusHeaders !== []) {
            $sheet->getStyle($this->range($layout['statusStart'], self::DATA_START_ROW, $layout['statusEnd'], $lastRow))->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        foreach ($rowPlans as $plan) {
            $item = $plan['item'];
            $lanes = $plan['lanes'];
            $row = $plan['startRow'];
            $endRow = $plan['endRow'];
            $this->writeValueSection($sheet, 1, $basicHeaders, $item['BasicInfo'] ?? [], $row, $endRow);
            $this->writeValueSection($sheet, $layout['statusStart'], $statusHeaders, $item['StatusInfo'] ?? [], $row, $endRow);
            if ($this->mergeCells && $endRow > $row) {
                $sheet->mergeCells($this->range($layout['verortung'], $row, $layout['verortung'], $endRow));
            }
            foreach ($this->getValueRows($row, $endRow) as $valueRow) {
                $sheet->setCellValue($this->cell($layout['verortung'], $valueRow), $item['BasicInfo']['Verortung'] ?? '');
            }
            $sheet->getStyle($this->range($layout['verortung'], $row, $layout['verortung'], $endRow))->getFont()->setBold(true);
            foreach ($lanes as $laneIndex => $tasks) {
                foreach ($tasks as $task) {
                    $taskRow = $row + $laneIndex;
                    $start = $layout['timelineStart'] + $task['startIndex'];
                    $end = $layout['timelineStart'] + $task['endIndex'];
                    $range = $this->range($start, $taskRow, $end, $taskRow);
                    $sheet->setCellValue($this->cell($start, $taskRow), $task['label']);
                    $this->fill($sheet, $range, $task['color']);
                    $taskBorders = [
                        'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF808080']],
                    ];
                    if ($task['inferredInterval']) {
                        $taskBorders['diagonal'] = [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF808080'],
                        ];
                        $taskBorders['diagonalDirection'] = Borders::DIAGONAL_UP;
                    }
                    $sheet->getStyle($range)->applyFromArray([
                        'font' => ['size' => 12], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
                        'borders' => $taskBorders,
                    ]);
                }
            }
            $sheet->getStyle($this->range($layout['basicStart'], $endRow, $timelineEnd, $endRow))
                ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        }
    }

    /**
     * Writes one merged value section and applies companion field colors.
     *
     * @param list<string> $headers
     * @param mixed $values
     */
    private function writeValueSection(Worksheet $sheet, int $startColumn, array $headers, $values, int $startRow, int $endRow): void
    {
        $values = is_array($values) ? $values : [];
        foreach ($headers as $index => $header) {
            $column = $startColumn + $index;
            $range = $this->range($column, $startRow, $column, $endRow);
            if ($this->mergeCells && $endRow > $startRow) {
                $sheet->mergeCells($range);
            }
            $value = $values[$header] ?? '';
            $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $value;
            foreach ($this->getValueRows($startRow, $endRow) as $valueRow) {
                $sheet->setCellValue($this->cell($column, $valueRow), $value);
            }
            $color = $this->resolveColor($values[$header . '_Farbe'] ?? $values[preg_replace('/\s+/', '', $header) . '_Farbe'] ?? null);
            if ($color !== null) {
                $textColor = $this->resolveColor(
                    ColorTools::pickTextColorForBackgroundColor('#' . $color, $this->textColorPreference ),
                    '000000'
                );
                $this->fillWithTextColor($sheet, $range, $color, $textColor);
            }
        }
    }

    /**
     * Returns the rows that receive location values according to the merge setting.
     *
     * @return iterable<int>
     */
    private function getValueRows(int $startRow, int $endRow): iterable
    {
        return $this->mergeCells ? [$startRow] : range($startRow, $endRow);
    }

    /**
     * Normalizes valid dated tasks to timeline indices.
     *
     * @return list<array{label:string,color:string,startIndex:int,endIndex:int,inferredInterval:bool}>
     */
    private function normalizeTasks(array $item, array $timelineIndex): array
    {
        $tasks = [];
        foreach ($this->extractRawTasks($item) as $row) {
            $interval = $this->resolveTaskInterval($row);
            if ($interval === null) {
                continue;
            }
            $start = $interval['start'];
            $end = $interval['end'];
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }
            $startKey = $this->dateToWeekKey($start);
            $endKey = $this->dateToWeekKey($end);
            if (! isset($timelineIndex[$startKey], $timelineIndex[$endKey])) {
                continue;
            }
            $tasks[] = [
                'label' => (string) ($row['LABEL'] ?? ''), 'color' => $this->resolveColor($row['FarbeAnzeige'] ?? null, 'D9D9D9'),
                'startIndex' => min($timelineIndex[$startKey], $timelineIndex[$endKey]),
                'endIndex' => max($timelineIndex[$startKey], $timelineIndex[$endKey]),
                'inferredInterval' => $interval['inferred'],
            ];
        }
        usort($tasks, static fn(array $a, array $b): int => [$a['startIndex'], $a['endIndex'], $a['label']] <=> [$b['startIndex'], $b['endIndex'], $b['label']]);
        return $tasks;
    }

    /**
     * Resolves a task's interval and marks dates completed with the configured default duration.
     *
     * @param array<string, mixed> $task
     * @return array{start:\DateTimeImmutable,end:\DateTimeImmutable,inferred:bool}|null
     */
    private function resolveTaskInterval(array $task): ?array
    {
        $start = $this->parseDate($task['DurchfuehrungVon'] ?? null);
        $end = $this->parseDate($task['DurchfuehrungBis'] ?? null);
        if ($start === null && $end === null) {
            return null;
        }

        $inferred = false;
        if ($start === null) {
            $start = $end->modify('-' . $this->defaultTaskDurationDays . ' days');
            $inferred = true;
        } elseif ($end === null) {
            $end = $start->modify('+' . $this->defaultTaskDurationDays . ' days');
            $inferred = true;
        }

        return ['start' => $start, 'end' => $end, 'inferred' => $inferred];
    }

    /**
     * Packs non-overlapping tasks into the smallest available lanes.
     *
     * @return list<list<array{label:string,color:string,startIndex:int,endIndex:int}>>
     */
    private function packTasks(array $tasks): array
    {
        $lanes = [];
        foreach ($tasks as $task) {
            $placed = false;
            foreach ($lanes as &$lane) {
                $overlap = array_filter($lane, static fn(array $existing): bool => $task['startIndex'] <= $existing['endIndex'] && $task['endIndex'] >= $existing['startIndex']);
                if ($overlap === []) {
                    $lane[] = $task;
                    $placed = true;
                    break;
                }
            }
            unset($lane);
            if (! $placed) {
                $lanes[] = [$task];
            }
        }
        return $lanes;
    }

    /**
     * Applies fixed dimensions, panes, filters, and print settings.
     */
    private function applyWorksheetSettings(Worksheet $sheet, array $layout, int $timelineCount): void
    {
        $end = $this->getTimelineEndColumn($layout, $timelineCount);
        $lastRow = max(self::DATA_START_ROW, $sheet->getHighestDataRow());
        foreach ([1 => 22.5, 2 => 25.45, 3 => 36.75, 4 => 147.75, 5 => 23.2] as $row => $height) {
            $sheet->getRowDimension($row)->setRowHeight($height);
        }
        foreach (range(1, $layout['basicEnd']) as $offset => $column) {
            $sheet->getColumnDimensionByColumn($column)->setWidth([13.15, 13.0, 12.6, 14.45, 12, 15.15][$offset] ?? 13);
        }
        foreach (range($layout['statusStart'], $layout['statusEnd']) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setWidth(5.5);
        }
        $sheet->getColumnDimensionByColumn($layout['spacer'])->setWidth(6.13);
        $sheet->getColumnDimensionByColumn($layout['verortung'])->setWidth(16.2);
        $sheet->getColumnDimensionByColumn($layout['ganttLabel'])->setWidth(6.13);
        if ($timelineCount > 0) {
            foreach (range($layout['timelineStart'], $end) as $column) {
                $sheet->getColumnDimensionByColumn($column)->setWidth(2);
            }
        }
        $freezePaneColumn = min($this->freezeColumns, $end) + 1;
        $sheet->freezePane($this->cell($freezePaneColumn, self::DATA_START_ROW));
        $sheet->setSelectedCell($this->cell($freezePaneColumn, self::DATA_START_ROW));
        $sheet->setAutoFilter($this->range(1, 5, $end, $lastRow));
        $sheet->getPageSetup()
            ->setOrientation($this->printSettings['orientation'])
            ->setPaperSize($this->printSettings['paper_size'])
            ->setPageOrder($this->printSettings['page_order'])
            ->setScale($this->printSettings['scale'])
            ->setPrintArea($this->range(1, 1, $end, $lastRow));
        $margins = $this->printSettings['page_margins'];
        $sheet->getPageMargins()
            ->setLeft($margins['left'])
            ->setRight($margins['right'])
            ->setTop($margins['top'])
            ->setBottom($margins['bottom'])
            ->setHeader($margins['header'])
            ->setFooter($margins['footer']);
    }

    /**
     * Returns the last timeline column or the Gantt label column when no timeline is available.
     *
     * @param array<string, int> $layout
     */
    private function getTimelineEndColumn(array $layout, int $timelineCount): int
    {
        return $timelineCount > 0
            ? $layout['timelineStart'] + $timelineCount - 1
            : $layout['ganttLabel'];
    }

    /** Parses the strict date representation used by Gantt task data. */
    private function parseDate($value): ?\DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === trim($value) ? $date : null;
    }

    /** Converts a date to its ISO week key. */
    private function dateToWeekKey(\DateTimeImmutable $date): string
    {
        return $this->weekKey((int) $date->format('o'), (int) $date->format('W'));
    }

    /** Formats one timeline lookup key. */
    private function weekKey(int $year, int $week): string
    {
        return sprintf('%04d-W%02d', $year, $week);
    }

    /** Resolves the fixed 13-week quarter for a week. */
    private function quarterForWeek(int $week): int
    {
        return min(4, max(1, (int) ceil($week / 13)));
    }

    /** Resolves the calendar quarter for a month. */
    private function quarterForMonth(int $month): int
    {
        return (int) ceil($month / 3);
    }

    /** Formats the German month label associated with an ISO week. */
    private function monthLabelForWeek(int $year, int $week): string
    {
        $names = [1 => 'Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $date = (new \DateTimeImmutable())->setISODate($year, $week, 4);
        $month = (int) $date->format('n');
        $quarter = $this->quarterForWeek($week);
        $displayYear = (int) $date->format('Y');
        $monthQuarter = $this->quarterForMonth($month);
        if ($displayYear < $year || $monthQuarter < $quarter) {
            $month = (($quarter - 1) * 3) + 1;
        } elseif ($displayYear > $year || $monthQuarter > $quarter) {
            $month = $quarter * 3;
        }
        return $names[$month] . ' ' . substr((string) $year, -2);
    }

    /** Resolves facade semantic colors and CSS hexadecimal colors to six-digit RGB. */
    private function resolveColor($value, ?string $default = null): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }
        $value = strtoupper(trim($value));
        if (str_starts_with($value, '~')) {
            $value = strtoupper(trim($this->semanticColors[$value] ?? ''));
            if ($value === '') {
                return $default;
            }
        }
        if ($value === 'GREY' || $value === 'GRAY') {
            return '767171';
        }
        if (preg_match('/^#?([0-9A-F]{3})$/', $value, $matches) === 1) {
            return implode('', array_map(static fn(string $digit): string => $digit . $digit, str_split($matches[1])));
        }
        return preg_match('/^#?([0-9A-F]{6})$/', $value, $matches) === 1
            ? $matches[1]
            : $default;
    }

    /** Applies a solid RGB fill to a cell range. */
    private function fill(Worksheet $sheet, string $range, string $rgb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . strtoupper($rgb));
    }

    /** Applies a solid background and contrasting text color in one style operation. */
    private function fillWithTextColor(Worksheet $sheet, string $range, string $backgroundRgb, string $textRgb): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF' . strtoupper($backgroundRgb)],
            ],
            'font' => ['color' => ['argb' => 'FF' . strtoupper($textRgb)]],
        ]);
    }

    /** Applies a medium black outline to a rectangular range. */
    private function mediumOutline(Worksheet $sheet, int $startColumn, int $startRow, int $endColumn, int $endRow): void
    {
        $sheet->getStyle($this->range($startColumn, $startRow, $endColumn, $endRow))->applyFromArray([
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']]],
        ]);
    }

    /** Converts numeric coordinates to an A1 cell reference. */
    private function cell(int $column, int $row): string
    {
        return Coordinate::stringFromColumnIndex($column) . $row;
    }

    /** Converts numeric coordinates to an A1 range reference. */
    private function range(int $startColumn, int $startRow, int $endColumn, int $endRow): string
    {
        return $this->cell($startColumn, $startRow) . ':' . $this->cell($endColumn, $endRow);
    }
}