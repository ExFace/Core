<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\Exceptions\FormulaError;

/**
 * Builds a data URI from a binary and a mime type.
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class DataURI extends Formula
{

    /**
     * 
     * @param string $binary
     * @param string $mimeType
     * @param string $binaryEncoding
     * 
     * @throws FormulaError
     * 
     * @return string
     */
    public function run(string $binary = null, string $mimeType = null, string $binaryEncoding = BinaryDataType::ENCODING_HEX)
    {
        if ($binary === null || $binary === '') {
            return $binary;
        }
        
        switch ($binaryEncoding) {
            case BinaryDataType::ENCODING_BINARY:
                $base64 = BinaryDataType::convertBinaryToBase64($binary);
                break;
            case BinaryDataType::ENCODING_BASE64:
                $base64 = $binary;
                break;
            case BinaryDataType::ENCODING_HEX:
                $base64 = BinaryDataType::convertHexToBase64($binary);
                break;
            default:
                throw new FormulaError('Invalid value for 3d parameter of DataURI() formula: use `binary`, `base64` or `hex`!');
        }
        
        return BinaryDataType::convertBase64ToDataUri($base64, $mimeType);
    }
}