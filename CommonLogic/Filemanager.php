<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Filesystem\Filesystem;
use exface\Core\Interfaces\ExfaceClassInterface;
use Webmozart\PathUtil\Path;

class Filemanager extends Filesystem implements ExfaceClassInterface
{

    const FOLDER_NAME_VENDOR = 'vendor';

    const FOLDER_NAME_USER_DATA = 'UserData';

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

    private $path_to_user_data_folder = null;

    private $path_to_backup_folder = null;

    private $path_to_log_folder = null;

    private $core_log_filename = null;

    private $path_to_log_detail_folder = null;

    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface\exface)
     *
     * @return string
     */
    public function getPathToBaseFolder()
    {
        return $this->getWorkbench()->getInstallationPath();
    }

    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface\exface\vendor)
     *
     * @return string
     */
    public function getPathToVendorFolder()
    {
        return $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_VENDOR;
    }

    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface\exface\UserData)
     *
     * @return string
     */
    public function getPathToUserDataFolder()
    {
        if (is_null($this->path_to_user_data_folder)) {
            $this->path_to_user_data_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_USER_DATA;
            if (! is_dir($this->path_to_user_data_folder)) {
                mkdir($this->path_to_user_data_folder);
            }
        }
        return $this->path_to_user_data_folder;
    }

    /**
     * Returns the absolute path to the main cache folder (e.g.
     * c:\xampp\htdocs\exface\exface\cache)
     *
     * @return string
     */
    public function getPathToCacheFolder()
    {
        if (is_null($this->path_to_cache_folder)) {
            $this->path_to_cache_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_CACHE;
            if (! is_dir($this->path_to_cache_folder)) {
                mkdir($this->path_to_cache_folder);
            }
        }
        return $this->path_to_cache_folder;
    }

    /**
     * Returns the absolute path to the installation specific config folder (e.g.
     * c:\xampp\htdocs\exface\exface\config)
     *
     * @return string
     */
    public function getPathToConfigFolder()
    {
        if (is_null($this->path_to_config_folder)) {
            $this->path_to_config_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_CONFIG;
            if (! is_dir($this->path_to_config_folder)) {
                mkdir($this->path_to_config_folder);
            }
        }
        return $this->path_to_config_folder;
    }

    /**
     * Returns the absolute path to the installation specific translations folder (e.g.
     * c:\xampp\htdocs\exface\exface\translations)
     *
     * @return string
     */
    public function getPathToTranslationsFolder()
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
    public function getPathToLogFolder()
    {
        if (is_null($this->path_to_log_folder)) {
            $this->path_to_log_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_LOG;
            if (! is_dir($this->path_to_log_folder)) {
                mkdir($this->path_to_log_folder);
            }
        }
        return $this->path_to_log_folder;
    }

    /**
     * Returns the filename of the core log.
     *
     * @return string
     */
    public function getCoreLogFilename()
    {
        return static::FILE_NAME_CORE_LOG;
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
            $this->path_to_backup_folder = $this->getPathToBaseFolder() . DIRECTORY_SEPARATOR . static::FOLDER_NAME_BACKUP;
            if (! is_dir($this->path_to_backup_folder)) {
                mkdir($this->path_to_backup_folder);
            }
        }
        return $this->path_to_backup_folder;
    }

    /**
     * Copies a complete folder to a new location including all sub folders
     *
     * @param string $originDir            
     * @param string $destinationDir            
     */
    public function copyDir($originDir, $destinationDir, $override = false)
    {
        $dir = opendir($originDir);
        @mkdir($destinationDir);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($originDir . DIRECTORY_SEPARATOR . $file)) {
                    $this->copyDir($originDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file);
                } else {
                    $this->copy($originDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file, $override);
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
    public function emptyDir($absolutePath, $removeHiddenFiles = true){
        $absolutePath = rtrim(static::pathNormalize($absolutePath, '/'), "/");
        if ($removeHiddenFiles){
            $files = glob('path/to/temp/{,.}*', GLOB_BRACE);
        } else {
            $files = glob($absolutePath . '/*');
        }
        array_map('unlink', $files);
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Transforms "C:\wamp\www\exface\exface\vendor\exface\Core\CommonLogic\..\..\..\.." to "C:/wamp/www/exface/exface"
     *
     * @param string $path            
     * @return string
     */
    public static function pathNormalize($path, $directory_separator = '/')
    {
        $path = Path::canonicalize($path);
        if ($directory_separator !== '/') {
            $path = str_replace('/', $directory_separator, $path);
        }
        return $path;
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
        return Path::isAbsolute($path);
    }

    /**
     * Joins all paths given in the array and returns the resulting path
     *
     * @param array $paths            
     * @return string
     */
    public static function pathJoin(array $paths)
    {
        return Path::join($paths);
    }

    /**
     * Returns the longest common base path for all given paths or NULL if there is no common base.
     *
     * @param array $paths            
     * @return string|NULL
     */
    public static function pathGetCommonBase(array $paths)
    {
        return Path::getLongestCommonBasePath($paths);
    }

    /**
     * Checks a path folder by folder to determine if they are present, constructs folders that aren't
     *
     * @param
     *            $path
     * @return string
     */
    public static function pathConstruct($path)
    {
        $paths = explode(DIRECTORY_SEPARATOR, $path);
        $sPathList = $paths[0];
        for ($i = 1; $i < count($paths); $i ++) {
            $sPathList .= DIRECTORY_SEPARATOR . $paths[$i];
            $file_parts = pathinfo($sPathList);
            if (file_exists($sPathList) === false && array_key_exists('extension', $file_parts) == false) {
                mkdir($sPathList, 0755);
            }
        }
    }
}
?>