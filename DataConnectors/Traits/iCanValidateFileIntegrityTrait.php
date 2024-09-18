<?php

namespace exface\Core\DataConnectors\Traits;

use exface\Core\Exceptions\Filesystem\FileCorruptedError;
use exface\Core\Interfaces\TranslationInterface;

/**
 * This trait enables validating file integrity.
 *
 * When using this trait, adhere to the following best practices:
 * - Overwrite `guessMimeType()` with your local mime-type logic, if possible.
 * - Validate file integrity before you perform any writes.
 * - Use `validateFileIntegrityArray()`, if possible.
 * - Bookend the code where you perform your write actions with `tryBeginWriting()` and `tryFinishWriting()` respectively.
 *
 * ### Sample Code
 *
 * ```
 *
 *  //...
 *
 *  $filesToSave = // Gather save data in the form of [string $path => string $binaryData]
 *  $errors = $this->validateFileIntegrityArray($filesToSave);
 *
 *  $this->tryBeginWriting($errors);
 *  foreach($filesToSave as $path => $data) {
 *      // Perform write actions.
 *  }
 *  $this->tryFinishWriting($errors);
 *
 *  //...
 *
 * ```
 *
 */
trait ICanValidateFileIntegrityTrait
{
    protected bool $validateFileIntegrity = false;

    protected bool $validateChecksums = false;

    protected bool $keepCorruptedFiles = false;

    private ?TranslationInterface $translator = null;

    /**
     * @return TranslationInterface
     */
    private function getTranslator() : TranslationInterface
    {
        if(!isset($this->translator)) {
            $this->translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        }

        return $this->translator;
    }

    /**
     * Toggle whether this connector should validate file integrity.
     *
     * Default value is FALSE.
     *
     * @uxon-poperty validate_file_integrity
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param $value
     * @return $this
     */
    public function setValidateFileIntegrity($value): static
    {
        if(!isset($value)) {
            $this->validateFileIntegrity = false;
        } else {
            $this->validateFileIntegrity = (bool)$value;
        }

        return $this;
    }

    /**
     * Toggle whether this connector should validate checksums.
     *
     * A checksum is calculated both when the file is first uploaded to the server
     * and right after it has been stored in the filesystem. Comparing both values
     * verifies, whether parts of the file went missing.
     *
     * Default value is FALSE.
     *
     * @uxon-poperty validate_checksums
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param $value
     * @return $this
     */
    public function setValidateChecksums($value): static
    {
        if(!isset($value)) {
            $this->validateChecksums = false;
        } else {
            $this->validateChecksums = (bool)$value;
        }

        return $this;
    }

    /**
     * Toggle whether this connector should keep corrupted files.
     *
     * If either checksum or file validation fails, the upload will be rejected
     * by the system to prevent faulty evidence from entering the database. If this
     * setting is TRUE these files will still be stored on the server, but without a
     * reference in the database. This allows technicians to restore the data later
     * if needed or to use it to track down points of failure.
     *
     * Default value is FALSE.
     *
     * @uxon-poperty keep_corrupted_files
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param $value
     * @return $this
     */
    public function setKeepCorruptedFiles($value): static
    {
        if(!isset($value)) {
            $this->keepCorruptedFiles = false;
        } else {
            $this->keepCorruptedFiles = (bool)$value;
        }

        return $this;
    }

    /**
     * Guess the mime-type of a given file. The default implementation uses finfo.
     *
     * NOTE: To ensure that the result is consistent with your filesystem you
     * should override this method with your local mime-type logic.
     *
     * @param string $path
     * @param string $data
     * @return string
     */
    protected function guessMimeType(string $path, string $data) : string
    {
        $finfo = finfo_open(FILEINFO_MIME);
        if(!$mime = finfo_file($finfo, $path)) {
            $mime = finfo_buffer($finfo, $data);
        }
        finfo_close($finfo);

        return $mime;
    }

    /**
     * Verify validation results and throw an error if appropriate.
     *
     * Call this method BEFORE any files have been written to the filesystem.
     * This is a convenience method.
     *
     * @param array $errors
     * @return void
     */
    protected function tryBeginWriting(array $errors) : void
    {
        // If corrupted files have been detected and storing them has been disabled, we can throw an early error.
        if(!empty($errors) && !$this->keepCorruptedFiles) {
            throw new FileCorruptedError($this->buildErrorMessage($errors));
        }
    }

    /**
     * Verify validation results and throw an error if appropriate.
     *
     * Call this method AFTER all files have been written to the filesystem.
     * This is a convenience method.
     *
     * @param array $errors
     * @return void
     */
    protected function tryFinishWriting(array $errors) : void
    {
        // If corrupted files have been detected, throw an error.
        // Note that the files have been written to the filesystem by now.
        if(!empty($errors)) {
            throw new FileCorruptedError($this->buildErrorMessage($errors));
        }
    }

    /**
     * Builds one coherent error message out of all the errors passed.
     *
     * @param array $errors
     * Array items should have the following structure: `[string $path => FileCorruptedError $error]`
     * @return string
     */
    protected function buildErrorMessage(array $errors) : string
    {
        $number = 1;
        $message = PHP_EOL.PHP_EOL.$this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_HEADER');
        foreach ($errors as $path => $error) {
            $message .= PHP_EOL.($number == 1 || $this->keepCorruptedFiles ? PHP_EOL : '');
            $message .= $number.'.: '.$error->getMessage();
            if($this->keepCorruptedFiles) {
                $backupPath = $error->getFileInfo() ? $error->getFileInfo()->getPathAbsolute() : $path;
                $message.= PHP_EOL.'--- '.$this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_DESTINATION', ["%path%" => $backupPath]);
            }
            $number++;
        }

        if(!$this->keepCorruptedFiles) {
            $message .= PHP_EOL.PHP_EOL.$this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_FOOTER');
        }
        return $message;
    }

    /**
     * Validates the integrity of the provided data.
     *
     * NOTE: If no validation for a given mime-type exists, the validation
     * will be considered to be successful.
     *
     * @param string $path
     * @param string $binaryData
     * @param string|null $mimeType
     * @return void
     */
    protected function validateFileIntegrity(string $path, string $binaryData, string $mimeType = null) : void
    {
        if(!$this->validateFileIntegrity) {
            return;
        }

        if($mimeType === null) {
            $mimeType = $this->guessMimeType($path, $binaryData);
        }

        [$superType,] = explode('/', $mimeType);
        switch ($superType) {
            case 'image':
                $this->internalValidateMimeImage($path, $binaryData);
                return;
            case null :
                $msg = $this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_MIMETYPE', ["%mimetypes%" => $superType, "%path%" => $path]);
                throw new FileCorruptedError($msg);
        }
    }

    /**
     * Validates all files in the given array and returns an array with all errors encountered.
     *
     * @param array $filesToValidate
     * Array items should have the following structure: `[string $path => string $binaryData]`
     * @return array
     */
    protected function validateFileIntegrityArray(array $filesToValidate) : array
    {
        if(!$this->validateFileIntegrity) {
            return [];
        }

        $errors = [];
        foreach ($filesToValidate as $path => $data) {
            try {
                $this->validateFileIntegrity($path, $data);
            } catch (FileCorruptedError $e) {
                $errors[$path] = $e;
            }
        }

        return $errors;
    }

    /**
     * Internal function to validate image files.
     *
     * DO NOT CALL DIRECTLY.
     *
     * @param string $path
     * @param string $binaryData
     * @return void
     */
    private function internalValidateMimeImage(string $path, string $binaryData) : void
    {
        if (!$img = imagecreatefromstring($binaryData)) {
            // Free memory.
            $img = null;
            unset($img);

            $pathComponents = explode('/', $path);
            $msg = $this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED', ["%path%" => end($pathComponents)]);
            throw new FileCorruptedError($msg,null, null);
        } else {
            // Free memory.
            $img = null;
            unset($img);
        }
    }
}