<?php
namespace exface\Core\Tests\PHPUnit\Unit\Factories;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\ExpressionFactory;
use PHPUnit\Framework\TestCase;

/**
 * Protects expression ownership when installers use multiple workbenches in one PHP process.
 */
class ExpressionFactoryWorkbenchCacheTest extends TestCase
{
    /**
     * Identical requests reuse a live expression within the same workbench.
     *
     * @return void
     */
    public function testReusesExpressionWithinWorkbench(): void
    {
        $workbench = $this->workbench();
        $object = $this->object($workbench);
        $expression = ExpressionFactory::createForObject($object, 'UID');

        $this->assertSame($expression, ExpressionFactory::createForObject($object, 'UID'));
        $this->assertSame($workbench, $expression->getWorkbench());
        $this->assertSame($object, $expression->getMetaObject());
    }

    /**
     * Matching model UIDs must not reuse expressions belonging to another workbench.
     *
     * @return void
     */
    public function testIsolatesWorkbenchesWithMatchingObjectUids(): void
    {
        $firstWorkbench = $this->workbench();
        $secondWorkbench = $this->workbench();
        $firstObject = $this->object($firstWorkbench);
        $secondObject = $this->object($secondWorkbench);
        $first = ExpressionFactory::createForObject($firstObject, 'UID');
        $second = ExpressionFactory::createForObject($secondObject, 'UID');

        $this->assertNotSame($first, $second);
        $this->assertSame($secondWorkbench, $second->getWorkbench());
        $this->assertSame($secondObject, $second->getMetaObject());
        $this->assertSame($first, ExpressionFactory::createForObject($firstObject, 'UID'));
    }

    /**
     * Objectless expressions also carry a workbench and require isolation.
     *
     * @return void
     */
    public function testIsolatesObjectlessExpressions(): void
    {
        $firstWorkbench = $this->workbench();
        $secondWorkbench = $this->workbench();
        $first = ExpressionFactory::createFromString($firstWorkbench, "'constant'");
        $second = ExpressionFactory::createFromString($secondWorkbench, "'constant'");

        $this->assertNotSame($first, $second);
        $this->assertSame($secondWorkbench, $second->getWorkbench());
        $this->assertSame($first, ExpressionFactory::createFromString($firstWorkbench, "'constant'"));
    }

    /**
     * Reloading a model object in one workbench must not return an expression of the previous instance.
     *
     * @return void
     */
    public function testDoesNotReuseReplacedMetaobject(): void
    {
        $workbench = $this->workbench();
        $original = $this->object($workbench);
        $replacement = $this->object($workbench);
        $first = ExpressionFactory::createForObject($original, 'UID');
        $second = ExpressionFactory::createForObject($replacement, 'UID');

        $this->assertNotSame($first, $second);
        $this->assertSame($replacement, $second->getMetaObject());
        $this->assertSame($second, ExpressionFactory::createForObject($replacement, 'UID'));
    }

    /**
     * A static cache must not keep an otherwise unused workbench alive through its expressions.
     *
     * @return void
     */
    public function testCacheDoesNotRetainUnusedWorkbench(): void
    {
        $workbench = $this->workbench();
        $expression = ExpressionFactory::createFromString($workbench, "'temporary'");
        $reference = \WeakReference::create($workbench);
        unset($expression, $workbench);
        gc_collect_cycles();

        $this->assertNull($reference->get());
    }

    /**
     * Scalar type and parsing mode remain part of the cache identity.
     *
     * @return void
     */
    public function testPreservesScalarTypesAndParsingModes(): void
    {
        $workbench = $this->workbench();
        $boolean = ExpressionFactory::createFromString($workbench, false);
        $number = ExpressionFactory::createFromString($workbench, 0);
        $string = ExpressionFactory::createFromString($workbench, '0');
        $object = $this->object($workbench);
        $unknown = ExpressionFactory::createForObject($object, 'MISSING');
        $literal = ExpressionFactory::createForObject($object, 'MISSING', true);

        $this->assertNotSame($boolean, $number);
        $this->assertNotSame($number, $string);
        $this->assertNotSame($unknown, $literal);
        $this->assertFalse($unknown->isString());
        $this->assertTrue($literal->isString());
    }

    /**
     * Creates a real workbench without starting services or accessing the model database.
     *
     * @return Workbench
     */
    private function workbench(): Workbench
    {
        return new Workbench([
            'SERVER.INSTALLATION_NAME' => 'expression-cache-unit-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
    }

    /**
     * Creates an in-memory object representing the same model UID in each workbench.
     *
     * @param Workbench $workbench
     * @return MetaObject
     */
    private function object(Workbench $workbench): MetaObject
    {
        $object = new MetaObject(new Model($workbench));
        $object->setId('0x35370000000000000000000000000000');
        $object->setAlias('APP');
        $object->addAttribute(new Attribute($object, 'UID', 'UID'));
        return $object;
    }
}
