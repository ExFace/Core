<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowUrl;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\UrlDataType;

/**
 * Opens a URL optionally filling placeholders with input data.
 * 
 * The URL defined in the `url` parameter can contain placeholders, that will be replaced 
 * by values from input data columns with the same names: e.g. the placeholder `[#PATH#]`,
 * will be replaced by the value of the `PATH` column in the first row of the action's 
 * input data. Placeholder values are url-encoded automatically unless you set
 * `urlencode_placeholders` to `false` - e.g. if the entire URL comes from the placeholder.
 * 
 * Additionally you can use `open_in_new_window` to force opening an new browser tab or
 * window.
 * 
 * ## Examples
 * 
 * Open an URL with input data parameter (e.g. from the selected row of a table). The input
 * data of the action should have a column with attribute alias `owner` of course - otherwise
 * the placeholder will be replaced by an empty value.
 * 
 * ```
 * {
 *  "alias": "exface.Core.GoToUrl",
 *  "url": "https://github.com/[#owner#]"
 * }
 * 
 * ```
 * 
 * Open an URL contained in the input data in a new window.
 * 
 * ```
 * {
 *  "alias": "exface.Core.GoToUrl",
 *  "url": "[#url#]",
 *  "urlencode_placeholders": false,
 *  "open_in_new_window": true
 * }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class GoToUrl extends AbstractAction implements iShowUrl
{

    private $url = null;

    private $open_in_new_window = false;
    
    private $open_in_browser_widget = null;

    /**
     * @var boolean
     */
    protected  $urlencode_placeholders = null;

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setIcon(Icons::EXTERNAL_LINK);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowUrl::getUrl()
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Defines the URL to navigate to.
     * 
     * @uxon-property url
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\Actions\iShowUrl::setUrl()
     */
    public function setUrl($value)
    {
        $this->url = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $vars = array();
        $vals = array();
        foreach ($this->getInputDataSheet($task)->getRow(0) as $var => $val) {
            $vars[] = '[#' . $var . '#]';
            $vals[] = urlencode($val);
        }
        
        $result = str_replace($vars, $vals, $this->getUrl());
        $result = filter_var($result, FILTER_SANITIZE_STRING);
        
        $result = ResultFactory::createUriResult($task, $result);
        $result->setMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.GOTOURL.SUCCESS'));
        if ($this->getOpenInNewWindow()) {
            $result->setOpenInNewWindow(true);
        }
        
        return $result;
    }

    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool
    {
        return $this->open_in_new_window;
    }

    /**
     * Set to TRUE to make the page open in a new browser window or tab (depending on the browser).
     * 
     * @uxon-property open_in_new_window
     * @uxon-type bool
     * 
     * @param bool|string $value
     * @return \exface\Core\Actions\GoToUrl
     */
    public function setOpenInNewWindow($value) : GoToUrl
    {
        $this->open_in_new_window = BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getUrlencodePlaceholders() : bool
    {
        if ($this->urlencode_placeholders === null) {
            // If we have a url already, see if it consists of a single placeholder. In that case, do not
            // urlencode it as it is the URL itself.
            if ($this->url) {
                if (count(StringDataType::findPlaceholders($this->url)) === 1 && trim(StringDataType::replacePlaceholders($this->url, [], false)) === '') {
                    $this->urlencode_placeholders = false;
                } else {
                    $this->urlencode_placeholders = true;
                }
            }
        }
        return $this->urlencode_placeholders ?? true;
    }

    /**
     * Makes all placeholders get encoded and thus URL-safe if set to TRUE (default).
     * 
     * Use FALSE if placeholders are ment to use as-is. By default, all placeholders are encoded except for the
     * case that the `url` consists of a single placeholder - that is, the complete URL is provided by the data.
     * 
     * @uxon-property urlencode_placeholders
     * @uxon-type boolean
     * 
     * @param bool|string $value
     * @return \exface\Core\Actions\GoToUrl
     */
    public function setUrlencodePlaceholders($value)
    {
        $this->urlencode_placeholders = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @param int $row_nr
     * @throws ActionRuntimeError
     * @return mixed
     */
    public function buildUrlFromDataSheet(DataSheetInterface $data_sheet, $row_nr = 0)
    {
        $url = $this->getUrl();
        $placeholders = StringDataType::findPlaceholders($url);
        foreach ($placeholders as $ph){
            if ($col = $data_sheet->getColumns()->getByExpression($ph)){
                $url = str_replace('[#' . $ph . '#]', $col->getCellValue($row_nr), $url);
            } else {
                throw new ActionRuntimeError($this, 'No value found for placeholder "' . $ph . '" in URL! Make sure, the input data sheet has a corresponding column!');
            }
        }
        return $url;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getOpenInBrowserWidget() : ?string
    {
        return $this->open_in_browser_widget;
    }
    
    /**
     * Id of the Browser widget, that should be used to open the URL
     * 
     * @uxon-propert open_in_browser_widget
     * @uxon-type uxon:$..id
     * 
     * @param string $value
     * @return GoToUrl
     */
    public function setOpenInBrowserWidget(string $value) : GoToUrl
    {
        $this->open_in_browser_widget = $value;
        return $this;
    }
}
?>