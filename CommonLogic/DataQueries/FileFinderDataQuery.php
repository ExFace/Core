<?php
namespace exface\Core\CommonLogic\DataQueries;;

use exface\Core\CommonLogic\Filemanager;
use Symfony\Component\Finder\Finder;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DebuggerInterface;

class FileFinderDataQuery extends AbstractDataQuery
{

    private $folders = array();

    private $basePath = null;

    private $query_builder = null;

    private $fullScanRequired = false;

    private $finder = null;

    /**
     *
     * @return \Symfony\Component\Finder\Finder
     */
    public function getFinder()
    {
        if (is_null($this->finder)) {
            $this->finder = new Finder();
        }
        return $this->finder;
    }

    public function getFolders()
    {
        return $this->folders;
    }

    public function setFolders(array $patternArray)
    {
        $this->folders = $patternArray;
        return $this;
    }

    public function addFolder($relativeOrAbsolutePath)
    {
        $this->folders[] = $relativeOrAbsolutePath;
        return $this;
    }

    public function getBasePath()
    {
        return $this->basePath;
    }

    public function setBasePath($absolutePath)
    {
        if (! is_null($absolutePath)) {
            $this->basePath = Filemanager::pathNormalize($absolutePath);
        }
        return $this;
    }

    public function setFullScanRequired($value)
    {
        $this->fullScanRequired = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getFullScanRequired()
    {
        return $this->fullScanRequired;
    }

    /**
     *
     * {@inheritdoc} The finder query creates a debug panel showing the dump of the symfony finder object.
     *              
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $page = $debug_widget->getPage();
        $finder_tab = $debug_widget->createTab();
        $finder_tab->setCaption('Finder');
        /* @var $finder_widget \exface\Core\Widgets\Html */
        $finder_widget = WidgetFactory::create($page, 'Html', $finder_tab);
        $finder_widget->setValue($this->dumpFinder($debug_widget->getWorkbench()->getDebugger()));
        $finder_widget->setWidth('100%');
        $finder_tab->addWidget($finder_widget);
        $debug_widget->addTab($finder_tab);
        return $debug_widget;
    }

    protected function dumpFinder(DebuggerInterface $debugger)
    {
        return $debugger->printVariable($this, true, 5);
    }
}
?>