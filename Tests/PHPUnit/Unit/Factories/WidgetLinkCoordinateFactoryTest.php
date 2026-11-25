<?php
namespace exface\Core\Tests\PHPUnit\Unit\Factories;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\WidgetLinkFactory;
use PHPUnit\Framework\TestCase;

/**
 * Covers coordinate normalization without widget or facade services.
 */
class WidgetLinkCoordinateFactoryTest extends TestCase
{
    /**
     * Parses strings and default coordinates while reusing already parsed objects.
     *
     * @return void
     */
    public function testNormalizesCoordinateInputs() : void
    {
        $coordinate = WidgetLinkFactory::createCoordinateFromAnything('AMOUNT$~selected:sum');
        $this->assertSame('AMOUNT', $coordinate->getColumnCoordinate());
        $this->assertTrue($coordinate->isTargetingSelection());
        $this->assertSame('SUM', $coordinate->getAggregator());
        $this->assertSame($coordinate, WidgetLinkFactory::createCoordinateFromAnything($coordinate));
        $this->assertSame('', (string) WidgetLinkFactory::createCoordinateFromAnything(null));
    }

    /**
     * Keeps coordinate validation errors explicit at the factory boundary.
     *
     * @return void
     */
    public function testRejectsUnsupportedAggregators() : void
    {
        $this->expectException(InvalidArgumentException::class);
        WidgetLinkFactory::createCoordinateFromAnything('AMOUNT$~loaded:UNKNOWN');
    }
}
