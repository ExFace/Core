<?php

namespace exface\Core\DataConnectors\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Filesystem\FileCorruptedError;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\TranslationInterface;

/**
 * This trait enables validating file integrity.
 *
 * When using this trait, adhere to the following best practices:
 * - Overwrite `guessMimeType()` with your local mime-type logic, if possible.
 * - Use `validateFileIntegrityArray()`, if possible.
 * - Bookend the code where you perform your write actions with `validateBeforeWriting()` and `validateAfterWriting()` respectively.
 *
 * ### Sample Code
 *
 * ```
 *
 *  //...
 *
 *  $filesToSave = // Gather save data in the form of [string $path => string $binaryData]
 *
 *  $this->validateBeforeWriting($filesToSave);
 *  foreach($filesToSave as $path => $data) {
 *      // Perform write actions.
 *  }
 *  $this->validateAfterWriting($filesToSave);
 *
 *  //...
 *
 * ```
 *
 */
// TODO geb 204-09-23: We should probably turn this trait into a class at some point.
trait ICanValidateFileIntegrityTrait
{
    protected bool $backupCorruptedFiles = false;

    protected bool $validationSettingDefault = true;

    private ?TranslationInterface $translator = null;

    private array $configPreWrite = [];

    private array $configPostWrite = [];

    // TODO geb 24-09-24: This should be a constant, but traits don't allow constants.
    private array $allConfigOptions = [
        'validate_checksums',
        'parse_images',
        'parse_pdf'
    ];

    private array $errors = [];

    private array $checkSumsMd5 = [];

    /**
     * @param bool $beforeWriting
     * @return array
     */
    public function getValidationSettings(bool $beforeWriting) : array
    {
        return $beforeWriting ? $this->configPreWrite : $this->configPostWrite;
    }

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
     * Set the value that validation settings should default to, if they are not set.
     *
     * Default value is TRUE.
     *
     * @uxon-poperty validation_setting_default
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return $this
     */
    public function setValidationSettingDefault(bool $value) : static
    {
        $this->validationSettingDefault = $value;
        return $this;
    }

    /**
     * Get the value that validation settings should default to, if they are not set.
     *
     * @return bool
     */
    public function getValidationSettingDefault() : bool
    {
        return $this->validationSettingDefault;
    }

    /**
     * Configure the settings that this connector should use when validating BEFORE writing.
     *
     * All options are TRUE by default.
     *
     * @uxon-poperty validations_before_writing
     * @uxon-type object
     * @uxon-default validations_before_writing: {'parse_images': true, 'parse_pdf': true, 'validate_checksums': false,}
     *
     * @param UxonObject $value
     * @return $this
     */
    public function setValidationsBeforeWriting(UxonObject $value): static
    {
        $this->configPreWrite = $value->toArray();
        return $this;
    }

    /**
     * Configure the settings that this connector should use when validating BEFORE writing.
     *
     * All options are TRUE by default.
     *
     * @uxon-poperty validations_after_writing
     * @uxon-type object
     * @uxon-default validations_after_writing: {'parse_images': false, 'parse_pdf': false, 'validate_checksums': true,}
     *
     * @param UxonObject $value
     * @return $this
     */
    public function setValidationsAfterWriting(UxonObject $value): static
    {
        $this->configPostWrite = $value->toArray();
        return $this;
    }

    /**
     * Toggle whether this connector should back up corrupted files.
     *
     * If either checksum or file validation fails, the upload will be rejected
     * by the system to prevent faulty evidence from entering the database. If this
     * setting is TRUE these files will still be stored on the server, but without a
     * reference in the database. This allows technicians to restore the data later
     * if needed or to use it to track down points of failure.
     *
     * Default value is FALSE.
     *
     * @uxon-poperty backup_corrupted_files
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param $value
     * @return $this
     */
    public function setBackupCorruptedFiles($value): static
    {
        if(!isset($value)) {
            $this->backupCorruptedFiles = false;
        } else {
            $this->backupCorruptedFiles = (bool)$value;
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
     * Validate files before writing and throw an error if appropriate.
     *
     * Call this method BEFORE any files have been written to the filesystem.
     *
     * @param array $filesToValidate
     * Array items should have the following structure: `[string $path => mixed $data]`. The data portion may either be
     * a string of binary data or an instance of FileInfoInterface.
     * @param array|null $checkSumsMd5
     * Optional array of MD5 hashes for each file. During this step, checksum validation is only possible if you provide
     * MD5 hashes for each file you wish to validate this way. Array items should have the following structure:
     * `[string $path => string $md5]`
     * @return void
     */
    protected function validateBeforeWriting(array $filesToValidate, array $checkSumsMd5 = null) : void
    {
        $this->errors = $this->validateArray($filesToValidate, $checkSumsMd5, $this->configPreWrite);

        // If corrupted files have been detected and storing them has been disabled, we can throw an early error.
        if(!empty($this->errors) && !$this->backupCorruptedFiles) {
            throw $this->renderAllErrors($this->errors);
        }

        // Calculate and cache new checksums for validation after writing.
        if($this->isValidationRequired($this->configPostWrite, ['validate_checksums'])) {
            $this->checkSumsMd5 = $this->calculateCheckSumsMd5($filesToValidate);
        }

    }

    /**
     * Validate files after writing and throw an error if appropriate.
     *
     * Call this method AFTER all files have been written to the filesystem.
     *
     * @param array $filesToValidate
     *  Array items should have the following structure: `[string $path => mixed $data]`. The data portion may either be
     *  a string of binary data or an instance of FileInfoInterface.
     * @return void
     */
    protected function validateAfterWriting(array $filesToValidate) : void
    {
        $this->errors = array_merge($this->errors, $this->validateArray($filesToValidate, $this->checkSumsMd5, $this->configPostWrite));
        $this->checkSumsMd5 = [];

        // If corrupted files have been detected, throw an error.
        // Note that the files have been written to the filesystem by now.
        if(!empty($this->errors)) {
            throw $this->renderAllErrors($this->errors);
        }
    }

    /**
     * Validates all files in the given array and returns an array with all errors encountered.
     *
     * @param array $filesToValidate
     * Array items should have the following structure: `[string $path => mixed $data]`. The data portion may either be
     * a string of binary data or an instance of FileInfoInterface.
     * @param array|null $checkSumsMd5
     * Optional array of MD5 hashes for each file. During this step, checksum validation is only possible if you provide
     * MD5 hashes for each file you wish to validate this way. Array items should have the following structure:
     * `[string $path => string $md5]`
     * @param array|null $config
     * @return array
     */
    protected function validateArray(array $filesToValidate, ?array $checkSumsMd5, array $config = null) : array
    {
        $errors = [];
        foreach ($filesToValidate as $path => $data) {
            $path = $data instanceof FileInfoInterface ? $data->getPath() : $path;
            try {
                $this->validateFile($path, $data, $checkSumsMd5[$path], $config);
            } catch (FileCorruptedError $e) {
                $errors[$path] = $e;
            }
        }

        return $errors;
    }

    /**
     * Validates the integrity of the provided data.
     *
     * NOTE: If no validation for a given mime-type exists, the validation
     * will be considered to be successful.
     *
     * @param string $path
     * @param FileInfoInterface|string $data
     * @param string|null $checkSumMd5
     * @param array|null $config
     * @return void
     */
    protected function validateFile(string $path, FileInfoInterface|string $data, ?string $checkSumMd5, array $config = null) : void
    {
        if(!$this->isValidationRequired($config)) {
            return;
        }

        // TODO geb 24-09-25: Mime-type identification may fail on files with incomplete headers.
        if($data instanceof FileInfoInterface) {
            $mimeType = $data->getMimetype();
            $binaryData = $data->openFile("r")->read();
        } else {
            $mimeType = $this->guessMimeType($path, $data);
            $binaryData = $data;
        }

        // Validate checksums.
        $this->internalValidateChecksumMd5($path, $binaryData, $checkSumMd5, $config);

        [$superType,] = explode('/', $mimeType);
        switch ($superType) {
            case 'image':
                $this->internalValidateMimeImage($path, $binaryData, $config);
                return;
            case null :
                $msg = $this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_MIMETYPE', ["%mimetypes%" => $superType, "%path%" => $path]);
                throw new FileCorruptedError($msg);
        }
    }

    /**
     * Hashes each file with PHP md5() and returns the result.
     *
     * @param array $files
     * Array items should have the following structure: `[string $path => mixed $data]`. The data portion may either be
     * a string of binary data or an instance of FileInfoInterface.
     * @return array
     */
    protected function calculateCheckSumsMd5(array $files) : array
    {
        $checkSums = [];
        foreach ($files as $path => $file) {
            if($file instanceof FileInfoInterface) {
                $checkSums[$file->getPath()] = $file->getMd5();
            } else {
                $checkSums[$path] = md5($file);
            }
        }

        return $checkSums;
    }

    /**
     * Builds one coherent error message out of all the errors passed.
     *
     * @param array $errors
     * Array items should have the following structure: `[string $path => FileCorruptedError $error]`
     * @return FileCorruptedError
     */
    protected function renderAllErrors(array $errors) : FileCorruptedError
    {
        $number = 1;
        $fileList = '';
        $debugMessage = $this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_HEADER');
        foreach ($errors as $path => $error) {
            $debugMessage .= PHP_EOL.($number == 1 || $this->backupCorruptedFiles ? PHP_EOL : '');
            $debugMessage .= $number.'.: '.$error->getMessage();
            if($this->backupCorruptedFiles) {
                $backupPath = $error->getFileInfo() ? $error->getFileInfo()->getPathAbsolute() : $path;
                $debugMessage.= PHP_EOL.'--- '.$this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_DESTINATION', ["%path%" => $backupPath]);
            }

            $path = explode('/', $path);
            $fileList .= ($number == 1 ? '' : ', ').(end($path));
            $number++;
        }

        if(!$this->backupCorruptedFiles) {
            $debugMessage .= PHP_EOL.PHP_EOL.$this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_FOOTER');
        }

        $userMessage = $this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED_SIMPLE', ['%number%' => count($errors), '%files%' => $fileList]);
        $error = new FileCorruptedError($userMessage, null, new FileCorruptedError($debugMessage));
        $error->setAlias('Upload failed!');
        $error->setUseExceptionMessageAsTitle(true);
        return $error;
    }

    /**
     * Renders a single file corrupted error.
     *
     * @param string $path
     * @return FileCorruptedError
     */
    private function renderSingleError(string $path) : FileCorruptedError
    {
        $pathComponents = explode('/', $path);
        $msg = $this->getTranslator()->translate('WIDGET.UPLOADER.ERROR_FILE_CORRUPTED', ["%path%" => end($pathComponents)]);
        return new FileCorruptedError($msg,null, null);
    }

    /**
     * Check whether validation is required for a specified list of properties. Unknown properties resolve to `getValidationSettingDefault()`.
     *
     * @param array $config
     * @param array|null $properties
     * The list of properties you wish to check. If the array is empty all known properties will be checked.
     * @param bool $any
     * If this setting is enabled, the function returns true if ANY of the properties are enabled.
     * Otherwise, it only returns true if ALL properties are enabled.
     * @return bool
     */
    private function isValidationRequired(array $config, array $properties = null, bool $any = true) : bool
    {
        if(empty($config)) {
            return $this->validationSettingDefault;
        }

        if(empty($properties)) {
            $properties = $this->allConfigOptions;
        }

        foreach ($properties as $key) {
            $isRequired = ($config[$key] ?? $this->validationSettingDefault);

            if($any && $isRequired) {
                return true;
            }

            if(!$any && !$isRequired){
                return false;
            }
        }

        return false;
    }

    /**
     * Internal function to validate image files.
     *
     * DO NOT CALL DIRECTLY.
     *
     * @param string $path
     * @param string $binaryData
     * @param array|null $config
     * @return void
     */
    // TODO geb 24-09-25: Image validation is unreliable at the moment. All known real world cases have been caught, but manually manipulated files were not.
    private function internalValidateMimeImage(string $path, string $binaryData, ?array $config) : void
    {
        if(!$this->isValidationRequired($config, ['parse_images'])) {
            return;
        }

        if (!$img = imagecreatefromstring($binaryData)) {
            // Free memory.
            $img = null;
            unset($img);

            throw $this->renderSingleError($path);
        } else {
            // Free memory.
            $img = null;
            unset($img);
        }
    }

    /**
     * Internal function to validate md5 checksums.
     *
     * DO NOT CALL DIRECTLY.
     *
     * @param string $path
     * @param string $binaryData
     * @param string|null $checkSumMd5
     * @param array|null $config
     * @return void
     */
    private function internalValidateChecksumMd5(string $path, string $binaryData, ?string $checkSumMd5, ?array $config) : void
    {
        if(!isset($checkSumMd5)) {
            return;
        }

        if(!$this->isValidationRequired($config, ['validate_checksums'])) {
            return;
        }

        if(md5($binaryData) !== $checkSumMd5) {
            throw $this->renderSingleError($path);
        }
    }
}