<?php
namespace exface\Core\CommonLogic\AppInstallers;


use exface\Core\CommonLogic\EventManager;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use JsonPath\JsonObject;

/**
 * Registers static event listeners with a config file that will be processed by the EventManager during initialization.
 * 
 * @author Andrej Kabachnik
 *        
 */
class StaticEventListenerInstaller extends AbstractAppInstaller
{
    public const CFG_SOURCES = 'SOURCES';

    private string $filepath;
    private string $appAlias;
    private array $listenersToInstall = [];

    /**
     * @param SelectorInterface $selectorToInstall
     * @param string|null       $appAlias
     */
    public function __construct(SelectorInterface $selectorToInstall, string $appAlias = null)
    {
        parent::__construct($selectorToInstall);
        $this->filepath = $this->getPathToConfigFolder() . EventManager::FILE_STATIC_LISTENERS;
        $this->appAlias = $appAlias ?? $selectorToInstall->toString();
    }

    /**
     * @inheritDoc
     */
    public function backup(string $absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }

    /**
     * @inheritDoc
     */
    public function uninstall() : \Iterator
    {
        $indent = $this->getOutputIndentation();
        yield PHP_EOL . PHP_EOL . $indent . 'Uninstalling static event listeners for "' . $this->appAlias . '"...' . PHP_EOL;

        $targetFile = $this->getTargetPath();
        $fileName = FilePathDataType::findFileName($targetFile, true);

        // Load config.
        if(file_exists($targetFile)) {
            $configArray = JsonDataType::decodeJson(file_get_contents($targetFile));
            yield $indent . $indent . 'Successfully loaded config file "' . $fileName . '".' . PHP_EOL;
        } else {
            yield $indent . $indent . 'Could not find config file "' . $fileName . '".' . PHP_EOL;
            yield $indent . 'Done.' . PHP_EOL . PHP_EOL;
            return;
        }

        // Remove source.
        $this->removeSourceFromConfig($configArray, $this->appAlias);

        // Save altered config.
        try {
            file_put_contents(
                $this->getTargetPath(),
                JsonDataType::encodeJson($configArray, true)
            );
            yield $indent . $indent . 'Successfully uninstalled static event listeners!' . PHP_EOL;
        } catch (\Throwable $exception) {
            yield $indent . $indent . 'Failed to uninstall static listeners:' . $exception->getMessage() . PHP_EOL;
        }
        
        yield $indent . 'Done.' . PHP_EOL . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function install(string $source_absolute_path): \Iterator
    {
        $indent = $this->getOutputIndentation();
        yield PHP_EOL . $indent . 'Installing static event listeners...' . PHP_EOL;

        $targetFile = $this->getTargetPath();
        $fileName = FilePathDataType::findFileName($targetFile, true);
        
        // Load config.
        if(file_exists($targetFile)) {
            $configArray = JsonDataType::decodeJson(file_get_contents($targetFile));
            $config = new JsonObject($configArray);
            yield $indent . $indent . 'Successfully loaded config file "' . $fileName . '".' . PHP_EOL;
        } else {
            yield $indent . $indent . 'Could not find config file "' . $fileName . '", loading template instead.' . PHP_EOL;
            $templateFile = $this->getTemplatePath();
            $fileName = FilePathDataType::findFileName($templateFile, true);
            
            // If config does not exist, load template instead.
            if(file_exists($templateFile)) {
                $templateArray = JsonDataType::decodeJson(file_get_contents($templateFile));
                yield $indent . $indent . 'Successfully loaded template file "' . $fileName . '".' . PHP_EOL;
                
                $config = new JsonObject([]);
                foreach ($templateArray[EventManager::CFG_STATIC_LISTENERS] as $eventName => $callables) {
                    foreach ($callables as $callable) {
                        $this->addListenerToConfig($config, $eventName, $callable, 'exface.Core');
                    }
                }
            } else {
                // If we have no template either, start with a blank slate.
                $config = new JsonObject([]);
                yield $indent . $indent . 'Failed to load template file "' . $fileName . '". Creating new file instead.' . PHP_EOL;
            }
        }
        
        // Install additional listeners.
        foreach ($this->listenersToInstall as $listener) {
            list($eventName, $callable) = $listener;
            $this->addListenerToConfig($config, $eventName, $callable, $this->appAlias);
        }
        
        // Save altered config.
        try {
            $configArray = $config->getValue();
            
            file_put_contents(
                $this->getTargetPath(),
                JsonDataType::encodeJson($configArray, true)
            );
            yield $indent . 'Successfully installed static event listeners!' . PHP_EOL . PHP_EOL;
        } catch (\Throwable $exception) {
            yield $indent . 'Failed to install static listeners:' . $exception->getMessage() . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Adds a listener to the configuration for a specified event name, ensuring that the callable
     * is registered and the source is tracked.
     *
     * @param JsonObject $config The config object you wish to modify.
     * @param string     $eventName The name of the event to which the listener will be added.
     * @param callable   $callable The callable function or method to register as the listener.
     * @param string     $source Associate the listener with a specific source, which allows for clean alterations later on.
     *
     * @return void
     */
    protected function addListenerToConfig(JsonObject $config, string $eventName, callable $callable, string $source) : void
    {
        // Add callable as a listener.
        $pathCallables = '$.' . EventManager::CFG_STATIC_LISTENERS . "['" . $eventName . "']";
        if(false === $callables = $config->get($pathCallables . '[*]')) {
            $config->set($pathCallables, [$callable]);
        } elseif (!in_array($callable, $callables, true)) {
            $callables[] = $callable;
            $config->set($pathCallables, $callables);
        }

        // Track source.
        $pathSources = '$.' . self::CFG_SOURCES . "['" . $eventName . "']['" . $source . "']";
        if(false === $sources = $config->get($pathSources . '[*]')) {
            $config->set($pathSources, [$callable]);
        } elseif (!in_array($source, $sources, true)) {
            $sources[] = $callable;
            $config->set($pathSources, $sources);
        }
    }

    /**
     * Removes a source and its associated listeners from the configuration array.
     *
     * @param array  $configArray The configuration array, passed by reference, from which sources and listeners will be removed.
     * @param string $source The specific source to be removed from the configuration.
     *
     * @return void
     */
    protected function removeSourceFromConfig(array &$configArray, string $source) : void
    {
        // No sources to remove, return.
        if(!key_exists(self::CFG_SOURCES, $configArray) || 
            !key_exists(EventManager::CFG_STATIC_LISTENERS, $configArray)
        ) {
            return;
        }
        
        $listeners = &$configArray[EventManager::CFG_STATIC_LISTENERS];
        $sources = &$configArray[self::CFG_SOURCES];
        
        // Remove the source from every event.
        foreach ($sources as $eventName => &$sourcesPerEvent) {
            // Get all callables for this event associated with this source. 
            $associatedCallables = $sourcesPerEvent[$source];
            // If no callables are associated with this source, move on.
            if($associatedCallables === null) {
                continue;
            }

            // Filter out removed callables that are still represented by other sources.
            unset($sourcesPerEvent[$source]);
            foreach ($sourcesPerEvent as $remainingCallables) {
                foreach ($associatedCallables as $index => $removedCallable) {
                    if(in_array($removedCallable, $remainingCallables, true)) {
                        unset($associatedCallables[$index]);
                    }
                }
                
                // If all callables are accounted for, continue with the next event.
                if(empty($associatedCallables)) {
                    continue 2;
                }
            }

            // Get all listeners for the event we just processed.
            $listenersPerEvent = &$listeners[$eventName];
            if(empty($listenersPerEvent)) {
                continue;
            }

            // Remove listeners for each callable that is no longer represented by any source.
            foreach ($associatedCallables as $callable) {
                $index = array_search($callable, $listenersPerEvent, true);
                if($index !== false) {
                    unset($listenersPerEvent[$index]);
                }
                
                // Remove event and sources if it has no listeners.
                if(empty($listenersPerEvent)) {
                    unset($listeners[$eventName]);
                    unset($sources[$eventName]);
                    break;
                }
            }
        }
    }

    /**
     * @return string
     */
    protected function getTargetPath() : string
    {
        return $this->filepath;
    }

    /**
     * @return string
     */
    protected function getTemplatePath() : string
    {
        return $this->getWorkbench()->getCoreApp()->getDirectoryAbsolutePath()
            . DIRECTORY_SEPARATOR . 'Config' 
            . DIRECTORY_SEPARATOR . EventManager::FILE_STATIC_LISTENERS;
    }

    /**
     * @return string
     */
    protected function getPathToConfigFolder() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR;
    }

    /**
     * Adds a listener be installed for a specified event.
     *
     * @param string   $eventName The name of the event to listen for.
     * @param callable $callable The callable to be executed when the event is triggered.
     * @return StaticEventListenerInstaller Returns the current instance for method chaining.
     */
    public function addListenerToInstall(
        string $eventName,
        callable $callable,
    ) : StaticEventListenerInstaller
    {
        $this->listenersToInstall[] = [$eventName, $callable];
        return $this;
    }
}