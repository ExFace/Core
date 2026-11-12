<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\Actions;

use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\UnexpectedValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies parameter parsing preserves native data types without starting Workbench.
 */
class ServiceParameterTest extends TestCase
{
    /**
     * Parsing must not coerce booleans or numbers to strings or reject optional null values.
     *
     * @param string $dataType
     * @param mixed $input
     * @param mixed $expected
     * @return void
     */
    #[DataProvider('parsedValues')]
    public function testPreservesParsedValueType(string $dataType, mixed $input, mixed $expected) : void
    {
        $parameter = new ServiceParameter(new Workbench());
        $parameter->setName('value')->setDataType(new UxonObject(['alias' => $dataType]));

        self::assertSame($expected, $parameter->parseValue($input));
    }

    /**
     * Supplies actual data types and inputs that exercise native return types.
     *
     * @return iterable<string,array{string,mixed,mixed}>
     */
    public static function parsedValues() : iterable
    {
        yield 'false' => ['exface.Core.Boolean', false, false];
        yield 'true' => ['exface.Core.Boolean', 'true', true];
        yield 'integer' => ['exface.Core.Number', '42', 42];
        yield 'zero' => ['exface.Core.Number', '0', 0];
        yield 'decimal' => ['exface.Core.Number', '1.25', 1.25];
        yield 'optional null' => ['exface.Core.Number', null, null];
        yield 'string' => ['exface.Core.String', '00123', '00123'];
    }

    /**
     * Native return types must not bypass required-value validation.
     *
     * @return void
     */
    public function testRejectsEmptyRequiredValue() : void
    {
        $parameter = new ServiceParameter(new Workbench());
        $parameter->setName('value')->setRequired(true);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Service parameter "value" cannot be empty!');
        $parameter->parseValue(null);
    }
}
