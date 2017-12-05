<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\OutOfBoundsException;

class Configuration implements ConfigurationInterface
{

    private $exface = null;

    private $config_uxon = null;

    private $config_files = array();

    /**
     *
     * @deprecated use ConfigurationFactory instead!
     * @param Workbench $workbench            
     */
    public function __construct(Workbench $workbench)
    {
        $this->exface = $workbench;
    }

    /**
     * Returns a UXON object with the current configuration options for this app.
     * Options defined on different levels
     * (user, installation, etc.) are already merged at this point.
     *
     * @return \exface\Core\CommonLogic\UxonObject
     */
    protected function getConfigUxon()
    {
        if (is_null($this->config_uxon)) {
            $this->config_uxon = new UxonObject();
        }
        return $this->config_uxon;
    }

    /**
     * Overwrites the internal config UXON with the given UXON object
     *
     * @param UxonObject $uxon            
     * @return \exface\Core\CommonLogic\Configuration
     */
    protected function setConfigUxon(UxonObject $uxon)
    {
        $this->config_uxon = $uxon;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ConfigurationInterface::getOption()
     */
    public function getOption($key)
    {
        if (! $this->getConfigUxon()->hasProperty($key)) {
            if ($key_found = $this->getConfigUxon()->findPropertyKey($key, false)) {
                $key = $key_found;
            } else {
                throw new ConfigOptionNotFoundError($this, 'Required configuration key "' . $key . '" not found!', '6T5DZN2');
            }
        }
        return $this->getConfigUxon()->getProperty($key);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ConfigurationInterface::hasOption()
     */
    public function hasOption($key)
    {
        try {
            $this->getOption($key);
        } catch (ConfigOptionNotFoundError $e){
            return false;
        }
        return true;
    }

    protected function getConfigFilePath($scope)
    {
        return $this->config_files[$scope];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ConfigurationInterface::setOption()
     */
    public function setOption($key, $value_or_object_or_string, $configScope = null)
    {
        $this->getConfigUxon()->setProperty(mb_strtoupper($key), $value_or_object_or_string);
        
        if (! is_null($configScope)) {
            if (! $filename = $this->getConfigFilePath($configScope)) {
                throw new OutOfBoundsException('No configuration path found for config scope "' . $configScope . '"!');
            }
            // Load the installation specific config file
            $config = new self($this->getWorkbench());
            if (file_exists($filename)) {
                $config->loadConfigFile($filename);
            }
            // Overwrite the option
            $config->setOption($key, $value_or_object_or_string);
            // Save the file or create one if there was no installation specific config before
            file_put_contents($filename, $config->exportUxonObject()->toJson(true));
        }
        
        return $this;
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
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ConfigurationInterface::loadConfigFile()
     */
    public function loadConfigFile($absolute_path, $config_scope_key = null)
    {
        if (! is_null($config_scope_key)) {
            $this->config_files[$config_scope_key] = $absolute_path;
        }
        
        if (file_exists($absolute_path) && $uxon = UxonObject::fromJson(file_get_contents($absolute_path))) {
            $this->loadConfigUxon($uxon);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ConfigurationInterface::loadConfigUxon()
     */
    public function loadConfigUxon(UxonObject $uxon)
    {
        $this->setConfigUxon($this->getConfigUxon()->extend($uxon));
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->getConfigUxon();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        return $this->setConfigUxon($uxon);
    }
}

?>