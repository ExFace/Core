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
 * Builds a formatted timeline workbook from normalized Gantt export data.
 */
class GanttXlsxBuilder
{
    private const DATA_START_ROW = 6;

    private array $semanticColors;
    private bool $mergeCells;
    private float $textColorPreference;
    private int $freezeColumns;
    private int $defaultTaskDurationDays;
    private array $printSettings;
    private array $translations;
    private array $headingColors;
    private array $headerGroups;
    private ?string $idColumn;

    /**
     * Creates a builder that resolves semantic colors with the active facade's CSS color map.
     *
     * @param array<string, string> $semanticColors
     * @param bool $mergeCells
     * @param float $textColorPreference
     * @param int $freezeColumns
     * @param int $defaultTaskDurationDays
     * @param array<string, mixed> $printSettings
     * @param array<string, string> $translations
     * @param list<string|null> $headingColors
     * @param list<array<string,mixed>> $headerGroups
     * @param string|null $idColumn
     */
    public function __construct(
        array $semanticColors = [],
        bool $mergeCells = false,
        float $textColorPreference = 0.5,
        int $freezeColumns = 0,
        int $defaultTaskDurationDays = 2,
        array $printSettings = [],
        array $translations = [],
        array $headingColors = [],
        array $headerGroups = [],
        ?string $idColumn = null
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
        $requiredTranslations = [
            'SHEET_TITLE',
            'GANTT',
            'YEAR',
            'QUARTER',
            'MONTH',
            'CALENDAR_WEEK',
            'EXECUTION_YEAR',
            'MONTH_01',
            'MONTH_02',
            'MONTH_03',
            'MONTH_04',
            'MONTH_05',
            'MONTH_06',
            'MONTH_07',
            'MONTH_08',
            'MONTH_09',
            'MONTH_10',
            'MONTH_11',
            'MONTH_12',
        ];
        $missingTranslations = array_diff($requiredTranslations, array_keys($translations));
        if ($missingTranslations !== []) {
            throw new \InvalidArgumentException(
                'Missing Gantt workbook translations: ' . implode(', ', $missingTranslations)
            );
        }
        $this->translations = $translations;
        $this->headingColors = $headingColors;
        $this->headerGroups = $headerGroups;
        $this->idColumn = $idColumn;
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
        $headers = $this->collectHeaders($items);
        if ($headers === []) {
            throw new \RuntimeException('Gantt export data must contain at least one exported column.');
        }
        $idColumn = $this->resolveIdColumn($headers);
        $headerGroups = $this->resolveHeaderGroups(count($headers));
        $timeline = $this->buildTimeline($items);
        $layout = $this->calculateLayout(count($headers));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->translations['SHEET_TITLE']);
        $spreadsheet->getDefaultStyle()->getFont()->setName('AvenirNext LT Com Regular')->setSize(12);
        $this->writeHeaders($sheet, $layout, $headers, $headerGroups, $timeline, $idColumn);
        $this->writeDataRows($sheet, $layout, $headers, $headerGroups, $timeline, $items, $idColumn);
        $this->applyWorksheetSettings($sheet, $layout, $headerGroups, count($timeline));

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
     * @return list<string>
     */
    private function collectHeaders(array $items): array
    {
        $headers = [];
        foreach ($items as $item) {
            $values = $item['Columns'] ?? [];
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
     * Resolves the dedicated ID column, defaulting to the first exported column.
     *
     * @param list<string> $headers
     */
    private function resolveIdColumn(array $headers): string
    {
        $idColumn = $this->idColumn ?? $headers[0];
        if (! in_array($idColumn, $headers, true)) {
            throw new \InvalidArgumentException('The XLSX ID column "' . $idColumn . '" is not an exported column.');
        }
        return $idColumn;
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
     * Resolves configured groups to absolute workbook column boundaries.
     *
     * @return list<array<string,mixed>>
     */
    private function resolveHeaderGroups(int $columnCount): array
    {
        $configuredGroups = $this->headerGroups;
        if ($configuredGroups === []) {
            $configuredGroups = [[
                'name' => '',
                'column_count' => $columnCount,
                'column_width' => 13.0,
                'orientation' => 'horizontal',
                'empty_cell_filler' => null,
                'empty_cell_color' => null,
            ]];
        }

        $groups = [];
        $start = 1;
        $remaining = $columnCount;
        foreach ($configuredGroups as $group) {
            if (! is_array($group)) {
                throw new \InvalidArgumentException('Every XLSX header group must be an object.');
            }
            $group += [
                'empty_cell_filler' => null,
                'empty_cell_color' => null,
            ];
            if (! isset($group['name'], $group['column_count'], $group['column_width'], $group['orientation'])) {
                throw new \InvalidArgumentException('Every XLSX header group must contain name, column_count, column_width, and orientation.');
            }
            $count = (int) $group['column_count'];
            $width = (float) $group['column_width'];
            $orientation = (string) $group['orientation'];
            if ($count < 1 || $width <= 0 || ! in_array($orientation, ['horizontal', 'vertical'], true)) {
                throw new \InvalidArgumentException('Invalid XLSX header group settings.');
            }
            if ($remaining === 0) {
                break;
            }
            $count = min($count, $remaining);
            $groups[] = [
                'name' => (string) $group['name'],
                'column_count' => $count,
                'column_width' => $width,
                'orientation' => $orientation,
                'empty_cell_filler' => is_string($group['empty_cell_filler'])
                    ? $group['empty_cell_filler']
                    : null,
                'empty_cell_color' => is_string($group['empty_cell_color'])
                    ? $group['empty_cell_color']
                    : null,
                'start' => $start,
                'end' => $start + $count - 1,
            ];
            $start += $count;
            $remaining -= $count;
        }
        if ($remaining > 0) {
            $lastIndex = array_key_last($groups);
            $groups[$lastIndex]['column_count'] += $remaining;
            $groups[$lastIndex]['end'] += $remaining;
        }
        return $groups;
    }

    /**
     * Calculates fixed workbook regions from the exported column count.
     *
     * @return array<string, int>
     */
    private function calculateLayout(int $columnCount): array
    {
        return [
            'columnsStart' => 1,
            'columnsEnd' => $columnCount,
            'spacer' => $columnCount + 1,
            'idColumn' => $columnCount + 2,
            'ganttLabel' => $columnCount + 3,
            'timelineStart' => $columnCount + 4,
        ];
    }

    /**
     * Writes all five workbook header rows and their fixed styles.
     *
     * @param array<string, int> $layout
     * @param list<string> $headers
     * @param list<array<string,mixed>> $headerGroups
     * @param list<array{year:int,week:int,key:string}> $timeline
     * @param string $idColumn
     */
    private function writeHeaders(
        Worksheet $sheet,
        array $layout,
        array $headers,
        array $headerGroups,
        array $timeline,
        string $idColumn
    ): void
    {
        $end = $this->getTimelineEndColumn($layout, count($timeline));
        foreach ($headerGroups as $group) {
            if ($group['end'] > $group['start']) {
                $sheet->mergeCells($this->range($group['start'], 1, $group['end'], 1));
            }
            $sheet->setCellValue($this->cell($group['start'], 1), $group['name']);
        }
        $sheet->mergeCells($this->range($layout['idColumn'], 1, $layout['idColumn'], 5));
        $sheet->setCellValue($this->cell($layout['idColumn'], 1), $idColumn);
        $sheet->mergeCells($this->range($layout['ganttLabel'], 1, $end, 1));
        $sheet->setCellValue($this->cell($layout['ganttLabel'], 1), $this->translations['GANTT']);
        foreach ([
            $this->translations['YEAR'],
            $this->translations['QUARTER'],
            $this->translations['MONTH'],
            $this->translations['CALENDAR_WEEK'],
        ] as $index => $label) {
            $sheet->setCellValue($this->cell($layout['ganttLabel'], $index + 2), $label);
        }
        foreach ($headers as $index => $header) {
            $column = $layout['columnsStart'] + $index;
            $sheet->setCellValue($this->cell($column, 4), $header);
            $group = $this->findHeaderGroup($headerGroups, $column);
            $defaultColor = $group['orientation'] === 'vertical' ? 'A5A5A5' : 'FFFFFF';
            $color = $this->resolveColor($this->headingColors[$index] ?? null, $defaultColor);
            $this->fill($sheet, $this->cell($column, 4), $color);
        }
        $this->writeTimelineHeaders($sheet, $layout, $timeline);
        $this->styleHeaders($sheet, $layout, $headerGroups, count($timeline));
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
            $groups['year'][] = ['column' => $column, 'label' => $this->translations['EXECUTION_YEAR'] . ' ' . $week['year']];
            $groups['quarter'][] = ['column' => $column, 'label' => $this->translations['QUARTER'] . ' ' . $this->quarterForWeek($week['week'])];
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
    private function styleHeaders(Worksheet $sheet, array $layout, array $headerGroups, int $timelineCount): void
    {
        $end = $this->getTimelineEndColumn($layout, $timelineCount);
        $sheet->getStyle($this->range(1, 1, $end, 5))->applyFromArray([
            'font' => ['name' => 'AvenirNext LT Com Regular', 'color' => ['argb' => 'FF000000']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        foreach ($headerGroups as $group) {
            $sheet->getStyle($this->range($group['start'], 1, $group['end'], 1))->getFont()->setBold(true)->setSize(12);
            if ($group['orientation'] === 'vertical') {
                $sheet->getStyle($this->range($group['start'], 4, $group['end'], 4))->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'textRotation' => 90,
                        'wrapText' => true,
                    ],
                ]);
            } else {
                $sheet->getStyle($this->range($group['start'], 4, $group['end'], 4))->getFont()->setBold(true)->setSize(12);
            }
        }
        foreach (range($layout['columnsStart'], $layout['columnsEnd']) as $column) {
            $rgb = $sheet->getStyle($this->cell($column, 4))->getFill()->getStartColor()->getRGB();
            $textColor = $this->resolveColor(
                ColorTools::pickTextColorForBackgroundColor('#' . $rgb, $this->textColorPreference),
                '000000'
            );
            $sheet->getStyle($this->cell($column, 4))->getFont()->getColor()->setARGB('FF' . $textColor);
        }
        $sheet->getStyle($this->cell($layout['idColumn'], 1))->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle($this->range($layout['ganttLabel'], 1, $end, 1))->getFont()->setBold(true)->setSize(12);
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
        foreach ($headerGroups as $group) {
            $this->mediumOutline($sheet, $group['start'], 1, $group['end'], 5);
            $this->mediumOutline($sheet, $group['start'], 1, $group['end'], 1);
        }
        $this->mediumOutline($sheet, $layout['ganttLabel'], 1, $layout['ganttLabel'], 5);
        $this->mediumOutline($sheet, $layout['ganttLabel'], 1, $end, 1);
    }

    /**
     * Writes grouped column values and packed Gantt task lanes.
     *
     * @param list<string> $headers
     * @param list<array<string,mixed>> $headerGroups
     * @param list<array{year:int,week:int,key:string}> $timeline
     * @param list<array<string,mixed>> $items
     * @param string $idColumn
     */
    private function writeDataRows(
        Worksheet $sheet,
        array $layout,
        array $headers,
        array $headerGroups,
        array $timeline,
        array $items,
        string $idColumn
    ): void
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
        foreach ($headerGroups as $group) {
            if ($group['orientation'] === 'vertical') {
                $sheet->getStyle($this->range($group['start'], self::DATA_START_ROW, $group['end'], $lastRow))->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }
        }

        foreach ($rowPlans as $plan) {
            $item = $plan['item'];
            $lanes = $plan['lanes'];
            $row = $plan['startRow'];
            $endRow = $plan['endRow'];
            $this->writeValueSection(
                $sheet,
                $layout['columnsStart'],
                $headers,
                $headerGroups,
                $item['Columns'] ?? [],
                $row,
                $endRow
            );
            if ($this->mergeCells && $endRow > $row) {
                $sheet->mergeCells($this->range($layout['idColumn'], $row, $layout['idColumn'], $endRow));
            }
            foreach ($this->getValueRows($row, $endRow) as $valueRow) {
                $sheet->setCellValue($this->cell($layout['idColumn'], $valueRow), $item['Columns'][$idColumn] ?? null);
            }
            $sheet->getStyle($this->range($layout['idColumn'], $row, $layout['idColumn'], $endRow))->getFont()->setBold(true);
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
            $sheet->getStyle($this->range($layout['columnsStart'], $endRow, $timelineEnd, $endRow))
                ->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()->setARGB('FF000000');
        }
    }

    /**
     * Writes one merged value section and applies companion field colors.
     *
     * @param list<string> $headers
     * @param list<array<string,mixed>> $headerGroups
     * @param mixed $values
     */
    private function writeValueSection(
        Worksheet $sheet,
        int $startColumn,
        array $headers,
        array $headerGroups,
        $values,
        int $startRow,
        int $endRow
    ): void
    {
        $values = is_array($values) ? $values : [];
        foreach ($headers as $index => $header) {
            $column = $startColumn + $index;
            $group = $this->findHeaderGroup($headerGroups, $column);
            $range = $this->range($column, $startRow, $column, $endRow);
            if ($this->mergeCells && $endRow > $startRow) {
                $sheet->mergeCells($range);
            }
            $value = $values[$header] ?? null;
            $isEmpty = $value === null || (is_string($value) && trim($value) === '');
            if ($isEmpty) {
                $value = $group['empty_cell_filler'];
            }
            $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $value;
            foreach ($this->getValueRows($startRow, $endRow) as $valueRow) {
                $sheet->setCellValue($this->cell($column, $valueRow), $value);
            }
            $color = $isEmpty
                ? $this->resolveColor($group['empty_cell_color'])
                : $this->resolveColor($values[$header . '_Farbe'] ?? $values[preg_replace('/\s+/', '', $header) . '_Farbe'] ?? null);
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
    private function applyWorksheetSettings(Worksheet $sheet, array $layout, array $headerGroups, int $timelineCount): void
    {
        $end = $this->getTimelineEndColumn($layout, $timelineCount);
        $lastRow = max(self::DATA_START_ROW, $sheet->getHighestDataRow());
        foreach ([1 => 22.5, 2 => 25.45, 3 => 36.75, 4 => 147.75, 5 => 23.2] as $row => $height) {
            $sheet->getRowDimension($row)->setRowHeight($height);
        }
        foreach ($headerGroups as $group) {
            foreach (range($group['start'], $group['end']) as $column) {
                $sheet->getColumnDimensionByColumn($column)->setWidth($group['column_width']);
            }
        }
        $sheet->getColumnDimensionByColumn($layout['spacer'])->setWidth(6.13);
        $sheet->getColumnDimensionByColumn($layout['idColumn'])->setWidth(16.2);
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
     * Returns the configured group containing an absolute workbook column.
     *
     * @param list<array<string,mixed>> $headerGroups
     * @return array<string,mixed>
     */
    private function findHeaderGroup(array $headerGroups, int $column): array
    {
        foreach ($headerGroups as $group) {
            if ($column >= $group['start'] && $column <= $group['end']) {
                return $group;
            }
        }
        throw new \LogicException('No XLSX header group contains column ' . $column . '.');
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

    /** Formats the translated month label associated with an ISO week. */
    private function monthLabelForWeek(int $year, int $week): string
    {
        $names = [];
        for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
            $names[$monthNumber] = $this->translations[
                'MONTH_' . str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT)
            ];
        }
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