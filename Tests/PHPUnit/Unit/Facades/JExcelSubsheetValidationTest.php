<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataImporter;
use exface\Core\Widgets\DataSpreadSheet;
use PHPUnit\Framework\TestCase;

/**
 * Covers widget-specific validation in the shared spreadsheet renderer without a metamodel.
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
     * Exercises the real column-model builder without constructing a facade or Workbench.
     *
     * @param WidgetInterface $widget
     * @return string
     */
    private function buildColumnModels(WidgetInterface $widget) : string
    {
        // Empty columns and early validation keep this fixture independent of facade services.
        $element = new class($widget) {
            use JExcelTrait {
                buildJsJExcelColumnModels as public;
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

        return $element->buildJsJExcelColumnModels();
    }
}
