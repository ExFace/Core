<?php
namespace exface\Core\Actions;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\CommonLogic\Model\ActionList;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * This action chains other actions together.
 * 
 * All actions in the action-array will be performed one-after-another in the order of definition. Every action receives
 * the result data of it's predecessor as input. The first action will get the input from the chain action. The result
 * of the action chain is the result of the last action.
 * 
 * Here is a simple example:
 * {
 * 	"alias": "exface.Core.ActionChain",
 * 	"actions": [
 * 		{
 * 			"alias": "my.app.CreateOrder",
 * 			"name": "Order",
 * 			"input_rows_min": 0
 * 		},
 * 		{
 * 			"alias": "my.app.PrintOrder"
 * 		}
 * 	]
 * }
 * 
 * As a rule of thumb, the action chain will behave as the first action in the it: it will inherit it's name, input restrictions, etc.
 * Thus, in the above example, the action chain would inherit the name "Order" and "input_rows_min=0". However, there are some
 * important exceptions from this rule:
 * - the chain has modified data if at least one of the actions modified data
 * - the chain has all interfaces it's actions have
 * 
 * By default, all actions in the chain will be performed in a single transaction. That is, all actions will get rolled back if at least
 * one failes. Set the property "use_single_transaction" to false to make every action in the chain run in it's own transaction.
 * 
 * Action chains can be nested - an action in the chain can be another chain. This way, complex processes even with multiple transactions
 * can be modeled (e.g. the root chain could have use_single_transaction disabled, while nested chains would each have a wrapping transaction).
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionChain extends AbstractAction {
	private $actions = array();
	private $use_single_transaction = true;
	private $output = '';
	
	protected function init(){
		parent::init();
		$this->actions = new ActionList($this->get_workbench(), $this);
	}
	
	protected function perform(){
		if ($this->get_actions()->is_empty()){
			throw new ActionConfigurationError($this, 'An action chain must contain at least one action!', '6U5TRGK');
		}
		
		$result = null;
		$output = '';
		$data = $this->get_input_data_sheet()->copy();
		foreach ($this->get_actions() as $action){
			// Prepare the action
			// All actions obviously run in the same template
			$action->set_template_alias($this->get_template_alias());
			// They are all called by the widget, that called the chain
			if ($this->get_called_by_widget()){
				$action->set_called_by_widget($this->get_called_by_widget());
			}
			// If the chain should run in a single transaction, this transaction must be set for every action to run in
			if ($this->get_use_single_transaction()){
				$action->set_transaction($this->get_transaction());
			}
			// Every action gets the data resulting from the previous action as input data
			$action->set_input_data_sheet($data);
			
			// Perform
			$data = $action->get_result_data_sheet();
			$output = $action->get_result_output();
			$result = $action->get_result();
			$this->add_result_message($action->get_result_message() . "\n");
			if ($action->is_data_modified()){
				$this->set_data_modified(true);
			}
		}
		if ($data){
			$this->set_result_data_sheet($data);
		}
		$this->set_result($result);
		$this->output = $output;
	}
	
	/**
	 * 
	 * @return ActionList|ActionInterface[]
	 */
	public function get_actions() {
		return $this->actions;
	}
	
	public function set_actions($array_or_uxon_or_action_list) {
		if ($array_or_uxon_or_action_list instanceof ActionList){
			$this->actions = $array_or_uxon_or_action_list;
		} elseif ($array_or_uxon_or_action_list instanceof \stdClass){
			// TODO
		} elseif (is_array($array_or_uxon_or_action_list)){
			foreach ($array_or_uxon_or_action_list as $nr => $action_or_uxon){
				if ($action_or_uxon instanceof \stdClass){
					$action = ActionFactory::create_from_uxon($this->get_workbench(), $action_or_uxon);
				} elseif ($action_or_uxon instanceof ActionInterface){
					$action = $action_or_uxon;
				} else {
					throw new ActionConfigurationError($this, 'Invalid chain link of type "' . gettype($action_or_uxon) . '" in action chain on position ' . $nr . ': only actions or corresponding UXON objects can be used as!', '6U5TRGK');
				}
				$this->add_action($action);
			}
		}
		
		return $this;
	}
	
	public function add_action(ActionInterface $action){
		$this->get_actions()->add($action);
		return $this;
	}
	
	public function get_use_single_transaction() {
		return $this->use_single_transaction;
	}
	
	public function set_use_single_transaction($value) {
		$this->use_single_transaction = $value ? true : false;
		return $this;
	}
	
	public function get_result_output(){
		return $this->output;
	}
	
	public function get_input_rows_min(){
		return $this->get_actions()->get_first()->get_input_rows_min();
	}
	
	public function get_input_rows_max(){
		return $this->get_actions()->get_first()->get_input_rows_max();
	}
	
	public function is_undoable(){
		return false;
	}
	
	public function get_name(){
		if (!parent::has_name()){
			return $this->get_actions()->get_first()->get_name();
		}
		return parent::get_name();
	}
	
	public function get_icon_name(){
		return parent::get_icon_name() ? parent::get_icon_name() : $this->get_actions()->get_first()->get_icon_name();
	}
	
	/* TODO
	public function implements_interface($interface){
		$answer = false;
		foreach ($this->get_actions() as $action){
			if ($action->implements_interface($interface)){
				$answer = true;
			}
		}
		return $answer;
	}*/
	
	/**
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 */
	/* TODO
	public function __call($method, $arguments){
		foreach ($this->get_ac)
		return call_user_func_array(array($this->get_action(), $method), $arguments);
	}*/

}
?>