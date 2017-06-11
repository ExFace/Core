<?php
namespace exface\Core\CommonLogic\Log\Helpers;

use Monolog\Logger;

class LogHelper
{

    /**
     * Returns the log filename with date added to it.
     *
     * @param string $filename            
     * @param string $dateFormat            
     * @param string $filenameFormat            
     *
     * @return string
     */
    public static function getFilename($filename, $dateFormat, $filenameFormat, $static)
    {
        $fileInfo = pathinfo($filename);
        $timedFilename = str_replace(array(
            '{filename}',
            '{variable}',
            '{static}'
        ), array(
            $fileInfo['filename'],
            date($dateFormat),
            $static
        ), $fileInfo['dirname'] . '/' . $filenameFormat);
        
        if (! empty($fileInfo['extension'])) {
            $timedFilename .= '.' . $fileInfo['extension'];
        }
        
        return $timedFilename;
    }

    /**
     * Return file pattern.
     *
     * @param
     *            $filename
     * @param
     *            $filenameFormat
     * @param string $variable            
     * @param string $static            
     *
     * @return mixed|string
     */
    public static function getPattern($filename, $filenameFormat, $variable = '*', $static = '')
    {
        $fileInfo = pathinfo($filename);
        $glob = str_replace(array(
            '{filename}',
            '{variable}',
            '{static}'
        ), array(
            $fileInfo['filename'],
            $variable,
            $static
        ), $fileInfo['dirname'] . '/' . $filenameFormat);
        if (! empty($fileInfo['extension'])) {
            $glob .= '.' . $fileInfo['extension'];
        }
        
        return $glob;
    }

    /**
     * Compares PSR 3 log levels. It uses monolog to do so.
     *
     * @param string $level1
     * @param string $level2
     *
     * @return int Result is < 0 if monolog values of level1 < level2. Result is > 0 if monolog values of level1 >
     * level2. Result is 0 if monolog values of level1 and level2 are equal.
     */
    public static function compareLogLevels($level1, $level2)
    {
        return Logger::toMonologLevel($level1) - Logger::toMonologLevel($level2);
    }
}
