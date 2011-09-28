<?php
	/**
	 * User: ReBeL
	 * Date: 28.09.11
	 * Time: 22:21
	 */

	class Common_Index extends Module
	{
		private $menuitem = array ();
		
		public function defaultAction()
		{
			global $CMS, $cmsConfig;

			// получаем урл, по которому обратились до рерайта
			$path = isset($_SERVER['REQUEST_URI']) ? (strpos($_SERVER['REQUEST_URI'], '?') ? substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')) : $_SERVER['REQUEST_URI']) : '/';
			// конвертим входные данные в utf-8
			if (!empty($_SERVER['QUERY_STRING']))
			{
				$path = str_replace('?' . $_SERVER['QUERY_STRING'], '', $path);
				$_SERVER['QUERY_STRING'] = Utils::toUtf8(urldecode($_SERVER['QUERY_STRING']));
			}

			// отрежем последний слэш (кроме корня)
			if (substr($path, -1) == '/' && strlen($path) > 1) $path = substr($path, 0, -1);

			// пользовательскый интерфейс.

				$this->menuitem = DBReader::callRow('system_getMenuItemByPath', addslashes($path));
				if ((!$this->menuitem) || (empty($this->menuitem))) $CMS->callModule('index', 'error404', $path);

				if ($this->menuitem['inherit'])
				{
					// собираем наследованные фильтры
					$filters = DBReader::callTable('system_getParentActions', 1, addslashes($path));
					foreach ($filters as $oneRow)
					{
						if (!isset($CMS->actions[$oneRow['menu_action_id']]))
						{
							if (!array_key_exists($oneRow['menu_action_id'], $CMS->actions))
								$CMS->actions[$oneRow['menu_action_id']] = array();
							$CMS->actions[$oneRow['menu_action_id']]['code'] = array(); // объектный код десериализуется ниже
							$CMS->actions[$oneRow['menu_action_id']]['module_name'] = $oneRow['module_name'];
							$CMS->actions[$oneRow['menu_action_id']]['action_name'] = $oneRow['action_name'];
						}

						$args = unserialize($oneRow['object_code']);

						if (is_array($args))
							$CMS->actions[$oneRow['menu_action_id']]['code'] += $args;

						if (!isset($CMS->actionsHash[$oneRow['module_name']]))
							$CMS->actionsHash[$oneRow['module_name']] = array();

						if (!isset($CMS->actionsHash[$oneRow['module_name']][$oneRow['action_name']]))
							$CMS->actionsHash[$oneRow['module_name']][$oneRow['action_name']] = array();

						$CMS->actionsHash[$oneRow['module_name']][$oneRow['action_name']][] = $oneRow['menu_action_id'];
					}
				}
				$CMS->appendXml('<http_host>' . base64_encode(strtolower($_SERVER['HTTP_HOST'])) . '</http_host>');
				$CMS->appendXml('<http_ref>' . base64_encode(strtolower($_SERVER['REQUEST_URI'])) . '</http_ref>');
				$CMS->appendXml('<refferer>' . base64_encode(
						array_key_exists('HTTP_REFERER', $_SERVER) ?
							strtolower($_SERVER['HTTP_REFERER']) :
							'index'
					) . '</refferer>');
				//$CMS->appendXml('<random_logo>'. rand(1, 5) .'</random_logo>');
				if (array_key_exists('debugMode', $cmsConfig) && $cmsConfig['debugMode'])
				{
					$CMS->appendXml('<debugMode>1</debugMode>');
				}

				// обработка центрального блока
				$CMS->callModule('index', 'content', $path);
				// обработка обрамляющих фильтров
				if (is_array($CMS->actions))
				{
					foreach ($CMS->actions as $filter)
					{
						$CMS->callModule($filter['module_name'], $filter['action_name'], $filter['code']);
					}
				}
			$out = new OutControl($this->modname, __FUNCTION__, false, $this->modname . '_' . $this->menuitem['view_name']);

			return $out;
		}

		public function content($path = '')
		{
			global $CMS;


			// собственно центральный контент
			$CMS->appendXml('<index_content>');

			// вызов локальных экшнов
			$r = DBReader::callTable('system_getModulesByViewId', $this->menuitem['view_id'], $this->menuitem['menu_id']);
			foreach ($r as $mod)
			{
				$CMS->callModule($mod['module_name'], $mod['action_name'], unserialize($mod['object_code']));
			}
			$CMS->appendXml('</index_content>');
		}

	}

?>