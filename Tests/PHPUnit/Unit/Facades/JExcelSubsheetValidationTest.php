<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataImporter;
use exface\Core\Widgets\DataSpreadSheet;
use exface\Core\Widgets\InputComboTable;
use exface\Core\Widgets\InputSelect;
use exface\Core\Widgets\InputText;
use PHPUnit\Framework\TestCase;

/**
 * Covers selector validation in the shared spreadsheet renderer without a metamodel.
 */
class JExcelSubsheetValidationTest extends TestCase
{
    /**
     * Importers do not implement spreadsheet subsheet validation.
     */
    public function testImporterColumnModelsDoNotRequireSpreadsheetValidation() : void
    {
        $widget = $this->getMockBuilder(DataImporter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getColumns'])
            ->getMock();
        $widget->method('getColumns')->willReturn([]);

        self::assertSame('{}', $this->buildColumnModels($widget));
    }

    /**
     * Spreadsheets without child columns still render normally.
     */
    public function testSpreadsheetWithoutSubsheetColumnsBuildsColumnModels() : void
    {
        $widget = $this->getMockBuilder(DataSpreadSheet::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getColumns'])
            ->getMock();
        $widget->method('getColumns')->willReturn([]);

        self::assertSame('{}', $this->buildColumnModels($widget));
    }

    /**
     * The shared renderer must still reject unsupported spreadsheet child relations.
     */
    public function testSpreadsheetSubsheetValidationStillRejectsInvalidRelationPaths() : void
    {
        $path = $this->createStub(MetaRelationPathInterface::class);
        $path->method('getRelations')->willReturn([]);
        $column = $this->getMockBuilder(DataColumn::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSubsheetColumn', 'getSubsheetRelationAlias', 'getObjectRelationPathToParent'])
            ->getMock();
        $column->method('isSubsheetColumn')->willReturn(true);
        $column->method('getSubsheetRelationAlias')->willReturn('CHILDREN');
        $column->method('getObjectRelationPathToParent')->willReturn($path);
        $widget = $this->getMockBuilder(DataSpreadSheet::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getColumns'])
            ->getMock();
        $widget->method('getColumns')->willReturn([$column]);

        $this->expectException(WidgetConfigurationError::class);
        $this->expectExceptionMessage('require a direct forward object_relation_path_to_parent');
        $this->buildColumnModels($widget);
    }

    /**
     * @dataProvider unsupportedSelectorProvider
     */
    public function testRendererRejectsUnsupportedSelectors(string $widgetClass, string $scenario, string $message) : void
    {
        $column = $this->createSelectorColumn($scenario);
        $columns = $scenario === 'duplicate' ? [$column, $column] : [$column];
        $widget = $this->createWidget($widgetClass, $columns, $scenario !== 'parent-without-uid');

        $this->expectException(WidgetConfigurationError::class);
        $this->expectExceptionMessage($message);
        $this->buildColumnModels($widget);
    }

    public static function unsupportedSelectorProvider() : array
    {
        $cases = [];
        foreach ([DataSpreadSheet::class, DataImporter::class] as $widgetClass) {
            foreach ([
                'empty-path' => 'require a direct forward object_relation_path_to_parent',
                'multi-hop' => 'require a direct forward object_relation_path_to_parent',
                'reverse' => 'require a direct forward object_relation_path_to_parent',
                'parent-without-uid' => 'require a relation to the parent UID attribute',
                'non-uid-key' => 'require a relation to the parent UID attribute',
                'child-without-uid' => 'require a child UID attribute',
                'duplicate' => 'one relation selector column per child relation',
                'non-selector' => 'one relation selector column per child relation',
                'unbound' => 'one relation selector column per child relation',
                'non-relation' => 'one relation selector column per child relation',
                'calculation' => 'without calculations or footers',
                'footer' => 'without calculations or footers'
            ] as $scenario => $message) {
                $cases[$widgetClass . ': ' . $scenario] = [$widgetClass, $scenario, $message];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider widgetProvider
     */
    public function testRendererAcceptsSelectorsForDistinctChildRelations(string $widgetClass) : void
    {
        $widget = $this->createWidget($widgetClass, [
            $this->createSelectorColumn('valid', 'CHILDREN'),
            $this->createSelectorColumn('combo-table', 'OTHER_CHILDREN')
        ]);

        $this->createElement($widget)->validateSubsheetColumns();
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider widgetProvider
     */
    public function testRendererDoesNotApplySelectorRestrictionsToOrdinaryColumns(string $widgetClass) : void
    {
        $column = $this->getMockBuilder(DataColumn::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSubsheetColumn', 'getSubsheetRelationAlias', 'getObjectRelationPathToParent', 'getCellWidget'])
            ->getMock();
        $column->method('isSubsheetColumn')->willReturn(false);
        $column->expects(self::never())->method('getSubsheetRelationAlias');
        $column->expects(self::never())->method('getObjectRelationPathToParent');
        $column->expects(self::never())->method('getCellWidget');

        $this->createElement($this->createWidget($widgetClass, [$column]))->validateSubsheetColumns();
    }

    public static function widgetProvider() : array
    {
        return [
            'spreadsheet' => [DataSpreadSheet::class],
            'importer' => [DataImporter::class]
        ];
    }

    private function createWidget(string $widgetClass, array $columns, bool $hasUid = true) : WidgetInterface
    {
        $uid = $this->createStub(MetaAttributeInterface::class);
        $uid->method('getAlias')->willReturn('UID');
        $object = $this->createStub(MetaObjectInterface::class);
        $object->method('hasUidAttribute')->willReturn($hasUid);
        $object->method('getUidAttribute')->willReturn($uid);
        $widget = $this->getMockBuilder($widgetClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['getColumns', 'getMetaObject'])
            ->getMock();
        $widget->method('getColumns')->willReturn($columns);
        $widget->method('getMetaObject')->willReturn($object);
        return $widget;
    }

    private function createSelectorColumn(string $scenario, string $relationAlias = 'CHILDREN') : DataColumn
    {
        $key = $this->createStub(MetaAttributeInterface::class);
        $key->method('getAlias')->willReturn($scenario === 'non-uid-key' ? 'CODE' : 'UID');
        $relation = $this->createStub(MetaRelationInterface::class);
        $relation->method('isForwardRelation')->willReturn($scenario !== 'reverse');
        $relation->method('getRightKeyAttribute')->willReturn($key);
        $path = $this->createStub(MetaRelationPathInterface::class);
        $relations = $scenario === 'empty-path' ? [] : [$relation];
        if ($scenario === 'multi-hop') {
            $relations[] = $relation;
        }
        $path->method('getRelations')->willReturn($relations);
        $path->method('getRelationFirst')->willReturn($relation);
        $object = $this->createStub(MetaObjectInterface::class);
        $object->method('hasUidAttribute')->willReturn($scenario !== 'child-without-uid');
        $attribute = $this->createStub(MetaAttributeInterface::class);
        $attribute->method('isRelation')->willReturn($scenario !== 'non-relation');
        $editorClass = $scenario === 'non-selector' ? InputText::class : InputSelect::class;
        if ($scenario === 'combo-table') {
            $editorClass = InputComboTable::class;
        }
        $editor = $this->getMockBuilder($editorClass)->disableOriginalConstructor()->getMock();
        $column = $this->getMockBuilder(DataColumn::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'isSubsheetColumn', 'getSubsheetRelationAlias', 'getObjectRelationPathToParent',
                'getMetaObject', 'getCellWidget', 'isBoundToAttribute', 'getAttribute', 'isCalculated', 'hasFooter'
            ])
            ->getMock();
        $column->method('isSubsheetColumn')->willReturn(true);
        $column->method('getSubsheetRelationAlias')->willReturn($relationAlias);
        $column->method('getObjectRelationPathToParent')->willReturn($path);
        $column->method('getMetaObject')->willReturn($object);
        $column->method('getCellWidget')->willReturn($editor);
        $column->method('isBoundToAttribute')->willReturn($scenario !== 'unbound');
        $column->method('getAttribute')->willReturn($attribute);
        $column->method('isCalculated')->willReturn($scenario === 'calculation');
        $column->method('hasFooter')->willReturn($scenario === 'footer');
        return $column;
    }

    /**
     * Exercises the real column-model builder without constructing a facade or Workbench.
     *
     * @param WidgetInterface $widget
     * @return string
     */
    private function buildColumnModels(WidgetInterface $widget) : string
    {
        return $this->createElement($widget)->buildJsJExcelColumnModels();
    }

    private function createElement(WidgetInterface $widget) : object
    {
        return new class($widget) {
            use JExcelTrait {
                buildJsJExcelColumnModels as public;
                validateSubsheetColumns as public;
            }

            private WidgetInterface $widget;

            /**
             * Supplies only the widget needed by the column-model builder.
             *
             * @param WidgetInterface $widget
             */
            public function __construct(WidgetInterface $widget)
            {
                $this->widget = $widget;
            }

            /**
             * Returns the widget rendered by the trait.
             *
             * @return WidgetInterface
             */
            public function getWidget() : WidgetInterface
            {
                return $this->widget;
            }
        };
    }
}
