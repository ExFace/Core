<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError;
use exface\Core\Templates\Placeholders\SelectivePlaceholders;
use exface\Core\Templates\Placeholders\SystemVariablePlaceholders;
use PHPUnit\Framework\TestCase;

/**
 * Covers server variable resolution without a Workbench or web server.
 */
class SystemVariablePlaceholdersTest extends TestCase
{
    private array $server;

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp() : void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $_SERVER = [
            'EXFACE_TEST_SYSTEM_VALUE' => 'configured-value',
            'EXFACE_TEST_SYSTEM_EMPTY' => '',
            'EXFACE_TEST_SYSTEM_ZERO' => '0'
        ];
    }

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::tearDown()
     */
    protected function tearDown() : void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    /**
     * System placeholders must use server values and ignore other prefixes.
     *
     * @return void
     */
    public function testResolvesServerValuesOnly() : void
    {
        $resolver = new SystemVariablePlaceholders();
        $this->assertSame(['~system:EXFACE_TEST_SYSTEM_VALUE' => 'configured-value'], $resolver->resolve([
            '~system:EXFACE_TEST_SYSTEM_VALUE',
            '~env:EXFACE_TEST_SYSTEM_VALUE',
            'EXFACE_TEST_SYSTEM_VALUE'
        ]));
    }

    /**
     * Empty and zero values must not be treated as missing variables.
     *
     * @return void
     */
    public function testPreservesEmptyAndZeroValues() : void
    {
        $resolver = new SystemVariablePlaceholders();
        $this->assertSame([
            '~system:EXFACE_TEST_SYSTEM_EMPTY' => '',
            '~system:EXFACE_TEST_SYSTEM_ZERO' => '0'
        ], $resolver->resolve(['~system:EXFACE_TEST_SYSTEM_EMPTY', '~system:EXFACE_TEST_SYSTEM_ZERO']));
    }

    /**
     * A missing server variable must fail explicitly.
     *
     * @return void
     */
    public function testMissingVariableRaisesError() : void
    {
        $this->expectException(PlaceholderValueInvalidError::class);
        $this->expectExceptionMessage('Could not find server variable "EXFACE_TEST_SYSTEM_MISSING"');
        (new SystemVariablePlaceholders())->resolve(['~system:EXFACE_TEST_SYSTEM_MISSING']);
    }

    /**
     * Variable names must retain their case-sensitive meaning.
     *
     * @return void
     */
    public function testVariableNamesAreCaseSensitive() : void
    {
        $this->expectException(PlaceholderValueInvalidError::class);
        $this->expectExceptionMessage('exface_test_system_value');
        (new SystemVariablePlaceholders())->resolve(['~system:exface_test_system_value']);
    }

    /**
     * Custom prefixes must work without resolving the default prefix.
     *
     * @return void
     */
    public function testSupportsCustomPrefix() : void
    {
        $resolver = new SystemVariablePlaceholders('~server:');
        $this->assertSame(['~server:EXFACE_TEST_SYSTEM_VALUE' => 'configured-value'], $resolver->resolve([
            '~server:EXFACE_TEST_SYSTEM_VALUE',
            '~system:EXFACE_TEST_SYSTEM_VALUE'
        ]));
    }

    /**
     * Resolution must read the current request values rather than a constructor snapshot.
     *
     * @return void
     */
    public function testReadsCurrentServerValues() : void
    {
        $resolver = new SystemVariablePlaceholders();
        $_SERVER['EXFACE_TEST_SYSTEM_VALUE'] = 'updated-value';
        $this->assertSame(['~system:EXFACE_TEST_SYSTEM_VALUE' => 'updated-value'], $resolver->resolve([
            '~system:EXFACE_TEST_SYSTEM_VALUE'
        ]));
    }

    /**
     * Selective resolution must retain unrelated placeholders for later processing.
     *
     * @return void
     */
    public function testWorksInSelectivePlaceholderGroup() : void
    {
        $resolver = new SelectivePlaceholders([new SystemVariablePlaceholders()]);
        $this->assertSame([
            '~system:EXFACE_TEST_SYSTEM_VALUE' => 'configured-value',
            '~other:value' => '[#~other:value#]'
        ], $resolver->resolve(['~system:EXFACE_TEST_SYSTEM_VALUE', '~other:value']));
    }
}
