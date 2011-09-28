<?php

	require_once 'common.php';
	class Module_Index extends Common_Index
	{

		public function defaultAction()
		{
			$out = new OutControl($this->modname, __FUNCTION__);

			$out->addParam('test', array(
				'type'  =>  OutControl::TAG_VALUE,
				'value' =>  'test',
			));

		    return $out;
		}

	}


?>