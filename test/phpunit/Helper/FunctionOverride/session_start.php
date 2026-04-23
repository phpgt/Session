<?php
namespace GT\Session;

use GT\Session\Test\Helper\FunctionMocker;

function session_start() {
	FunctionMocker::$mockCalls["session_start"] []= func_get_args();

	if(isset(FunctionMocker::$callState["session_start__fail"])) {
		return false;
	}

	return true;
}
