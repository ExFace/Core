<?php
namespace exface\Core\DataTypes;

/**
 * Data type for server software names like `Apache/2.4.46`
 *
 * Includes static helper methods to identify the current server environment
 *
 * @author Andrej Kabachnik
 *
 */
class ServerSoftwareDataType extends StringDataType
{
    const SERVER_SOFTWARE_APACHE = 'Apache';
    const SERVER_SOFTWARE_IIS = 'Microsoft-IIS';
    const SERVER_SOFTWARE_NGINX = 'nginx';

    /**
     * Returns the full name of the servers software, that runs the workbench
     *
     * Examples:
     *
     * - Apache/2.4.46 (Win64) PHP/8.0.14 mod_fcgid/2.3.10-dev
     * - Microsoft-IIS/10.0
     * - nginx/1.24.0
     * - PHP/8.3.0 (Development Server)
     *
     * @return string|NULL
     */
    public static function getServerSoftware() : ?string
    {
        return $_SERVER['SERVER_SOFTWARE'] ?? null;
    }

    /**
     * Returns family/type of the server software
     *
     * - Apache
     * - Microsoft-IIS
     * - nginx
     * - PHP
     *
     * @return string|NULL
     */
    public static function getServerSoftwareFamily() : ?string
    {
        $software = static::getServerSoftware();
        return StringDataType::substringBefore($software ?? '', '/', $software);
    }

    /**
     * Returns the version of the server softwareExamples:
     *
     * - 2.4.46
     * - 10.0
     * - 1.24.0
     * - 8.3.0
     *
     * @return string|NULL
     */
    public static function getServerSoftwareVersion() : ?string
    {
        $parts = explode('/', static::getServerSoftware() ?? '');
        switch (count($parts)) {
            case 0:
            case 1:
                $version = null;
                break;
            default:
                $version = StringDataType::substringBefore($parts[1], ' ', $parts[1]);
                break;
        }
        return $version;
    }

    /**
     *
     * @return bool
     */
    public static function isServerIIS() : bool
    {
        return strcasecmp(static::getServerSoftwareFamily() ?? null, self::SERVER_SOFTWARE_IIS) === 0;
    }

    /**
     *
     * @return bool
     */
    public static function isServerApache() : bool
    {
        return strcasecmp(static::getServerSoftwareFamily() ?? null, self::SERVER_SOFTWARE_APACHE) === 0;
    }

    /**
     *
     * @return bool
     */
    public static function isServerNginx() : bool
    {
        return strcasecmp(static::getServerSoftwareFamily() ?? null, self::SERVER_SOFTWARE_NGINX) === 0;
    }

    /**
     *
     * @return bool
     */
    public static function isOsWindows() : bool
    {
        return substr(php_uname(), 0, 7) === "Windows";
    }

    /**
     *
     * @return bool
     */
    public static function isOsLinux() : bool
    {
        return ! static::isOsWindows();
    }

    /**
     * Checks whether the file system at the given path treats file names case-sensitively.
     *
     * Why: the previous implementation upper-/lowercased the entire path of this class,
     * which mixes the behavior of every parent directory and mount point. Here only a single
     * path segment is varied, so the result reflects the directory the files are actually
     * loaded from. Pass the directory you are going to load from (e.g. the vendor folder).
     *
     * Not cached - callers needing the value repeatedly should keep it themselves.
     *
     * This method never throws, because it is used while bootstrapping the autoloader.
     * If case sensitivity cannot be determined, TRUE is returned: assuming case-sensitivity
     * only leads to exact matches (plus recovery lookups), so callers fall back to their
     * regular "not found" handling instead of matching a wrongly cased path.
     *
     * @param string|null $path file or directory to test - defaults to this class file
     */
    public static function isFileSystemCaseSensitive(?string $path = null) : bool
    {
        $realPath = realpath($path ?? __FILE__);
        if ($realPath === false) {
            return true;
        }
        return self::detectCaseSensitivity($realPath);
    }

    /**
     * Determines which path segment to probe and returns the detected case sensitivity.
     *
     * Why: for a directory, probing its own name would test the file system of its PARENT
     * (the name is resolved there). If the directory is a separate mount (e.g. a Docker
     * volume), that gives a wrong result. So an entry inside the directory is probed first.
     * If that is not possible, we walk up to the nearest segment containing case-changeable
     * characters. If nothing is testable, case-sensitivity is assumed.
     */
    private static function detectCaseSensitivity(string $realPath) : bool
    {
        if (is_dir($realPath)) {
            $probeEntry = self::findProbeEntry($realPath);
            if ($probeEntry !== null) {
                return self::isCaseSensitiveEntry($realPath, $probeEntry);
            }
        }

        $current = $realPath;
        while (($parent = dirname($current)) !== $current) {
            $name = basename($current);
            if (strtoupper($name) !== strtolower($name)) {
                return self::isCaseSensitiveEntry($parent, $name);
            }
            $current = $parent;
        }
        return true;
    }

    /**
     * Returns the first entry of the given directory, that contains case-changeable characters.
     *
     * Why: we need a real entry INSIDE the directory to test the file system the directory
     * itself lives on. readdir() is used instead of scandir() to stop at the first match
     * instead of reading large directories completely. Warnings are suppressed because this
     * runs during bootstrap, where an unreadable directory must not break startup.
     */
    private static function findProbeEntry(string $dir) : ?string
    {
        $handle = @opendir($dir);
        if ($handle === false) {
            return null;
        }
        try {
            while (($entry = readdir($handle)) !== false) {
                // "." and ".." are skipped automatically as they contain no letters
                if (strtoupper($entry) !== strtolower($entry)) {
                    return $entry;
                }
            }
        } finally {
            closedir($handle);
        }
        return null;
    }

    /**
     * Checks if a case-variant of the given directory entry resolves to the same file.
     *
     * Why: checking only if the variant exists is not enough - on a case-sensitive file system
     * a directory may contain two siblings differing only in case. Comparing inodes tells these
     * cases apart: same inode means the same entry was found (case-insensitive), different
     * inodes mean two separate files (case-sensitive).
     */
    private static function isCaseSensitiveEntry(string $dir, string $name) : bool
    {
        $upper = strtoupper($name);
        $variant = $name !== $upper ? $upper : strtolower($name);
        $originalPath = $dir . DIRECTORY_SEPARATOR . $name;
        $variantPath = $dir . DIRECTORY_SEPARATOR . $variant;

        if (! file_exists($variantPath)) {
            return true;
        }
        return @fileinode($originalPath) !== @fileinode($variantPath);
    }

    /**
     * Returns the operating system user running the current PHP process.
     *
     * @return string|NULL
     */
    public static function getOsUser() : ?string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $userInfo = posix_getpwuid(posix_geteuid());
            if (is_array($userInfo) && ($userInfo['name'] ?? '') !== '') {
                return $userInfo['name'];
            }
        }

        if (static::isOsWindows() && function_exists('exec')) {
            $output = [];
            $exitCode = null;
            @exec('whoami', $output, $exitCode);
            if ($exitCode === 0 && ($output[0] ?? '') !== '') {
                return trim($output[0]);
            }
        }

        $username = getenv(static::isOsWindows() ? 'USERNAME' : 'USER');
        if ($username === false || $username === '') {
            return null;
        }

        if (static::isOsWindows()) {
            $domain = getenv('USERDOMAIN');
            if ($domain !== false && $domain !== '') {
                return $domain . '\\' . $username;
            }
        }

        return $username;
    }

    /**
     * Check if php script is run in a CLI environment
     *
     * @return boolean
     */
    public static function isCLI()
    {
        if ( defined('STDIN') )
        {
            return true;
        }

        if ( php_sapi_name() === 'cli' )
        {
            return true;
        }

        if ( array_key_exists('SHELL', $_ENV) ) {
            return true;
        }

        if ( empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0)
        {
            return true;
        }

        if ( !array_key_exists('REQUEST_METHOD', $_SERVER) )
        {
            return true;
        }

        return false;
    }
}