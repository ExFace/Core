<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\RangeDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeFormattingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\InputDateRange;
use PHPUnit\Framework\TestCase;

class InputDateRangeTest extends TestCase
{
    private function createFilter() : Filter
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $object = new MetaObject(new Model($workbench));
        $object->setAlias('ORDER');
        $object->setNamespace('Test.App');
        $attribute = new Attribute($object, 'Order Date', 'ORDER_DATE');
        $attribute->setDataType(DataTypeFactory::createFromPrototype($workbench, DateDataType::class));
        $attribute->setDefaultEditorUxon(new UxonObject(['widget_type' => 'InputDate']));
        $object->addAttribute($attribute);

        $filter = new Filter(UiPageFactory::createBlank($workbench, ''));
        $filter->setMetaObject($object);
        $filter->importUxonObject(new UxonObject([
            'attribute_alias' => 'ORDER_DATE',
            'input_widget' => [
                'widget_type' => 'InputDateRange',
                'ranges' => [
                    ['range' => 'month_previous'],
                    ['caption' => 'Past 3 months', 'range' => 'custom', 'range_from' => '-3m', 'range_to' => 0]
                ]
            ]
        ]));
        return $filter;
    }

    public function testDateRangeWorksAsOneFilterValue() : void
    {
        $filter = $this->createFilter();
        $widget = $filter->getInputWidget();
        self::assertInstanceOf(InputDateRange::class, $widget);
        self::assertSame('0', $widget->getRanges()[1]->getRangeTo());
        self::assertSame('..', $filter->getComparator());
        $filter->setValue('2026-10-26..2026-10-28');
        self::assertSame('2026-10-26..2026-10-28', $filter->getValue());
        self::assertSame('2026-10-26..2026-10-28', $widget->getValueDataType()->parse($filter->getValue()));
    }

    public function testCastNormalizesStructureWithoutInterpretingBounds() : void
    {
        self::assertSame('3w..5w', RangeDataType::cast(' 3w .. 5w '));
        self::assertSame('0..0', RangeDataType::cast(' 0 .. 0 '));
        self::assertNull(RangeDataType::cast(null));
        self::assertSame('', RangeDataType::cast(''));
        self::assertSame(EXF_LOGICAL_NULL, RangeDataType::cast(EXF_LOGICAL_NULL));
    }

    public function testCastRejectsMalformedRanges() : void
    {
        foreach (['2026-11-01..', ' ..2026-11-30', 'a..b..c', '1. .. 5', '1...5', ['a', 'b'], 0] as $value) {
            try {
                RangeDataType::cast($value);
                self::fail('Expected an invalid range to be rejected.');
            } catch (DataTypeCastingError $e) {
                self::assertStringContainsString('from..to', $e->getMessage());
            }
        }
    }

    public function testRangeLengthLimitRejectsRatherThanTruncates() : void
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $range = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class);
        $range->setLengthMax(4);
        $this->expectException(DataTypeValidationError::class);
        $range->parse('1..20');
    }

    public function testRangeLengthLimitAlsoAppliesAfterEndpointNormalization() : void
    {
        $range = $this->createFilter()->getValueDataType();
        $range->setLengthMax(10);
        $this->expectException(DataTypeValidationError::class);
        $range->parse('0..0');
    }

    public function testRangePreservesFalseAndHonorsEmptyValueConfiguration() : void
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $range = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class);
        $range->importUxonObject(new UxonObject([
            'endpoint_data_type' => BooleanDataType::class,
            'force_null_for_empty_values' => true
        ]));
        self::assertSame('0..1', $range->parse('false..true'));
        self::assertNull($range->parse(''));
    }

    public function testLogicalNullDateBoundIsNotValid() : void
    {
        self::assertFalse($this->createFilter()->getValueDataType()->isValidValue(EXF_LOGICAL_NULL . '..2026-11-30'));
    }

    public function testOpenRangeIsRejected() : void
    {
        $type = $this->createFilter()->getValueDataType();
        $this->expectException(DataTypeCastingError::class);
        $type->parse('2026-10-26..');
    }

    public function testRangeFormattingUsesBothEndpointFormatsWithoutChangingTheStoredValue() : void
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $range = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class);
        $range->importUxonObject(new UxonObject([
            'endpoint_data_type' => ['alias' => RangeFormattingDateDataType::class, 'format' => 'dd.MM.yyyy']
        ]));
        $value = '2026-11-01..2026-11-30';
        $range = $range->withValue($value);

        self::assertSame($value, $range->getValue());
        self::assertSame('01.11.2026 .. 30.11.2026', $range->format());
        self::assertSame('', $range->format(EXF_LOGICAL_NULL));

        $restored = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class);
        $restored->importUxonObject($range->exportUxonObject());
        self::assertSame($value, $restored->parse($value));
        self::assertSame('01.11.2026 .. 30.11.2026', $restored->format($value));
    }

    public function testIntlDateBoundsNormalizeToCanonicalDates() : void
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $range = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class);
        $range->importUxonObject(new UxonObject([
            'endpoint_data_type' => ['alias' => RangeFormattingDateDataType::class, 'format' => "yyyy'!'MM'!'dd"]
        ]));

        self::assertSame('2026-11-01..2026-11-30', $range->parse('2026!11!01..2026!11!30'));
        self::assertSame('2026!11!01 .. 2026!11!30', $range->format('2026!11!01..2026!11!30', false));
    }

    public function testRangeExportPreservesFormatOfAnEndpointInstance() : void
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $endpoint = DataTypeFactory::createFromPrototype($workbench, RangeFormattingDateDataType::class);
        $range = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class)
            ->setEndpointDataType($endpoint);

        $config = $range->exportUxonObject()->getProperty('endpoint_data_type');
        self::assertFalse($config->hasProperty('format'));

        $endpoint->setFormat('yyyy/MM/dd');
        $export = $range->exportUxonObject();
        self::assertSame('yyyy/MM/dd', $export->getProperty('endpoint_data_type')->getProperty('format'));

        $restored = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class);
        $restored->importUxonObject($export);

        self::assertSame('2026/11/01 .. 2026/11/30', $restored->format('2026-11-01..2026-11-30', false));
    }

    public function testRangeWithAStringEndpointSelectorReportsMalformedInputInStrictMode() : void
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $range = new RangeDataType(new DataTypeSelector($workbench, RangeDataType::class), null, new UxonObject([
            'endpoint_data_type' => DateDataType::class
        ]));
        self::assertSame('2026-11-01..2026-11-30', $range->parse(' 2026-11-01 .. 2026-11-30 '));

        $this->expectException(DataTypeFormattingError::class);
        $range->format('2026-11-01..', false);
    }

    public function testPresetGroupSurvivesUxonRoundTrip() : void
    {
        $widget = $this->createFilter()->getInputWidget();
        $widget->setRanges(new UxonObject([
            ['range' => 'custom', 'range_from' => '-3m', 'range_to' => 0, 'group' => 'months'],
            ['range' => 'quarter_previous', 'group' => 'Reporting periods'],
            ['range' => 'year_previous']
        ]));

        $widget->setRanges($widget->exportUxonObject()->getProperty('ranges'));
        [$grouped, $customGroup, $ungrouped] = $widget->getRanges();
        self::assertSame('months', $grouped->getGroup());
        self::assertSame('-3m', $grouped->getRangeFrom());
        self::assertSame('0', $grouped->getRangeTo());
        self::assertSame('Reporting periods', $customGroup->getGroup());
        self::assertNull($ungrouped->getGroup());
    }

    public function testBlankPresetGroupIsRejected() : void
    {
        $widget = $this->createFilter()->getInputWidget();
        $this->expectException(WidgetPropertyInvalidValueError::class);
        $widget->setRanges(new UxonObject([['range' => 'month_previous', 'group' => ' ']]));
    }

    public function testUnknownPresetPropertyIsRejectedDuringImport() : void
    {
        $this->expectException(UxonMapError::class);
        $this->createFilter()->getInputWidget()->setRanges(new UxonObject([
            ['range' => 'month_previous', 'capiton' => 'Previous month']
        ]));
    }

    public function testInvalidCaptionAndHintAreRejectedDuringImport() : void
    {
        $widget = $this->createFilter()->getInputWidget();
        foreach (['caption', 'hint'] as $name) {
            try {
                $widget->setRanges(new UxonObject([['range' => 'month_previous', $name => ['text' => 'Invalid']]]));
                self::fail("Expected {$name} to be rejected during import.");
            } catch (WidgetPropertyInvalidValueError $e) {
                self::assertStringContainsString($name, $e->getMessage());
            }
        }
    }

    public function testFacadeDefaultsCanBeEnabledWithoutCalendarOrReplacingPresets() : void
    {
        $widget = $this->createFilter()->getInputWidget();
        self::assertFalse($widget->getAllowDefaultOptions());
        $widget->importUxonObject(new UxonObject([
            'allow_custom_range' => false,
            'allow_default_options' => true
        ]));

        self::assertFalse($widget->getAllowCustomRange());
        self::assertTrue($widget->getAllowDefaultOptions());
        self::assertSame('Past 3 months', $widget->getRanges()[1]->getCaption());
        self::assertTrue($widget->exportUxonObject()->getProperty('allow_default_options'));
    }
}

class RangeFormattingDateDataType extends DateDataType
{
    public function getLocale() : string
    {
        return 'de_DE';
    }

    public function getValidationErrorReason($value) : ?string
    {
        return 'Invalid date.';
    }
}
