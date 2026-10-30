<?php

namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\AppInstallers;

use exface\Core\CommonLogic\AppInstallers\ApacheServerInstaller;
use exface\Core\CommonLogic\AppInstallers\NginxServerInstaller;
use PHPUnit\Framework\TestCase;

/**
 * Protects vendor-root metadata without blocking assets in package subfolders.
 */
class ServerInstallerVendorSecurityTest extends TestCase
{
    /**
     * Checks the generated Apache rule without requiring the installation lifecycle.
     * 
     * @return void
     */
    public function testApacheDeniesOnlyDirectVendorChildren(): void
    {
        $reflection = new \ReflectionClass(ApacheServerInstaller::class);
        $installer = $reflection->newInstanceWithoutConstructor();
        $config = $reflection->getMethod('getContentSecurityRules')->invoke($installer);
        self::assertSame(1, preg_match('/^[\t ]*RewriteRule (\S+) - \[F,L,NC\]\r?$/m', $config, $matches));
        $this->assertVendorBoundary($matches[1], '');
    }

    /**
     * Checks nginx locations for root and subdirectory installations.
     * 
     * @return void
     */
    public function testNginxDeniesOnlyDirectVendorChildren(): void
    {
        $reflection = new \ReflectionClass(NginxServerInstaller::class);
        $installer = $reflection->newInstanceWithoutConstructor();
        foreach (['', 'workbench'] as $urlPath) {
            $config = $reflection->getMethod('buildConfigForLocation')->invoke($installer, $urlPath, '/srv/workbench');
            self::assertSame(1, preg_match('/location ~\* (\S+) \{ return 403; \}/', $config, $matches));
            $this->assertVendorBoundary($matches[1], '/' . ($urlPath === '' ? '' : $urlPath . '/'));
        }
    }

    /**
     * Keeps both IIS versions aligned on the same forbidden response and path boundary.
     * 
     * @return void
     */
    public function testIisTemplatesDenyOnlyDirectVendorChildren(): void
    {
        foreach (['default.Web.config', 'default8.Web.config'] as $template) {
            $document = new \DOMDocument();
            self::assertTrue($document->load(dirname(__DIR__, 5) . '/Install/' . $template));
            $xpath = new \DOMXPath($document);
            $rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[@name="files directly in vendor folder"]');
            self::assertSame(1, $rules->length);
            $rule = $rules->item(0);
            self::assertSame('true', $rule->getAttribute('stopProcessing'));
            self::assertSame('true', $xpath->evaluate('string(match/@ignoreCase)', $rule));
            self::assertSame('CustomResponse', $xpath->evaluate('string(action/@type)', $rule));
            self::assertSame('403', $xpath->evaluate('string(action/@statusCode)', $rule));
            $this->assertVendorBoundary($xpath->evaluate('string(match/@url)', $rule), '');
        }
    }

    /**
     * Exercises filenames independently of their extensions and preserves deeper paths.
     * 
     * @param string $pattern
     * @param string $prefix
     * @return void
     */
    private function assertVendorBoundary(string $pattern, string $prefix): void
    {
        foreach (['vendor/sbom.json', 'vendor/sbom.xml', 'vendor/autoload.php', 'vendor/LICENSE', 'VENDOR/SBOM.JSON', 'vendor/sbom.json?download=1'] as $path) {
            $requestPath = parse_url($prefix . $path, PHP_URL_PATH);
            self::assertSame(1, preg_match('~' . $pattern . '~i', $requestPath), $path);
        }
        foreach (['vendor/', 'vendor/package/', 'vendor/package/asset.css', 'vendor/exface/core/index.php', 'vendor-other/sbom.json', 'data/sbom.json'] as $path) {
            self::assertSame(0, preg_match('~' . $pattern . '~i', $prefix . $path), $path);
        }
    }
}