<?php

namespace exface\Core\CommonLogic\Log\Handlers;


use exface\Core\Interfaces\LogHandlerInterface;

interface AbstractFileHandler extends LogHandlerInterface {
	public function setFilename($filename);
}
