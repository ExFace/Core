<?php
namespace exface\Core\CommonLogic\Security\Authenticators\TOTP;

use exface\BarcodeScanner\BarcodeScannerApp;
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
class LocalQRCodeProvider implements IQRCodeProvider
{
    const ERROR_BARCODE_SCANNER_MISSING = 'QR code image generation requires the app exface.BarcodeScanner. Please install it to use this feature.';
    
    const QR_SYMBOLOGY = 'qr';
    
    /**
     * Renders the QR code locally, so TOTP secrets never leave the server.
     * 
     * {@inheritDoc}
     * @see \RobThree\Auth\Providers\Qr\IQRCodeProvider::getQRCodeImage()
     */
    public function getQRCodeImage(string $qrText, int $size): string
    {
        if (! $this->isBarcodeScannerAvailable()) {
            throw new RuntimeException(self::ERROR_BARCODE_SCANNER_MISSING);
        }
        // barcode.php declares a global class, that PSR-4 autoloading cannot find
        if (! class_exists('barcode_generator')) {
            require_once $this->getBarcodeGeneratorPath();
        }
        $generator = new \barcode_generator();
        return $generator->render_svg(self::QR_SYMBOLOGY, $qrText, ['w' => $size, 'h' => $size]);
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
    
    /**
     * Checks if the optional app exface.BarcodeScanner can be used - separate method, so tests can override it.
     * 
     * @return bool
     */
    protected function isBarcodeScannerAvailable() : bool
    {
        return class_exists(BarcodeScannerApp::class) && is_file($this->getBarcodeGeneratorPath());
    }
    
    /**
     * Locates barcode.php relative to the app class, so it works regardless of the installation folder layout.
     * 
     * @return string
     */
    protected function getBarcodeGeneratorPath() : string
    {
        $appFolder = dirname((new \ReflectionClass(BarcodeScannerApp::class))->getFileName());
        return $appFolder . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'barcode.php';
    }
}
