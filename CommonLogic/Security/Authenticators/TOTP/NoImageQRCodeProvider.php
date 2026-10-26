<?php
namespace exface\Core\CommonLogic\Security\Authenticators\TOTP;

use exface\Core\Exceptions\RuntimeException;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;

/**
 * QR code provider for robthree/twofactorauth that deliberately does not generate images.
 * 
 * Since v3 the `TwoFactorAuth` constructor requires an `IQRCodeProvider`. The workbench only needs
 * the QR text (`getQRText()`), which is rendered by the `QrCode` widget in the browser. The providers
 * shipped with the library either call external web services (which would leak TOTP secrets) or
 * require additional dependencies, so this provider refuses to produce images at all.
 * 
 * @author Gizem Bicer
 */
class NoImageQRCodeProvider implements IQRCodeProvider
{
    /**
     * Always throws, so TOTP secrets can never be sent to an external QR service by accident.
     * 
     * {@inheritDoc}
     * @see \RobThree\Auth\Providers\Qr\IQRCodeProvider::getQRCodeImage()
     */
    public function getQRCodeImage(string $qrText, int $size): string
    {
        throw new RuntimeException('Server-side QR code images are not supported for TOTP: use the QR text with the QrCode widget instead!');
    }

    /**
     * Required by the interface - returns a neutral type as this provider never produces image data.
     * 
     * {@inheritDoc}
     * @see \RobThree\Auth\Providers\Qr\IQRCodeProvider::getMimeType()
     */
    public function getMimeType(): string
    {
        return 'text/plain';
    }
}
