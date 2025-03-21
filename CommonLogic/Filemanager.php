<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\WorkbenchInterface;
use Symfony\Component\Filesystem\Filesystem;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\DataTypes\FilePathDataType;

class Filemanager extends Filesystem implements WorkbenchDependantInterface
{
    const FOLDER_NAME_VENDOR = 'vendor';
    
    const FOLDER_NAME_DATA = 'data';
    
    const FOLDER_NAME_USER_DATA = 'users';
    
    const FOLDER_NAME_CACHE = 'cache';
    
    const FOLDER_NAME_CONFIG = 'config';
    
    const FOLDER_NAME_TRANSLATIONS = 'translations';
    
    const FOLDER_NAME_BACKUP = 'backup';
    
    const FOLDER_NAME_LOG = 'logs';
    
    const FILE_NAME_CORE_LOG = '.log';
    
    const FOLDER_NAME_LOG_DETAILS = 'details';
    
    private $exface = null;
    
    private $path_to_cache_folder = null;
    
    private $path_to_config_folder = null;
    
    private $path_to_data_folder = null;
    
    private $path_to_user_data_folder = null;
    
    private $path_to_backup_folder = null;
    
    private $path_to_log_folder = null;
    
    private $core_log_filename = null;
    
    private $path_to_log_detail_folder = null;
    
    private $path_to_translations_folder = null;
    
    public function __construct(WorkbenchInterface $exface)
    {
        $this->exface = $exface;
    }
    
    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface)
     *
     * @return string
     */
    public function getPathToBaseFolder() : string
    {
        return $this->getWorkbench()->getInstallationPath();
    }
    
    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface\vendor)
     *
     * @return string
     */
    public function getPathToVendorFolder() : string
    {
        return $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_VENDOR;
    }
    
    /**
     * Returns the absolute path to the user data folder (e.g. c:\xampp\htdocs\exface\data\users)
     *
     * @return string
     */
    public function getPathToUserDataFolder() : string
    {
        if (is_null($this->path_to_user_data_folder)) {
            /* TODO configurable userdata folder path did not work because Workbench::getConfig() also
             * attempts to get the userdata folder to look for configs there, resulting in an infinite
             * loop.
             *
             try {
             $path = $this->getWorkbench()->getConfig()->getOption('FOLDERS.USERDATA_PATH_ABSOLUTE');
             } catch (ConfigOptionNotFoundError $e) {
             $path = '';
             }*/
            $this->path_to_user_data_folder = $this->getPathToDataFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_USER_DATA;
            if (! is_dir($this->path_to_user_data_folder)) {
                static::pathConstruct($this->path_to_user_data_folder);
            }
        }
        return $this->path_to_user_data_folder;
    }
    
    /**
     * Returns the absolute path to the data (e.g. c:\xampp\htdocs\exface\data)
     *
     * @return string
     */
    public function getPathToDataFolder() : string
    {
        if (null === $this->path_to_data_folder) {
            $this->path_to_data_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_DATA;
            if (false === is_dir($this->path_to_data_folder)) {                
                static::pathConstruct($this->path_to_data_folder);
            }
        }
        return $this->path_to_data_folder;
    }
    
    /**
     * Returns the absolute path to the main cache folder (e.g.
     * c:\xampp\htdocs\exface\cache)
     *
     * @return string
     */
    public function getPathToCacheFolder() : string
    {
        if (is_null($this->path_to_cache_folder)) {
            try {
                $path = $this->getWorkbench()->getConfig()->getOption('FOLDERS.CACHE_PATH_ABSOLUTE');
            } catch (ConfigOptionNotFoundError $e) {
                $path = '';
            }
            
            $this->path_to_cache_folder = $path ? $path : $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_CACHE;
            if (! is_dir($this->path_to_cache_folder)) {
                static::pathConstruct($this->path_to_cache_folder);
            }
        }
        return $this->path_to_cache_folder;
    }
    
    /**
     * Returns the absolute path to the folder for temporary data (e.g. c:\xampp\htdocs\exface\temp)
     *
     * @return string
     */
    public function getPathToTempFolder() : string
    {
        return $this->getPathToCacheFolder();
    }
    
    /**
     * Returns the absolute path to the installation specific config folder (e.g.
     * c:\xampp\htdocs\exface\config)
     *
     * @return string
     */
    public function getPathToConfigFolder() : string
    {
        if (null === $this->path_to_config_folder) {
            $this->path_to_config_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_CONFIG;
            if (false === is_dir($this->path_to_config_folder)) {
                mkdir($this->path_to_config_folder);
            }
        }
        return $this->path_to_config_folder;
    }
    
    /**
     * Returns the absolute path to the installation specific translations folder (e.g.
     * c:\xampp\htdocs\exface\translations)
     *
     * @return string
     */
    public function getPathToTranslationsFolder() : string
    {
        if (is_null($this->path_to_translations_folder)) {
            $this->path_to_translations_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_TRANSLATIONS;
            if (! is_dir($this->path_to_translations_folder)) {
                mkdir($this->path_to_translations_folder);
            }
        }
        return $this->path_to_translations_folder;
    }
    
    /**
     * Returns the absolute path to the log folder
     *
     * @return string
     */
    public function getPathToLogFolder() : string
    {
        if (is_null($this->path_to_log_folder)) {
            try {
                $path = $this->getWorkbench()->getConfig()->getOption('FOLDERS.LOGS_PATH_ABSOLUTE');
            } catch (ConfigOptionNotFoundError $e) {
                $path = '';
            }
            $this->path_to_log_folder = $path ? $path : $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_LOG;
            if (! is_dir($this->path_to_log_folder)) {
                static::pathConstruct($this->path_to_log_folder);
            }
        }
        return $this->path_to_log_folder;
    }
    
    /**
     * Returns the absolute path to the log details folder
     *
     * @return string
     */
    public function getPathToLogDetailsFolder()
    {
        if (is_null($this->path_to_log_detail_folder)) {
            $this->path_to_log_detail_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_LOG . DIRECTORY_SEPARATOR . static::FOLDER_NAME_LOG_DETAILS;
            if (! is_dir($this->path_to_log_detail_folder)) {
                mkdir($this->path_to_log_detail_folder);
            }
        }
        return $this->path_to_log_detail_folder;
    }
    
    /**
     * Returns the absolute path to the main backup folder
     *
     * @return string
     */
    public function getPathToBackupFolder()
    {
        if (is_null($this->path_to_backup_folder)) {
            try {
                $path = $this->getWorkbench()->getConfig()->getOption('FOLDERS.BACKUP_PATH_ABSOLUTE');
            } catch (ConfigOptionNotFoundError $e) {
                $path = '';
            }
            $this->path_to_backup_folder = $path ? $path : $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_BACKUP;
            if (! is_dir($this->path_to_backup_folder)) {
                static::pathConstruct($this->path_to_backup_folder);
            }
        }
        return $this->path_to_backup_folder;
    }
    
    /**
     * Copies a complete folder to a new location including all sub folders
     *
     * @param string $originDir
     * @param string $destinationDir
     * @param boolean $overWriteNewerFiles
     */
    public function copyDir($originDir, $destinationDir, $overWriteNewerFiles = false)
    {
        $dir = opendir($originDir);
        @mkdir($destinationDir);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($originDir . DIRECTORY_SEPARATOR . $file)) {
                    $this->copyDir($originDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file, $overWriteNewerFiles);
                } else {
                    $this->copy($originDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file, $overWriteNewerFiles);
                }
            }
        }
        closedir($dir);
    }
    
    /**
     * Removes all files and subfolders in the given folder, leaving it empty.
     * @param string $absolutePath
     * @param boolean $removeHiddenFiles
     */
    public static function emptyDir($absolutePath, $removeHiddenFiles = true){
        $absolutePath = static::pathNormalize($absolutePath, DIRECTORY_SEPARATOR);
        if (substr($absolutePath, -1) !== DIRECTORY_SEPARATOR){
            $absolutePath .= DIRECTORY_SEPARATOR;
        }
        
        // First empty subfolders
        if ($removeHiddenFiles){
            $subfolders = glob($absolutePath . '{,.}[!.,!..]*', GLOB_MARK|GLOB_BRACE|GLOB_ONLYDIR);
        } else {
            $subfolders = glob($absolutePath . '*', GLOB_ONLYDIR);
        }
        array_map(self::class . '::emptyDir', $subfolders);
        
        // Now delete subfolders and files
        if ($removeHiddenFiles){
            $files = glob($absolutePath . '{,.}[!.,!..]*', GLOB_MARK|GLOB_BRACE);
        } else {
            $files = glob($absolutePath . '*');
        }
        array_map(function($path) {
            if (is_dir($path)) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }, $files);
            
            return;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }
    
    /**
     * Transforms "C:\wamp\www\exface\vendor\exface\Core\CommonLogic\..\..\..\.." to "C:/wamp/www/exface/exface"
     *
     * @param string $path
     * @return string
     */
    public static function pathNormalize($path, $directory_separator = '/')
    {
        return FilePathDataType::normalize($path, $directory_separator);
    }
    
    /**
     * Returns TRUE if the given string is an absolute path and FALSE otherwise
     *
     * @param string $path
     * @return boolean
     */
    public static function pathIsAbsolute($path)
    {
        if (is_null($path) || $path == '') {
            return false;
        }
        return FilePathDataType::isAbsolute($path);
    }
    
    /**
     * Joins all paths given in the array and returns the resulting path
     *
     * @param array $paths
     * @return string
     */
    public static function pathJoin(array $paths)
    {
        return FilePathDataType::join($paths);
    }
    
    /**
     * Returns the longest common base path for all given paths or NULL if there is no common base.
     *
     * @param array $paths
     * @return string|NULL
     */
    public static function pathGetCommonBase(array $paths)
    {
        return FilePathDataType::findCommonBase($paths);
    }
    
    /**
     * Checks a path folder by folder to determine if they are present, constructs folders that aren't
     *
     * @param string $path
     * @return string
     */
    public static function pathConstruct(string $path)
    {
        $path = self::pathNormalize($path, DIRECTORY_SEPARATOR);
        $paths = explode(DIRECTORY_SEPARATOR, $path);
        $sPathList = $paths[0];
        for ($i = 1; $i < count($paths); $i ++) {
            $sPathList .= DIRECTORY_SEPARATOR . $paths[$i];
            if (file_exists($sPathList) === false) {
                mkdir($sPathList, 0755);
            }
        }
    }
    /**
     * Emptys directory, deletes it afterwards
     *
     * @param  $path
     */
    public static function deleteDir($dirPath) {
        if (is_dir($dirPath)) {
            self::emptyDir($dirPath, true);
            rmdir($dirPath);
        }
        return;
    }
    /**
     * Checks if a directory is empty
     *
     * @param  $path
     * @return boolean|null State of directory, TRUE if empty, FALSE if at least one file is found, NULL if permission denied and state unclear
     */
    public static function isDirEmpty($dir) {
        if (!is_readable($dir)) return NULL;
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                return FALSE;
            }
        }
        return TRUE;
    }
    
    /**
     * An alias for copy() - for historical reasons
     * 
     * @param string $source
     * @param string $destination
     * @param bool $overwriteNewerFiles
     * 
     * @return void
     */
    public function copyFile(string $source, string $destination, bool $overwriteNewerFiles = false)
    {
        return $this->copy($source, $destination, $overwriteNewerFiles);
    }
    
    /**
     * Returns an array with all files and folders in the given directory and subdirectories
     * 
     * @param string $dir
     * @param boolean $includeFolders
     * @param bool $realtiveToDir
     * @param string $directorySeparator
     * 
     * @return string[]
     */
    public static function getDirContentsRecursive(string $dir, $includeFolders = true, bool $realtiveToDir = true, string $directorySeparator = DIRECTORY_SEPARATOR) {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        /* @var $file \SplFileInfo */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                if ($includeFolders === true && $file->getPath() !== $dir && $file->getFilename() === '.') {
                    $files[] = $file->getPath();
                }
                continue;
            }
            $files[] = $file->getPathname();
        }
        if ($realtiveToDir === true) {
            $dirLen = strlen($dir);
            array_walk($files, function(&$file) use ($dirLen) {
                $file = ltrim(substr($file, $dirLen), "/\\");
            });
        }
        if ($directorySeparator !== DIRECTORY_SEPARATOR) {
            array_walk($files, function(&$file) use ($directorySeparator) {
                $file = FilePathDataType::normalize($file, $directorySeparator);
            });
        }
        return $files;
    }
}