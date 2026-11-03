<?php
namespace exface\Core\CommonLogic\Security\Authenticators\TOTP;

use exface\BarcodeScanner\DataTypes\BarcodeDataType;
use exface\Core\Exceptions\RuntimeException;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;

/**
 * QR code provider for robthree/twofactorauth that renders QR codes locally as SVG.
 * 
 * Since v3 the `TwoFactorAuth` constructor requires an `IQRCodeProvider`. The providers shipped with
 * the library either call external web services (which would leak TOTP secrets) or require additional
 * dependencies. This provider uses the QR encoder of the app `exface.BarcodeScanner` if it is installed.
 * The app is an optional runtime dependency - Core does not require it in its composer.json.
 * 
 * @author Gizem Bicer
 */
class BarcodeScannerQRCodeProvider implements IQRCodeProvider
{    
    /**
     * Renders the QR code locally, so TOTP secrets never leave the server.
     * 
     * {@inheritDoc}
     * @see \RobThree\Auth\Providers\Qr\IQRCodeProvider::getQRCodeImage()
     */
    public function getQRCodeImage($qrText, $size): string
    {
        if (! $this->isBarcodeScannerAvailable()) {
            throw new RuntimeException('QR code image generation requires the app exface.BarcodeScanner. Please install it to use this feature.');
        }
        return BarcodeDataType::generateBarcodeImage(BarcodeDataType::QR, $qrText, $size);
    }

    /**
     * Checks the optional runtime dependency exface.BarcodeScanner - separate method, so tests can override it.
     * 
     * @return bool
     */
    protected function isBarcodeScannerAvailable() : bool
    {
        return class_exists(BarcodeDataType::class);
    }

    /**
     * Required by the interface to build data URIs - images produced by this provider are always SVG.
     * 
     * {@inheritDoc}
     * @see \RobThree\Auth\Providers\Qr\IQRCodeProvider::getMimeType()
     */
    public function getMimeType(): string
    {
        return 'image/svg+xml';
    }
}