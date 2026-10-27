<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\Security\Authenticators\TOTP;

use exface\BarcodeScanner\BarcodeScannerApp;
use exface\Core\CommonLogic\Security\Authenticators\TOTP\LocalQRCodeProvider;
use exface\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;
use RobThree\Auth\TwoFactorAuth;

/**
 * Covers LocalQRCodeProvider and the robthree/twofactorauth v3 features used by TwoFactorTOTPAuthenticator.
 *
 * All codes are generated for fixed timestamps to keep the tests deterministic.
 */
class LocalQRCodeProviderTest extends TestCase
{
    private const QR_TEXT = 'otpauth://totp/My%20Server?secret=JBSWY3DPEHPK3PXP&issuer=&period=30&algorithm=SHA1&digits=6';

    // Middle of a 30s window, so +/- 30s always lands in the neighboring windows
    private const FIXED_TIME = 1700000025;

    // 80 bit secret as created by robthree/twofactorauth 1.x
    private const LEGACY_SECRET = 'JBSWY3DPEHPK3PXP';

    /**
     * Creates the library instance exactly like TwoFactorTOTPAuthenticator::getTFA() does.
     *
     * @return TwoFactorAuth
     */
    private function createTFA() : TwoFactorAuth
    {
        return new TwoFactorAuth(new LocalQRCodeProvider());
    }

    /**
     * Skips image tests if the optional app exface.BarcodeScanner is not installed in the test environment.
     *
     * @return void
     */
    private function skipIfBarcodeScannerMissing() : void
    {
        if (! class_exists(BarcodeScannerApp::class)) {
            $this->markTestSkipped('App exface.BarcodeScanner is not installed');
        }
    }

    /**
     * Without exface.BarcodeScanner there is no local encoder, so a clear error must tell users what to install.
     *
     * @return void
     */
    public function testGetQRCodeImageThrowsExceptionWithoutBarcodeScanner() : void
    {
        $provider = new class extends LocalQRCodeProvider {
            /**
             * Simulates an installation without exface.BarcodeScanner.
             *
             * @return bool
             */
            protected function isBarcodeScannerAvailable() : bool
            {
                return false;
            }
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('QR code image generation requires the app exface.BarcodeScanner. Please install it to use this feature.');
        $provider->getQRCodeImage(self::QR_TEXT, 200);
    }

    /**
     * With exface.BarcodeScanner installed the QR code must be rendered locally as SVG.
     *
     * @return void
     */
    public function testGetQRCodeImageReturnsSvg() : void
    {
        $this->skipIfBarcodeScannerMissing();
        $svg = (new LocalQRCodeProvider())->getQRCodeImage(self::QR_TEXT, 200);
        $this->assertNotEmpty($svg);
        $this->assertMatchesRegularExpression('/^(<\?xml|<svg)/', $svg);
        $this->assertStringContainsString('width="200" height="200"', $svg);
    }

    /**
     * The library builds data URIs from the provider output and MIME type, so both must fit together.
     *
     * @return void
     */
    public function testGetQRCodeImageAsDataUriReturnsSvgDataUri() : void
    {
        $this->skipIfBarcodeScannerMissing();
        $uri = $this->createTFA()->getQRCodeImageAsDataUri('My Server', self::LEGACY_SECRET);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
        $this->assertMatchesRegularExpression('/^(<\?xml|<svg)/', $svg);
    }

    /**
     * New secrets must be valid Base32 with the v3 default length of 160 bit (32 chars).
     *
     * @return void
     */
    public function testCreateSecretReturnsBase32SecretWith160Bit() : void
    {
        $secret = $this->createTFA()->createSecret();
        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    }

    /**
     * Secrets already stored for users by the 1.x library must keep working after the upgrade.
     *
     * @return void
     */
    public function testLegacy80BitSecretStillVerifies() : void
    {
        $tfa = $this->createTFA();
        $code = $tfa->getCode(self::LEGACY_SECRET, self::FIXED_TIME);
        $this->assertTrue($tfa->verifyCode(self::LEGACY_SECRET, $code, 1, self::FIXED_TIME));
    }

    /**
     * A code that does not match any window in the allowed discrepancy must be rejected.
     *
     * @return void
     */
    public function testWrongCodeIsRejected() : void
    {
        $tfa = $this->createTFA();
        $code = $tfa->getCode(self::LEGACY_SECRET, self::FIXED_TIME);
        $wrongCode = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        $this->assertFalse($tfa->verifyCode(self::LEGACY_SECRET, $wrongCode, 1, self::FIXED_TIME));
    }

    /**
     * Codes from the previous and next 30s window are accepted to compensate for clock drift on user devices.
     *
     * @return void
     */
    public function testNeighboringWindowsAreAccepted() : void
    {
        $tfa = $this->createTFA();
        $previous = $tfa->getCode(self::LEGACY_SECRET, self::FIXED_TIME - 30);
        $next = $tfa->getCode(self::LEGACY_SECRET, self::FIXED_TIME + 30);
        $this->assertTrue($tfa->verifyCode(self::LEGACY_SECRET, $previous, 1, self::FIXED_TIME));
        $this->assertTrue($tfa->verifyCode(self::LEGACY_SECRET, $next, 1, self::FIXED_TIME));
    }

    /**
     * Codes two windows away must be rejected, so a discrepancy of 1 really limits the accepted time range.
     *
     * @return void
     */
    public function testCodesTwoWindowsAwayAreRejected() : void
    {
        $tfa = $this->createTFA();
        $before = $tfa->getCode(self::LEGACY_SECRET, self::FIXED_TIME - 60);
        $after = $tfa->getCode(self::LEGACY_SECRET, self::FIXED_TIME + 60);
        $this->assertFalse($tfa->verifyCode(self::LEGACY_SECRET, $before, 1, self::FIXED_TIME));
        $this->assertFalse($tfa->verifyCode(self::LEGACY_SECRET, $after, 1, self::FIXED_TIME));
    }

    /**
     * The QR text is what the QrCode widget renders, so it must be a valid otpauth URI containing the secret.
     *
     * @return void
     */
    public function testGetQRTextReturnsOtpauthUriWithSecret() : void
    {
        $text = $this->createTFA()->getQRText('My Server', self::LEGACY_SECRET);
        $this->assertStringStartsWith('otpauth://totp/', $text);
        $this->assertStringContainsString(self::LEGACY_SECRET, $text);
    }
}
