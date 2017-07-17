<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\Contexts\AbstractContext;

/**
 * The upload context takes care of files uploaded by the user.
 * Uploads are saved in the UserData/Uploads folder and organized in
 * subfolders named by the id of the context scope, where the upload context belongs to. By default it is the window scope. In that
 * case, the uploads folder will have subfolders for each window session id, containing alle files uploaded from the current window
 * (browser window). Similarly, the upload context can be placed in other scopes.
 *
 * @author Andrej Kabachnik
 *        
 */
class UploadContext extends AbstractContext
{

    private $upload_folder = 'Uploads';

    /**
     * Returns the absolute path to the upload folder of the current scope
     * IDEA Make it possible to define a user-specific UserData folder
     */
    public function getUploadsPath()
    {
        return $this->getWorkbench()->context()->getScopeUser()->getUserDataFolderAbsolutePath() . DIRECTORY_SEPARATOR . $this->upload_folder . DIRECTORY_SEPARATOR . $this->getScope()->getScopeId();
    }

    /**
     * Returns an array with all file names in the uploads folder
     *
     * @return array
     */
    public function getUploadedFileNames($include_folders = false)
    {
        if (is_dir($this->getUploadsPath()) && $result = scandir($this->getUploadsPath())) {
            // Filter away all folders (including "." and "..")
            foreach ($result as $nr => $filename) {
                if ($filename == '.' || $filename == '..' || (! $include_folders && is_dir($this->getUploadsPath() . DIRECTORY_SEPARATOR . $filename))) {
                    unset($result[$nr]);
                }
            }
        } else {
            $result = array();
        }
        return $result;
    }

    public function getUploadedFilePaths($include_folders = false)
    {
        $paths = array();
        $files = $this->getUploadedFileNames($include_folders);
        foreach ($files as $file) {
            $paths[] = $this->getUploadsPath() . DIRECTORY_SEPARATOR . $file;
        }
        return $paths;
    }

    /**
     * Clears all uploaded files from the current context
     *
     * @return \exface\Core\Contexts\UploadContext
     */
    public function clearUploads()
    {
        $this->deleteFolder($this->getUploadsPath());
        return $this;
    }

    /**
     * Deletes the given folder including all subfolders
     *
     * @param string $dir            
     */
    protected function deleteFolder($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . DIRECTORY_SEPARATOR . $object) == "dir")
                        $this->deleteFolder($dir . DIRECTORY_SEPARATOR . $object);
                    else
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
        return $this;
    }

    /**
     * The default scope of the upload context ist window, because it should only show the uploads from the current
     * browser window if the user works with multiple windows at the same time.
     *
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->context()->getScopeWindow();
    }
}
?>