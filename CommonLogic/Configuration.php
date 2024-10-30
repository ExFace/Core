<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\Interfaces\WorkbenchInterface;

class Configuration implements ConfigurationInterface
{

    private $exface = null;

    private $config_uxon = null;

    private $config_files = array();

    /**
     *
     * @deprecated use ConfigurationFactory instead!
     * @param WorkbenchInterface $workbench            
     */
    public function __construct(WorkbenchInterface $workbench)
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
        if ($this->config_uxon === null) {
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
     * @see \exface\Core\Interfaces\ConfigurationInterface::getOption()
     */
    public function getOption(string $key)
    {
        $key = mb_strtoupper($key);
        $val = $this->getConfigUxon()->getProperty($key);
        // If the value is NULL, we need to distinguish between an intended NULL and a missing property
        if ($val === null && $this->getConfigUxon()->hasProperty($key) === false) {
           throw new ConfigOptionNotFoundError($this, 'Required configuration key "' . $key . '" not found!', '6T5DZN2');
        }
        return $val;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ConfigurationInterface::getOptionGroup()
     */
    public function getOptionGroup(string $namespace, bool $removeNamespace = false) : array
    {
        $namespace = rtrim($namespace, ".") . '.';
        $opts = $this->findOptions('/^' . preg_quote($namespace, '/') . '.*/');
        if ($removeNamespace) {
            $optsWithNamespace = $opts;
            $opts = [];
            $namespaceLength = strlen($namespace);
            foreach ($optsWithNamespace as $opt => $val) {
                $opts[substr($opt, $namespaceLength)] = $val;
            }
        }
        return $opts;
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
    public function hasOption(string $key, string $scope = null) : bool
    {
        if ($scope !== null) {
            return $this->getScopeConfig($scope)->hasOption($key);
        }
        
        $key = mb_strtoupper($key);
        return array_key_exists($key, $this->getConfigUxon()->toArray());
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
            $config = $this->getScopeConfig($configScope);
            // Overwrite the option
            $config->setOption($key, $value_or_object_or_string);
            // Save the file or create one if there was no installation specific config before
            file_put_contents($this->getConfigFilePath($configScope), $config->exportUxonObject()->toJson(true));
        }
        
        return $this;
    }
    
    /**
     * Returns a configuration instance with only the given scope.
     * 
     * @param string $configScope
     * @throws OutOfBoundsException
     * @return ConfigurationInterface
     */
    protected function getScopeConfig(string $configScope) : ConfigurationInterface
    {
        if (! $filename = $this->getConfigFilePath($configScope)) {
            throw new OutOfBoundsException('No configuration path found for config scope "' . $configScope . '"!');
        }
        // Load the installation specific config file
        $config = new self($this->getWorkbench());
        if (file_exists($filename)) {
            $config->loadConfigFile($filename);
        }
        
        return $config;
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
    
    /**
     * 
     * @param string $absolute_path
     * @return UxonObject|NULL
     */
    protected function readFile(string $absolute_path) : ?UxonObject
    {
        $uxon = null;
        if (file_exists($absolute_path)) {
            $json = file_get_contents($absolute_path);
            if ($json === false) {
                throw new FileNotReadableError('Cannot read configuration file "' . $absolute_path . "!");
            }
            if ($json !== null && $json !== '') {
                $uxon = UxonObject::fromJson($json, CASE_UPPER);
                $this->loadConfigUxon($uxon);
            }
        }
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ConfigurationInterface::reloadFiles()
     */
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