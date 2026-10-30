<?php

namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\AppInstallers;

use exface\Core\CommonLogic\AppInstallers\ApacheServerInstaller;
use exface\Core\CommonLogic\AppInstallers\NginxServerInstaller;
use PHPUnit\Framework\TestCase;

/**
 * Guards hidden directories and their descendants without blocking ordinary dotted names.
 */
class ServerInstallerHiddenPathsTest extends TestCase
{
    /**
     * Checks each server's generated deny pattern without requiring a running web server.
     * 
     * @return void
     */
    public function testHiddenPathsAreDeniedAtEveryDepth(): void
    {
        $patterns = [];
        $reflection = new \ReflectionClass(ApacheServerInstaller::class);
        $config = $reflection->getMethod('getContentSecurityRules')->invoke($reflection->newInstanceWithoutConstructor());
        self::assertSame(1, preg_match('/# Block dot-prefixed[^\r\n]*\r?\n[\t ]*RewriteRule (\S+) - \[F,L,NC\]/', $config, $matches));
        $patterns['Apache'] = [$matches[1], ''];

        $reflection = new \ReflectionClass(NginxServerInstaller::class);
        foreach (['', 'workbench'] as $urlPath) {
            $config = $reflection->getMethod('buildConfigForLocation')->invoke($reflection->newInstanceWithoutConstructor(), $urlPath, '/srv/workbench');
            self::assertSame(1, preg_match('/location ~ (\S+) \{\s+deny all;/', $config, $matches));
            $patterns['nginx/' . $urlPath] = [$matches[1], '/' . ($urlPath === '' ? '' : $urlPath . '/')];
        }

        foreach (['default.Web.config', 'default8.Web.config'] as $template) {
            $document = new \DOMDocument();
            self::assertTrue($document->load(dirname(__DIR__, 5) . '/Install/' . $template));
            $xpath = new \DOMXPath($document);
            $rule = $xpath->query('/configuration/system.webServer/rewrite/rules/rule')->item(0);
            self::assertSame('dot-prefixed files and folders', $rule->getAttribute('name'));
            self::assertSame('true', $rule->getAttribute('stopProcessing'));
            self::assertSame('CustomResponse', $xpath->evaluate('string(action/@type)', $rule));
            self::assertSame('403', $xpath->evaluate('string(action/@statusCode)', $rule));
            $patterns[$template] = [$xpath->evaluate('string(match/@url)', $rule), ''];
        }

        foreach ($patterns as $server => [$pattern, $prefix]) {
            foreach (['.git', '.git/', '.git/config', 'vendor/package/.github/workflows/test.yml', 'data/nested/.private/file.txt', 'api/.hidden/file', '.env', 'data/.secret'] as $path) {
                self::assertSame(1, preg_match('~' . $pattern . '~', $prefix . $path), $server . ': ' . $path);
            }
            foreach (['vendor/package/asset.min.js', 'data/file.txt', 'folder.with.dots/file.txt', 'api/example.route', 'vendor/exface/core/index.php'] as $path) {
                self::assertSame(0, preg_match('~' . $pattern . '~', $prefix . $path), $server . ': ' . $path);
            }
        }
    }
}