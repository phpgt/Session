<?php
namespace GT\Session;
function session_id() {
	\GT\Session\Test\Helper\FunctionMocker::$mockCalls["session_id"] []= func_get_args();
	return "TEST";
}