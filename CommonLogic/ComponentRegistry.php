<?php

namespace exface\Core\CommonLogic;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\ComponentRegistryInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Facades\MarkdownInstancePrinterInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;

/**
 * Provides component metadata installed by Core and other apps.
 */
class ComponentRegistry implements ComponentRegistryInterface
{
    public const CONFIG_FILENAME = 'ComponentRegistry.config.json';
    public const CONFIG_COMPONENTS = 'COMPONENTS';
    public const CONFIG_SOURCES = 'SOURCES';

    private WorkbenchInterface $workbench;
    private array $config;
    
    /**
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
        $this->reload();
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::reload()
     */
    public function reload() : void
    {
        $file = $this->getWorkbench()->filemanager()->getPathToConfigFolder()
            . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME;
        $installed = file_exists($file);
        if (! $installed) {
            $file = $this->getWorkbench()->getCoreApp()->getDirectoryAbsolutePath()
                . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME;
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('Cannot read component registry "' . $file . '"');
        }
        $json = JsonDataType::decodeJson($contents, false);
        $models = $installed ? ($json->{self::CONFIG_COMPONENTS} ?? null) : $json;
        if (! $models instanceof \stdClass) {
            throw new RuntimeException('Expected a component object in registry "' . $file . '"');
        }
        foreach ($models as $key => $model) {
            if (! is_string($key) || $key === '' || $key !== mb_strtolower($key) || ! $model instanceof \stdClass) {
                throw new RuntimeException('Invalid component "' . $key . '" in registry "' . $file . '"');
            }
        }
        $this->config = JsonDataType::decodeJson(JsonDataType::encodeJson($models), true);
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::getComponentKeys()
     */
    public function getComponentKeys(?string $havingKey = null) : array
    {
        $keys = array_keys($this->config);
        if ($havingKey !== null) {
            $keys = array_filter($keys, function($key) use ($havingKey) {
                return array_key_exists($havingKey, $this->config[$key]);
            });
        }
        return $keys;
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::getComponentNames()
     */
    public function getComponentNames() : array
    {
        $array = [];
        foreach ($this->config as $key => $component) {
            $array[$key] = $component['name'] ?? $key;
        }
        return $array;
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::getComponentProperty()
     */
    public function getComponentProperty(string $componentKey, string $property)
    {
        return $this->config[$componentKey][$property] ?? null;
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::getComponentSaveData()
     */
    public function getComponentSaveData(string $component) : ?UxonObject
    {
        $componentModel = $this->getComponentModel($component);
        if ($componentModel === null) {
            throw new InvalidArgumentException('Unknown component type "' . $component . '"');
        }

        $saveData = new UxonObject($componentModel['save_component_data'] ?? []);
        return $saveData->isEmpty() ? null : $saveData;
    }
    
    protected function canInstantiate(string $component) : bool
    {
        return null !== $this->getSelectorFactory($component);
    }
    
    protected function getSelectorFactory($component) : ?callable
    {
        $factoryClass = (($this->config[$component] ?? [])['selector'] ?? [])['factory'] ?? null;
        switch (true) {
            case null === $factoryClass:
                return null;
            case is_string($factoryClass):
                $callable = explode('::', rtrim($factoryClass, "()"));
                break;
            case is_callable($factoryClass):
                $callable = $factoryClass;
                break;
            default:
                throw new RuntimeException('Invalid selector_factory_class for component ' . $component);
        }
        return $callable;
    }

    protected function getSelectorClass(string $component) : ?string
    {
        return (($this->config[$component] ?? [])['selector'] ?? null)['class'] ?? null;
    }
    
    protected function instantiate(string $component, string $selector) : object
    {
        $selectorClass = $this->getselectorClass($component);
        if (! $selectorClass) {
            throw new RuntimeException("Cannot find selector class for '{$component}'");
        }
        $selector = new $selectorClass($this->getWorkbench(), $selector);
        $factory = $this->getSelectorFactory($component);
        if (! $factory) {
            throw new RuntimeException('Cannot instantiate ' . $component . ' `' . $selector . '` - no selector factory found');
        }
        return $factory($selector);
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::getMarkdownPrinter()
     */
    public function getDocsForSelector(string $component, string $selector) : ?string
    {
        switch (true) {
            case $this->canInstantiate($component):
                $config = $this->config[$component] ?? null;
                if (
                    !$config
                    || !array_key_exists('documentation', $config)
                    || !array_key_exists('markdown_printer_class', $config['documentation'])
                ) {
                    return null;
                }

                $printerClass = $config['documentation']['markdown_printer_class'];
                if (! is_a($printerClass, MarkdownInstancePrinterInterface::class, true)) {
                    throw new RuntimeException('Cannot use ' . $printerClass . ' for documentation of ' . $component);
                }
                
                $instance = $this->instantiate($component, $selector);
                $printer = $printerClass::constructForInstance($instance);
                break;
            default:
                return null;
        }
        return $printer->getMarkdown();
    }
    
    public function findComponentKey(string $aliasOrUxonProperty) : ?string
    {
        if (array_key_exists($aliasOrUxonProperty, $this->config)) {
            return $aliasOrUxonProperty;
        }
        foreach ($this->config as $component) {
            if ($component['uxon_properties'] && in_array($aliasOrUxonProperty, $component['uxon_properties'], true)) {
                return $component['class'];
            }
        }
        return null;
    }

    /**
     * {@inheritDoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    protected function getComponentModel(string $component) : ?array
    {
        $component = mb_strtolower($component);
        return $this->config[$component] ?? null;
    }
    
    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::searchPrototypes()
     */
    public function searchPrototypes(string $searchTerm, string $component, ?int $limit = null, int $offset = null) : DataSheetInterface
    {
        $compModel = $this->getComponentModel($component);
        if (! $compModel) {
            throw new InvalidArgumentException('Cannot search prototypes for unknown component "' . $component . '"');
        }
        $dataSheetUxon = new UxonObject($compModel['search_prototype_data'] ?? []);
        if ($dataSheetUxon->isEmpty()) {
            throw new InvalidArgumentException('Cannot search prototypes for component "' . $component . '" - no prototype search data defined');
        }
        
        // Render placeholders
        $dataSheetJson = $dataSheetUxon->toJson();
        $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $renderer->addPlaceholder(new ArrayPlaceholders([
            '~query' => $searchTerm
        ]));
        $renderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench()));
        $renderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench()));
        $renderedJson = $renderer->render($dataSheetJson);
        $dataSheetUxon = UxonObject::fromJson($renderedJson);
        
        $dataSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $dataSheetUxon);
        $dataSheet->dataRead($limit, $offset);
        return $dataSheet;
    }
}