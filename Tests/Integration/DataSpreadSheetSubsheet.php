<?php
/** Run from Core: php Tests/Integration/DataSpreadSheetSubsheet.php
 * Requires an installed Core model. Performs reads only; never calls a write action.
 */
require dirname(__DIR__) . '/PHPUnit/bootstrap.php';

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Widgets\DataSpreadSheet;

$checks = 0;
$check = function (bool $condition, string $message) use (&$checks) {
    $checks++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$validateSelectors = function (DataSpreadSheet $widget) {
    $element = new class($widget) {
        use JExcelTrait {
            validateSubsheetColumns as public;
        }

        private DataSpreadSheet $widget;

        public function __construct(DataSpreadSheet $widget)
        {
            $this->widget = $widget;
        }

        public function getWidget() : DataSpreadSheet
        {
            return $this->widget;
        }
    };
    $element->validateSubsheetColumns();
};

$workbench = new Workbench();
$workbench->start();
$page = UiPageFactory::createBlank($workbench, '');
$selector = [
    'object_alias' => 'exface.Core.ATTRIBUTE',
    'attribute_alias' => 'DATATYPE',
    'object_relation_path_to_parent' => 'OBJECT',
    'cell_widget' => ['widget_type' => 'InputComboTable', 'lazy_loading' => false]
];
$widget = WidgetFactory::createFromUxon($page, new UxonObject([
    'widget_type' => 'DataSpreadSheet',
    'object_alias' => 'exface.Core.OBJECT',
    'columns' => [['attribute_alias' => 'NAME'], $selector]
]));
$childColumns = array_values(array_filter($widget->getColumns(), function ($column) {
    return $column->isSubsheetColumn();
}));
$check(count($childColumns) === 1, 'Expected one child selector');
$column = $childColumns[0];
$check($column->getMetaObject()->getAliasWithNamespace() === 'exface.Core.ATTRIBUTE', 'Registration overwrote child object');
$cell = $column->getCellWidget();
$check($cell->getMetaObject()->isExactly($column->getMetaObject()), 'Editor must inherit child context');
$check($cell->getTable()->getMetaObject()->getAliasWithNamespace() === 'exface.Core.DATATYPE', 'Wrong suggestion object');
$check($cell->getMultiSelect(), 'Child selectors should default to multi-selection');
$check(strpos($column->getDataColumnName(), '__subsheet_') === 0, 'Missing virtual cell name');
$validateSelectors($widget);

$sheet = $widget->prepareDataSheetToRead();
$check($sheet->hasUidColumn(), 'Spreadsheet must retain parent identity');
$relation = $column->getSubsheetRelationAlias();
$nested = $sheet->getColumns()->getByExpression($relation);
$check($nested && $nested->isNestedData(), 'Child relation is not a nested datasheet');
$check(! $sheet->getColumns()->get($column->getDataColumnName()), 'Virtual cell leaked into read columns');
$sheet->getFilters()->addConditionFromString('ALIAS', 'OBJECT');
$sheet->dataRead(1, 0);
$check($sheet->countRows() === 1, 'Expected a Core OBJECT record');
$value = $nested->getCellValue(0);
$check(is_array($value) && count($value['rows']) > 0, 'Child records were not loaded');
$check(isset($value['rows'][0]['UID']), 'Missing child record identity');
$check(array_key_exists('DATATYPE', $value['rows'][0]), 'Missing editable child attribute');
$check(array_key_exists('OBJECT', $value['rows'][0]), 'Missing child parent key');

// Round-trip the exact shape emitted by the spreadsheet, including an empty collection.
foreach ([$value['rows'], []] as $rows) {
    $submitted = DataSheetFactory::createFromUxon($workbench, new UxonObject([
        'object_alias' => 'exface.Core.OBJECT',
        'rows' => [['UID' => $sheet->getUidColumn()->getCellValue(0), $relation => [
            'object_alias' => 'exface.Core.ATTRIBUTE', 'rows' => $rows
        ]]]
    ]));
    $check($submitted->getColumns()->getByExpression($relation)->isNestedData(), 'Submitted collection not recognized as nested data');
}

// Generic child bindings must not inherit the spreadsheet's selector policies.
foreach (['Data', 'DataTable'] as $widgetType) {
    $generic = WidgetFactory::createFromUxon($page, new UxonObject([
        'widget_type' => $widgetType,
        'object_alias' => 'exface.Core.OBJECT',
        'columns' => [
            ['attribute_alias' => 'NAME'],
            array_replace_recursive($selector, [
                'filterable' => true,
                'sortable' => true,
                'cell_widget' => ['required' => true]
            ]),
            [
                'object_alias' => 'exface.Core.ATTRIBUTE',
                'attribute_alias' => 'NAME',
                'object_relation_path_to_parent' => 'OBJECT',
                'cell_widget' => ['widget_type' => 'Display']
            ]
        ]
    ]));
    $genericChildren = array_values(array_filter($generic->getColumns(), function ($candidate) {
        return $candidate->isSubsheetColumn();
    }));
    $check(count($genericChildren) === 2, $widgetType . ': missing generic child bindings');
    $genericColumn = $genericChildren[0];
    $check($genericColumn->getMetaObject()->isExactly($column->getMetaObject()), $widgetType . ': child context overwritten');
    $check($genericColumn->getDataColumnName() === 'DATATYPE', $widgetType . ': virtual spreadsheet cell leaked');
    $check($genericColumn->isFilterable() && $genericColumn->isSortable(), $widgetType . ': spreadsheet restrictions leaked');
    $check(! $genericColumn->getCellWidget()->getMultiSelect(), $widgetType . ': spreadsheet multi-select default leaked');
    $check($genericColumn->getCellWidget()->isRequired(), $widgetType . ': required editor setting lost');
    $genericSheet = $generic->prepareDataSheetToRead();
    $genericNested = $genericSheet->getColumns()->getByExpression($relation);
    $check($genericNested && $genericNested->isNestedData(), $widgetType . ': missing nested template');
    $genericTemplate = DataSheetFactory::createFromUxon($workbench, $genericNested->getNestedDataTemplateUxon());
    $check((bool) $genericTemplate->getColumns()->getByExpression('DATATYPE'), $widgetType . ': first child attribute lost');
    $check((bool) $genericTemplate->getColumns()->getByExpression('NAME'), $widgetType . ': second child attribute lost');
    $genericSheet->getFilters()->addConditionFromString('ALIAS', 'OBJECT');
    $genericSheet->dataRead(1, 0);
    $genericValue = $genericNested->getCellValue(0);
    $check(isset($genericValue['rows'][0]['DATATYPE'], $genericValue['rows'][0]['NAME']), $widgetType . ': merged child attributes not loaded');
}

$check(! $column->isFilterable() && ! $column->isSortable(), 'Spreadsheet selector restrictions missing');
$check(! $cell->isRequired(), 'Spreadsheet collection should default to optional');
$duplicate = WidgetFactory::createFromUxon($page, new UxonObject([
    'widget_type' => 'DataSpreadSheet',
    'object_alias' => 'exface.Core.OBJECT',
    'columns' => [$selector, $selector]
]));
$check(
    $duplicate->prepareDataSheetToRead()->getColumns()->getByExpression($relation)->isNestedData(),
    'Read preparation must not impose renderer-specific selector restrictions'
);
$duplicateRejected = false;
try {
    $validateSelectors($duplicate);
} catch (\exface\Core\Exceptions\Widgets\WidgetConfigurationError $error) {
    $duplicateRejected = strpos($error->getMessage(), 'one relation selector') !== false;
}
$check($duplicateRejected, 'Spreadsheet must still reject multiple selectors for a child relation');

// Owner hooks must also apply to columns inside explicit groups.
$grouped = WidgetFactory::createFromUxon($page, new UxonObject([
    'widget_type' => 'DataSpreadSheet',
    'object_alias' => 'exface.Core.OBJECT',
    'columns' => [['columns' => [array_replace_recursive($selector, [
        'cell_widget' => ['multi_select' => false, 'required' => true]
    ])]]]
]));
$groupedChildren = array_values(array_filter($grouped->getColumns(), function ($candidate) {
    return $candidate->isSubsheetColumn();
}));
$check(count($groupedChildren) === 1, 'Explicit column group lost child binding');
$check(strpos($groupedChildren[0]->getDataColumnName(), '__subsheet_') === 0, 'Grouped selector was not configured');
$check(! $groupedChildren[0]->getCellWidget()->getMultiSelect(), 'Explicit single-selection setting lost');
$check($groupedChildren[0]->getCellWidget()->isRequired(), 'Explicit required collection setting lost');

$multiHopSelector = array_replace($selector, ['object_relation_path_to_parent' => 'OBJECT__APP']);
$multiHop = WidgetFactory::createFromUxon($page, new UxonObject([
    'widget_type' => 'DataTable',
    'object_alias' => 'exface.Core.APP',
    'columns' => [$multiHopSelector]
]));
$multiHopColumn = $multiHop->getColumns()[0];
$check($multiHopColumn->isSubsheetColumn(), 'Multi-hop binding not recognized');
$check(count($multiHopColumn->getObjectRelationPathToParent()->getRelations()) === 2, 'Multi-hop test requires two relations');
$multiHopSheet = $multiHop->prepareDataSheetToRead();
$check($multiHopSheet->getColumns()->getByExpression($multiHopColumn->getSubsheetRelationAlias())->isNestedData(), 'Generic multi-hop template rejected');
$unsupported = WidgetFactory::createFromUxon($page, new UxonObject([
    'widget_type' => 'DataSpreadSheet',
    'object_alias' => 'exface.Core.APP',
    'columns' => [$multiHopSelector]
]));
$unsupportedChildren = array_values(array_filter($unsupported->getColumns(), function ($candidate) {
    return $candidate->isSubsheetColumn();
}));
$unsupportedRelation = $unsupportedChildren[0]->getSubsheetRelationAlias();
$check(
    $unsupported->prepareDataSheetToRead()->getColumns()->getByExpression($unsupportedRelation)->isNestedData(),
    'Read preparation must allow generic multi-hop child bindings'
);
$multiHopRejected = false;
try {
    $validateSelectors($unsupported);
} catch (\exface\Core\Exceptions\Widgets\WidgetConfigurationError $error) {
    $multiHopRejected = strpos($error->getMessage(), 'direct forward') !== false;
}
$check($multiHopRejected, 'Spreadsheet must reject unsupported multi-hop selectors');

echo 'Passed ' . $checks . ' read-only subsheet integration checks.' . PHP_EOL;
