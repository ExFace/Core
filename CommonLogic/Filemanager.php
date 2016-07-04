<?php namespace exface\Core\CommonLogic;

use Symfony\Component\Filesystem\Filesystem;

class Filemanager extends Filesystem {	
	/**
	 * Copies a complete folder to a new location including all subfolders
	 * @param string $originDir
	 * @param string $destinationDir
	 */
	public function copyDir($originDir, $destinationDir, $override = false) {
		$dir = opendir($originDir);
		@mkdir($destinationDir);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($originDir . DIRECTORY_SEPARATOR . $file) ) {
					$this->copyDir($originDir . DIRECTORY_SEPARATOR . $file,$destinationDir . DIRECTORY_SEPARATOR . $file);
				}
				else {
					$this->copy($originDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file, $override);
				}
			}
		}
		closedir($dir);
	}
}
?>