<?php

namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\ColorDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Parts\WidgetPropertyScale;
use PHPUnit\Framework\TestCase;

/**
 * Covers configurable Color preset hints without requiring a persisted metamodel.
 */
class ColorDataTypeTest extends TestCase
{
    /**
     * Imports Color UXON into a real datatype without a database lookup.
     */
    private function createColorType(array $config = []): ColorDataType
    {
        $type = DataTypeFactory::createFromPrototype(new Workbench(), ColorDataType::class);
        $type->importUxonObject(new UxonObject($config));
        return $type;
    }

    /**
     * Colors have no descriptions unless explicitly configured.
     */
    public function testColorHintsDefaultToEmpty(): void
    {
        $type = $this->createColorType();
        self::assertSame([], $type->getColorHints());
        self::assertNull($type->getHintOfValue('~INFO'));
    }

    /**
     * The color_hints option supports semantic and CSS colors.
     */
    public function testColorHintsImportFromUxon(): void
    {
        $hints = ['~INFO' => 'Additional information', '#107e3e' => 'Success', 'red' => 'Failure'];
        $type = $this->createColorType(['color_hints' => $hints]);
        self::assertSame($hints, $type->getColorHints());
        self::assertSame('Additional information', $type->getHintOfValue('~INFO'));
        self::assertSame('Success', $type->getHintOfValue('#107e3e'));
        self::assertNull($type->getHintOfValue('blue'));
    }

    /**
     * The common hint scale exposes the datatype's configured color descriptions.
     */
    public function testHintScaleUsesColorHints(): void
    {
        $type = $this->createColorType(['color_hints' => ['~INFO' => 'Information']]);
        $widget = $this->createStub(WidgetInterface::class);
        $widget->method('getWorkbench')->willReturn($type->getWorkbench());
        $scale = new WidgetPropertyScale($widget, $type);
        self::assertSame(['~INFO' => 'Information'], $scale->getScaleValues());
    }

    /**
     * An explicit widget scale overrides the datatype's descriptions.
     */
    public function testExplicitHintScaleOverridesDatatype(): void
    {
        $type = $this->createColorType(['color_hints' => ['~INFO' => 'Information']]);
        $widget = $this->createStub(WidgetInterface::class);
        $widget->method('getWorkbench')->willReturn($type->getWorkbench());
        $scale = new WidgetPropertyScale($widget, $type, new UxonObject([
            'scale' => ['~INFO' => 'Widget-specific information']
        ]));
        self::assertSame(['~INFO' => 'Widget-specific information'], $scale->getScaleValues());
    }
}

