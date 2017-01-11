<?php namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetWriteError;

/**
 * Exception thrown if during an update a forbidden transition between states
 * is detected, or an attribute, which is disabled in a certain state, has been
 * changed.
 *
 * @author Stefan Leupold
 */
class StateMachineUpdateException extends DataSheetWriteError {
	
}
