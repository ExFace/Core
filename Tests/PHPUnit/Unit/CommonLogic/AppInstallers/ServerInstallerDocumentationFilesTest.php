<?php

namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\AppInstallers;

use exface\Core\CommonLogic\AppInstallers\ApacheServerInstaller;
use exface\Core\CommonLogic\AppInstallers\NginxServerInstaller;
use PHPUnit\Framework\TestCase;

/**
 * Prevents vendor documentation exposure without blocking application pages or package assets.
 */
class ServerInstallerDocumentationFilesTest extends TestCase
{
    /**
     * Checks each server's documentation restriction independently of a running web server.
     * 
     * @return void
     */
    public function testVendorHtmlAndMarkdownFilesAreDenied(): void
    {
        $patterns = [];
        $reflection = new \ReflectionClass(ApacheServerInstaller::class);
        $config = $reflection->getMethod('getContentSecurityRules')->invoke($reflection->newInstanceWithoutConstructor());
        self::assertSame(1, preg_match('/# Block direct access[^\r\n]*\r?\n[\t ]*RewriteRule (\S+) - \[F,L,NC\]/', $config, $matches));
        $patterns['Apache'] = [$matches[1], ''];

        $reflection = new \ReflectionClass(NginxServerInstaller::class);
        foreach (['', 'workbench'] as $urlPath) {
            $config = $reflection->getMethod('buildConfigForLocation')->invoke($reflection->newInstanceWithoutConstructor(), $urlPath, '/srv/workbench');
            self::assertSame(1, preg_match('/location ~\* (\S+) \{ return 404; \}/', $config, $matches));
            $patterns['nginx/' . $urlPath] = [$matches[1], '/' . ($urlPath === '' ? '' : $urlPath . '/')];
        }

        foreach (['default.Web.config', 'default8.Web.config'] as $template) {
            $document = new \DOMDocument();
            self::assertTrue($document->load(dirname(__DIR__, 5) . '/Install/' . $template));
            $xpath = new \DOMXPath($document);
            $rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[@name="HTML and Markdown files in vendor folder"]');
            self::assertSame(1, $rules->length);
            $rule = $rules->item(0);
            self::assertSame('true', $rule->getAttribute('stopProcessing'));
            self::assertSame('true', $xpath->evaluate('string(match/@ignoreCase)', $rule));
            self::assertSame('CustomResponse', $xpath->evaluate('string(action/@type)', $rule));
            self::assertSame('403', $xpath->evaluate('string(action/@statusCode)', $rule));
            $patterns[$template] = [$xpath->evaluate('string(match/@url)', $rule), ''];
        }

        foreach ($patterns as $server => [$pattern, $prefix]) {
            foreach (['vendor/package/README.md', 'vendor/package/docs/page.htm', 'vendor/package/docs/page.html', 'vendor/package/README.MD', 'VENDOR/package/page.HTM', 'vendor/package/page.HTML', 'vendor/package/README.md?download=1'] as $path) {
                $requestPath = parse_url($prefix . $path, PHP_URL_PATH);
                self::assertSame(1, preg_match('~' . $pattern . '~i', $requestPath), $server . ': ' . $path);
            }
            foreach (['vendor/package/asset.css', 'vendor/package/script.js', 'vendor/package/README.md.txt', 'vendor/package/html', 'vendor/package/md', 'page.html', 'data/document.md', 'vendor-other/page.htm'] as $path) {
                self::assertSame(0, preg_match('~' . $pattern . '~i', $prefix . $path), $server . ': ' . $path);
            }
        }
    }
}