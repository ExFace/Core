<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
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
    public function getOption(string $key)
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
     * @see \exface\Core\Interfaces\ConfigurationInterface::findOptions()
     */
    public function findOptions(string $regEx) : array
    {
        $array = $this->config_uxon->toArray();
        $result = [];
        foreach($array as $key => $value) {
            if (preg_match($regEx,$key)){
                $result[$key] = $value;
            }
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ConfigurationInterface::hasOption()
     */
    public function hasOption(string $key) : bool
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
        foreach ($this->config_files as $f) {
            if ($f['scope'] === $scope) {
                return $f['path'];
            }
        }
        return null;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ConfigurationInterface::setOption()
     */
    public function setOption(string $key, $value_or_object_or_string, string $configScope = null) : ConfigurationInterface
    {
        $this->getConfigUxon()->setProperty(mb_strtoupper($key), $value_or_object_or_string);
        
        if ($configScope !== null) {
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ConfigurationInterface::unsetOption()
     */
    public function unsetOption(string $key, string $configScope) : ConfigurationInterface
    {
        $filename = $this->getConfigFilePath($configScope);
        
        if ($filename && file_exists($filename)) {
            $config = new self($this->getWorkbench());
            $config->loadConfigFile($filename);
            file_put_contents($filename, $config->exportUxonObject()->unsetProperty(mb_strtoupper($key))->toJson(true));
            $this->reloadFiles();
        }
        
        return $this;
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
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ConfigurationInterface::loadConfigFile()
     */
    public function loadConfigFile(string $absolute_path, string $config_scope_key = null) : ConfigurationInterface
    {
        $this->config_files[] = [
            'scope' => $config_scope_key,
            'path' => $absolute_path
        ];
        
        $this->readFile($absolute_path);
        
        return $this;
    }
    
    protected function readFile(string $absolute_path)
    {
        if (file_exists($absolute_path) && $uxon = UxonObject::fromJson(file_get_contents($absolute_path))) {
            $this->loadConfigUxon($uxon);
        }
        return;
    }
    
    public function reloadFiles() : ConfigurationInterface
    {
        $this->setConfigUxon(new UxonObject());
        foreach ($this->config_files as $f) {
            $this->readFile($f['path']);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ConfigurationInterface::loadConfigUxon()
     */
    public function loadConfigUxon(UxonObject $uxon) : ConfigurationInterface
    {
        // Can't use UxonObject::extend() here because the config cannot be merged
        // recursively.
        $merged = array_replace($this->getConfigUxon()->toArray(), $uxon->toArray());
        $this->setConfigUxon(new UxonObject($merged));
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
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }
}