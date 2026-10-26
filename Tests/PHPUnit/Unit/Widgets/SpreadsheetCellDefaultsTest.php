<?php
namespace exface\Core\Tests\PHPUnit\Unit\Widgets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataSpreadSheet;
use exface\Core\Widgets\DataTable;
use PHPUnit\Framework\TestCase;

/** Local default-selection checks; no Workbench or persisted metamodel is needed. */
class SpreadsheetCellDefaultsTest extends TestCase
{
    public function testReadonlySpreadsheetUsesColoredAttributeDefaults() : void
    {
        foreach (['ColorIndicator', 'ProgressBar'] as $type) {
            $display = new UxonObject(['widget_type' => $type, 'color_scale' => ['ready' => 'green']]);
            $attribute = $this->attribute($display);
            $attribute->expects($this->never())->method('getDefaultEditorUxon');
            $column = $this->column(true, false);
            [$uxon, $fallback] = $this->defaults($column, $attribute);
            $this->assertSame($display, $uxon);
            $this->assertSame('Display', $fallback);
        }
    }

    public function testInheritedEditingOverridesColoredDisplayDefaults() : void
    {
        foreach (['ColorIndicator', 'ProgressBar'] as $type) {
            $attribute = $this->attribute(new UxonObject(['widget_type' => $type]));
            $attribute->expects($this->never())->method('getDefaultDisplayUxon');
            [$uxon, $fallback] = $this->defaults($this->column(), $attribute);
            $this->assertSame('Input', $uxon->getProperty('widget_type'));
            $this->assertSame('Input', $fallback);
        }
    }

    public function testBooleanDatatypeDefaultsRespectColumnEditabilityAndAttributeOverrides() : void
    {
        // Attribute's legacy property default normally comes from Workbench startup.
        if (! defined('EXF_LIST_SEPARATOR')) {
            define('EXF_LIST_SEPARATOR', ',');
        }
        $display = new UxonObject([
            'widget_type' => 'ColorIndicator', 'color_only' => false, 'fill' => true,
            'color_scale' => ['lightsteelblue', 'steelblue']
        ]);
        $datatype = $this->createMock(BooleanDataType::class);
        $datatype->method('getDefaultDisplayUxon')->willReturn($display);
        $datatype->method('getDefaultEditorUxon')->willReturn(new UxonObject(['widget_type' => 'InputCheckBox']));
        $attribute = $this->getMockBuilder(Attribute::class)->disableOriginalConstructor()
            ->onlyMethods(['getDataType', 'getAliasWithRelationPath', 'isRelation', 'isEditable'])->getMock();
        $attribute->method('getDataType')->willReturn($datatype);
        $attribute->method('getAliasWithRelationPath')->willReturn('FLAG');
        $attribute->method('isRelation')->willReturn(false);
        $attribute->method('isEditable')->willReturn(true);

        [$uxon, $fallback] = $this->defaults($this->column(true, false), $attribute);
        $this->assertSame('ColorIndicator', $uxon->getProperty('widget_type'));
        $this->assertFalse($uxon->getProperty('color_only'));
        $this->assertTrue($uxon->getProperty('fill'));
        $this->assertSame($display->getProperty('color_scale')->toArray(), $uxon->getProperty('color_scale')->toArray());
        $this->assertSame('Display', $fallback);

        [$uxon, $fallback] = $this->defaults($this->column(), $attribute);
        $this->assertSame('InputCheckBox', $uxon->getProperty('widget_type'));
        $this->assertSame('Input', $fallback);

        $attribute->setDefaultDisplayUxon(new UxonObject(['widget_type' => 'ProgressBar']));
        [$uxon] = $this->defaults($this->column(true, false), $attribute);
        $this->assertSame('ProgressBar', $uxon->getProperty('widget_type'));
        $this->assertFalse($display->hasProperty('attribute_alias'));
    }

    public function testOrdinarySpreadsheetAttributesKeepTheirEditors() : void
    {
        $attribute = $this->attribute(new UxonObject(['widget_type' => 'Display']));
        [$uxon, $fallback] = $this->defaults($this->column(), $attribute);
        $this->assertSame('Input', $uxon->getProperty('widget_type'));
        $this->assertSame('Input', $fallback);
    }

    public function testExplicitEditingOverridesTheDisplayDefault() : void
    {
        $column = $this->column();
        $property = new \ReflectionProperty(DataColumn::class, 'editable');
        $property->setAccessible(true);
        $property->setValue($column, true);
        $attribute = $this->attribute(new UxonObject(['widget_type' => 'ColorIndicator']));
        [$uxon, $fallback] = $this->defaults($column, $attribute);
        $this->assertSame('Input', $uxon->getProperty('widget_type'));
        $this->assertSame('Input', $fallback);
    }

    public function testActionControlledEditingKeepsTheEditor() : void
    {
        $column = $this->column();
        $column->setEditableIfAccessToAction('exface.Core.UpdateData');
        [$uxon] = $this->defaults($column, $this->attribute(new UxonObject(['widget_type' => 'ColorIndicator'])));
        $this->assertSame('Input', $uxon->getProperty('widget_type'));
    }

    public function testEditableDataTablesKeepTheirEditors() : void
    {
        $column = $this->column(false);
        [$uxon] = $this->defaults($column, $this->attribute(new UxonObject(['widget_type' => 'ColorIndicator'])));
        $this->assertSame('Input', $uxon->getProperty('widget_type'));
    }

    public function testReadonlyColumnsUseFullDisplayDefaults() : void
    {
        $display = new UxonObject(['widget_type' => 'ColorIndicator', 'fill' => true, 'color_only' => false]);
        [$uxon, $fallback] = $this->defaults($this->column(true, false), $this->attribute($display));
        $this->assertSame($display, $uxon);
        $this->assertSame('Display', $fallback);
    }

    public function testHiddenColumnsDoNotLoadComplexDefaults() : void
    {
        $attribute = $this->createMock(MetaAttributeInterface::class);
        $attribute->expects($this->never())->method('getDefaultDisplayUxon');
        $attribute->expects($this->never())->method('getDefaultEditorUxon');
        [$uxon, $fallback] = $this->defaults($this->column(true, true, true), $attribute);
        $this->assertTrue($uxon->isEmpty());
        $this->assertSame('Display', $fallback);
    }

    private function attribute(UxonObject $display)
    {
        $attribute = $this->createMock(MetaAttributeInterface::class);
        $attribute->method('getDefaultDisplayUxon')->willReturn($display);
        $attribute->method('getDefaultEditorUxon')->willReturn(new UxonObject(['widget_type' => 'Input']));
        $attribute->method('isEditable')->willReturn(true);
        return $attribute;
    }

    private function column(bool $spreadsheet = true, bool $editable = true, bool $hidden = false) : DataColumn
    {
        $column = $this->getMockBuilder(DataColumn::class)->disableOriginalConstructor()
            ->onlyMethods(['getDataWidget', 'isEditable', 'isHidden'])->getMock();
        $column->method('getDataWidget')->willReturn($this->createMock($spreadsheet ? DataSpreadSheet::class : DataTable::class));
        $column->method('isEditable')->willReturn($editable);
        $column->method('isHidden')->willReturn($hidden);
        return $column;
    }

    private function defaults(DataColumn $column, MetaAttributeInterface $attribute) : array
    {
        $method = new \ReflectionMethod(DataColumn::class, 'getDefaultCellWidgetConfiguration');
        $method->setAccessible(true);
        return $method->invoke($column, $attribute);
    }
}


