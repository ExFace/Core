<?php

namespace exface\Core\CommonLogic\Log\Handlers;


use exface\Core\CommonLogic\Log\LogHandlerInterface;

interface FileHandlerInterface extends LogHandlerInterface {
	public function setFilename($filename);
}
