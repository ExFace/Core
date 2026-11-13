<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\Filesystem;

use exface\Core\Behaviors\FileBehavior;
use exface\Core\CommonLogic\Filesystem\DataSourceFileInfo;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\FileDownloadConnectionInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\UrlDataConnector\DataConnectors\HttpConnector;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Covers explicit URL contents and the unchanged stored-content path.
 */
class DataSourceFileInfoContentsTest extends TestCase
{
    public function testStoredContentsStillUseTheirAttribute() : void
    {
        $this->assertSame("\x00stored\xff", $this->createFile("\x00stored\xff")->getContent());
    }

    public function testUrlStringsAreNotDownloadedByDefault() : void
    {
        $url = 'https://files.example.test/api/file/123';
        $this->assertSame($url, $this->createFile($url)->getContent());
    }

    public function testUrlModeRequiresANonemptyLink() : void
    {
        $this->expectException(FileNotReadableError::class);
        $this->expectExceptionMessage('nonempty download URL');

        $this->createFile('', true)->getContent();
    }

    public function testUrlModeDownloadsThroughTheObjectConnection() : void
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('read')->willReturn('downloaded');
        $connection = $this->createMock(FileDownloadConnectionInterface::class);
        $connection->expects($this->once())->method('downloadFile')
            ->with('file/123', 'report.png', 'image/png', 5 * 1024 * 1024)
            ->willReturn($file);

        $this->assertSame('downloaded', $this->createFile('file/123', true, $connection)->getContent());
    }

    public function testUrlModeRequiresADownloadCapableConnection() : void
    {
        $this->expectException(FileNotReadableError::class);
        $this->expectExceptionMessage('supports file downloads');

        $this->createFile('file/123', true, $this->createStub(DataConnectionInterface::class))->getContent();
    }

    public function testHttpConnectorDownloadsThroughItsRequest() : void
    {
        if (! class_exists(HttpConnector::class)) {
            $this->markTestSkipped('URL contents require UrlDataConnector.');
        }
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0u0AAAAASUVORK5CYII=');
        $connection = $this->getMockBuilder(HttpConnector::class)
            ->disableOriginalConstructor()->onlyMethods(['sendRequest', 'getUrl'])->getMock();
        $connection->expects($this->once())->method('getUrl')->willReturn('https://files.example.test/api/');
        $connection->expects($this->once())->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request, array $options) use ($contents) {
                $this->assertSame('file/123', (string) $request->getUri());
                $this->assertSame('*/*', $request->getHeaderLine('Accept'));
                $this->assertFalse($options['allow_redirects']);
                $response = new Response(200, ['Content-Type' => 'image/png'], $contents);
                $options['on_headers']($response);
                $options['sink']->write($contents);
                return $response;
            });

        $this->assertSame($contents, $this->createFile('file/123', true, $connection)->getContent());
    }

    public function testAbsoluteUrlUsesTheConfiguredConnectionBase() : void
    {
        if (! class_exists(HttpConnector::class)) {
            $this->markTestSkipped('URL contents require UrlDataConnector.');
        }
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0u0AAAAASUVORK5CYII=');
        $connection = $this->getMockBuilder(HttpConnector::class)->disableOriginalConstructor()
            ->onlyMethods(['sendRequest', 'getUrl'])->getMock();
        $connection->expects($this->once())->method('getUrl')->willReturn('https://files.example.test/api/');
        $connection->expects($this->once())->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request, array $options) use ($contents) {
                $this->assertSame('file/123?download=1', (string) $request->getUri());
                $response = new Response(200, ['Content-Type' => 'image/png'], $contents);
                $options['on_headers']($response);
                $options['sink']->write($contents);
                return $response;
            });

        $url = 'https://files.example.test/api/file/123?download=1';
        $this->assertSame($contents, $this->createFile($url, true, $connection)->getContent());
    }

    public function testUrlsCannotEscapeTheConfiguredConnection() : void
    {
        if (! class_exists(HttpConnector::class)) {
            $this->markTestSkipped('URL contents require UrlDataConnector.');
        }
        foreach ([
            'https://other.example.test/api/file/123',
            'http://files.example.test/api/file/123',
            'https://files.example.test:8443/api/file/123',
            'https://files.example.test/private/file/123',
            '../private/file/123',
            '%2e%2e/private/file/123'
        ] as $url) {
            $connection = $this->getMockBuilder(HttpConnector::class)->disableOriginalConstructor()
                ->onlyMethods(['sendRequest', 'getUrl'])->getMock();
            $connection->expects($this->once())->method('getUrl')->willReturn('https://files.example.test/api/');
            $connection->expects($this->never())->method('sendRequest');
            try {
                $this->createFile($url, true, $connection)->getContent();
                $this->fail('A URL outside the configured connection must not be downloaded.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('configured HTTP connection', $error->getMessage());
            }
        }
    }

    public function testUrlModeHonorsTheConfiguredByteLimit() : void
    {
        if (! class_exists(HttpConnector::class)) {
            $this->markTestSkipped('URL contents require UrlDataConnector.');
        }
        $connection = $this->getMockBuilder(HttpConnector::class)
            ->disableOriginalConstructor()->onlyMethods(['sendRequest', 'getUrl'])->getMock();
        $connection->expects($this->once())->method('getUrl')->willReturn('https://files.example.test/api/');
        $connection->expects($this->once())->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request, array $options) {
                $response = new Response(200);
                $options['on_headers']($response);
                $options['sink']->write('too large');
                return $response;
            });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Download exceeded its byte limit.');

        $this->createFile('file/123', true, $connection, 1 / 1048576)->getContent();
    }

    /**
     * Builds local content-reading collaborators without a Workbench or database.
     *
     * @param string $value
     * @param bool $urlMode
     * @param DataConnectionInterface|null $connection
     * @param float|null $maxSizeMb
     * @return DataSourceFileInfo
     */
    private function createFile(string $value, bool $urlMode = false, ?DataConnectionInterface $connection = null, ?float $maxSizeMb = null) : DataSourceFileInfo
    {
        $attribute = $this->createStub(MetaAttributeInterface::class);
        $attribute->method('getDataType')->willReturn($this->createStub(DataTypeInterface::class));
        $behavior = $this->getMockBuilder(FileBehavior::class)->disableOriginalConstructor()
            ->onlyMethods(['getContentsAttribute', 'getMaxFileSizeInMb'])->getMock();
        $behavior->expects($this->once())->method('getContentsAttribute')->willReturn($attribute);
        $behavior->method('getMaxFileSizeInMb')->willReturn($maxSizeMb);
        $setter = new \ReflectionMethod(FileBehavior::class, 'setContentsAreUrls');
        $setter->invoke($behavior, $urlMode);
        $column = $this->createMock(DataColumnInterface::class);
        $column->expects($this->once())->method('getValue')->with(0)->willReturn($value);
        $file = $this->getMockBuilder(DataSourceFileInfo::class)->disableOriginalConstructor()
            ->onlyMethods(['getFileBehavior', 'getFileDataColumn', 'getMetaObject', 'getFilename', 'getMimetype'])->getMock();
        $file->method('getFileBehavior')->willReturn($behavior);
        $file->expects($this->once())->method('getFileDataColumn')->with($attribute)->willReturn($column);
        $file->method('getFilename')->willReturn('report.png');
        $file->method('getMimetype')->willReturn('image/png');
        if ($connection !== null) {
            $object = $this->createStub(MetaObjectInterface::class);
            $object->method('getDataConnection')->willReturn($connection);
            $file->method('getMetaObject')->willReturn($object);
        } else {
            $file->expects($this->never())->method('getMetaObject');
        }
        return $file;
    }
}