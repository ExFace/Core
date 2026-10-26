<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\Security\Authenticators\TOTP;

use exface\Core\CommonLogic\Security\Authenticators\TOTP\NoImageQRCodeProvider;
use exface\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;
use RobThree\Auth\TwoFactorAuth;

/**
 * Covers the robthree/twofactorauth v3 features used by TwoFactorTOTPAuthenticator.
 *
 * All codes are generated for fixed timestamps to keep the tests deterministic.
 */
class NoImageQRCodeProviderTest extends TestCase
{
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
        return new TwoFactorAuth(new NoImageQRCodeProvider());
    }

    /**
     * Makes sure TOTP secrets can never be rendered into images by a server-side or external QR service.
     *
     * @return void
     */
    public function testGetQRCodeImageThrowsException() : void
    {
        $this->expectException(RuntimeException::class);
        (new NoImageQRCodeProvider())->getQRCodeImage('otpauth://totp/test?secret=' . self::LEGACY_SECRET, 200);
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
