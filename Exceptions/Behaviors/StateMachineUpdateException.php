<?php namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetWriteError;

/**
 * Exception thrown if during an update a forbidden transition between StateMachineStates
 * is detected, or an attribute, which is disabled in a certain StateMachineState, has been
 * changed.
 *
 * @author Stefan Leupold
 *
 */
class StateMachineUpdateException extends DataSheetWriteError {
	
}
