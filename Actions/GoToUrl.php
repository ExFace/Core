<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowUrl;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * This action opens a URL for a given object instance.
 * The URL can contain placeholders, that will
 * ber replaced by attribute values of the instance. This is usefull in tables, where a URL needs
 * to be opened for a specific row. Any value from that row can be passed to the URL vial placeholder [#column_id#]
 *
 * @author Andrej Kabachnik
 *        
 */
class GoToUrl extends AbstractAction implements iShowUrl
{

    private $url = null;

    private $open_in_new_window = false;

    /**
     * @uxon urlencode_placeholders Makes all placeholders get encoded and thus URL-safe if set to TRUE.
     * Use FALSE if placeholders are ment to use as-is (e.g. the URL itself is a placeholder)
     *
     * @var boolean
     */
    private $urlencode_placeholders = true;

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setIconName(Icons::EXTERNAL_LINK);
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($value)
    {
        $this->url = $value;
        return $this;
    }

    protected function perform()
    {
        $vars = array();
        $vals = array();
        foreach ($this->getInputDataSheet()->getRow(0) as $var => $val) {
            $vars[] = '[#' . $var . '#]';
            $vals[] = urlencode($val);
        }
        
        $result = str_replace($vars, $vals, $this->getUrl());
        $result = filter_var($result, FILTER_SANITIZE_STRING);
        if (substr($result, 0, 4) !== 'http') {
            $result = $this->getWorkbench()->getCMS()->createLinkToFile($result);
        }
        if ($this->getOpenInNewWindow()) {
            $result .= (strrpos($result, "?") !== false ? "&" : "?") . "target=_blank";
        }
        $this->setResult($result);
        $this->setResultMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.GOTOURL.SUCCESS'));
        $this->setResultDataSheet($this->getInputDataSheet());
        return $this;
    }

    public function getOpenInNewWindow()
    {
        return $this->open_in_new_window;
    }

    public function setOpenInNewWindow($value)
    {
        $this->open_in_new_window = $value;
        return $this;
    }

    public function getUrlencodePlaceholders()
    {
        return $this->urlencode_placeholders;
    }

    public function setUrlencodePlaceholders($value)
    {
        $this->urlencode_placeholders = $value;
        return $this;
    }
}
?>