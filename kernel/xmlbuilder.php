<?php
abstract class XMLBuilder {
    const TAG_VALUE   = 0;
    const TAG_QUERY   = 1;
    const TAG_INCLUDE = 2;
    const TAG_MONTHQUERY = 3;
    const TAG_REQUEST  = 4;

    // настройка
    protected $get_access = array ();

    // входные данные
    protected $tagname = '';
    protected $params = array ();

    // выходные данные
    protected $xml = '';

    /**
     * Добавить набор параметров к уже накопленным.
     * В случае конфликта ключей затираются старые параметры.
     *
     * @param array $params
     */
    public function addParams ($params) {
        if (is_array($params)) $this->params = $params + $this->params;
    }

    /**
     * Добавить один параметр к уже накопленным.
     * В случае конфликта ключей стирается старый параметр.
     *
     * @param string $key
     * @param array  $value
     */
    public function addParam ($key, $value) {
        $this->params[$key] = $value;
    }

    /**
     * Сформировать выходной XML по результатам работы отдельного экшна.
     *
     * Есть два режима работы.
     * Штатный режим: вызов в Module::run, $pure = false.
     * Полученный XML примет участие в общем потоке, пройдёт обработку шаблонами.
     * Изолированный режим: вызов в экшне, $pure = true. Полученный XML уходит "как есть" на echo.
     *
     * @param array $params
     * @param boolean $pure
     * @return mixed
     */
    public function buildXML ($pure = false, $headers = true) {
        global $cmsConfig;

        $ret = $this->createXML($this->params);
        if (! $ret) return false;
        if ($pure) {
	        if ($headers)
	        {
            $xml = '<?xml version="1.0" encoding="' . $cmsConfig['dataEncoding'] . '"?>';
            $xml .= '<globalpage>' . $this->xml . '</globalpage>';
	        return $xml;
	        }
	        else
		        return $this->xml;

        }
        return true;
    }

    public function __get ($name) {
        if (in_array($name, $this->get_access)) return $this->$name;
        throw new Exception('Permission denied to access ' . get_class($this) . '::' . $name);
    }

    private function createXML(&$paramsArray)
    {
        if (!is_array($paramsArray)) return false;
        $this->xml .= '<' . $this->tagname . '>';
        $parentNode = Array();
        $this->buildNodes($paramsArray, $parentNode);
        $this->xml .= '</' . $this->tagname . '>';
        return true;
    }

    private function buildNodes(&$paramsArray, &$parentNode)
    {
        foreach ($paramsArray as $tag => &$params) {
            if (!isset($params['unescape'])) $params['unescape'] = Array();
            $params['tag'] = $tag;
            $methodName = 'buildTag' . $params['type'];
            if (is_callable(Array($this, $methodName))) {
                $this->{$methodName}($params, $parentNode);
            }
            else {
                throw new Exception(
                    'Failed to find method "' . get_class($this) . '::' . $methodName . '" for tag "' . $this->tagname . '"'
                );
            }
            if (isset($params['tags']) && is_array($params['tags']))
                $this->buildNodes($params['tags'], $params);
        }
    }

    private function buildTag0(&$params, &$parentNode) {
        $tagname = (is_numeric($params['tag'])) ? 'row' : $params['tag'];
        $this->xml .= '<' . $tagname . ' id="'. $params['tag'] .'">';
        if (is_array($params['value'])) {
            $par = Array();
            foreach ($params['value'] as $k => $v) {
                $par[$k] = Array(
                    'tag'   => $k,
                    'value' => $v,
                );
                $this->buildTag0($par[$k], $parentNode);
            }
        } else
            $this->xml .= htmlspecialchars($params['value'], ENT_QUOTES);
        $this->xml .= '</' . $tagname . '>';
    }

    private function buildTag1(&$params, &$parentNode) {
        if (!isset($params['name'])) return trigger_error('Not defined procedure name', E_USER_NOTICE);

        $this->xml .= '<' . $params['tag'];
        if (isset($params['params'])) {
            foreach ($params['params'] as $n => $v) {
                $this->xml .= ' ' . $n . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
            }
        }
        else
        {
            $params['params'] = Array();
        }
        $this->xml .= '>';

        $stmt = DBReader::callTable($params['name'], $params['params']);

        if ($stmt && (count($stmt) > 0)) {
            $num = 0;
            $rx = '/[\x01-\x09\x0b\x0c\x0e\x1f]/';
            foreach ($stmt as &$row) {
                if (isset($params['callback']) && isset($params['callback']['instance']) &&
                    method_exists($params['callback']['instance'], $params['callback']['method'])
                ) {
                    $args = array(&$row);
                    if (array_key_exists ('params', $params['callback']) && is_array($params['callback']['params'])) {
                        foreach ($params['callback']['params'] as $c) {
                            $args[] = $c;
                        }
                    }
                    call_user_func_array(Array($params['callback']['instance'], $params['callback']['method']), $args);
                    $row = $args[0];
                }
                ++$num;
                $this->xml .= '<row>';
                foreach ($row as $name => $v)
                    $this->xml .= '<' . $name . '><![CDATA['. preg_replace ($rx, '', $v) .']]></' . $name . '>';
                $this->xml .= '</row>';
            }
        }
        $this->xml .= '</' . $params['tag'] . '>';

        if (isset($params['getquantity']) && $params['getquantity'])
        {
            /*
                If we need total number of results on the criteria,
                we get it with special procedure and append to th xml
            */
            $this->xml .= '<quantity>'.DBReader::callValue('system_getQuant').'</quantity>';
        }
    }

    private function buildTag2(&$params, &$parentNode) {
        global $CMS;
        $CMS->callModule(
            $params['module'],
            isset($params['action']) ? $params['action'] : false,
            isset($params['args']) ? $params['args'] : false
        );
    }

    private function buildTag3(&$params, &$parentNode) {
        if (!isset($params['name'])) return trigger_error('Not defined procedure name', E_USER_NOTICE);

        if (!isset($params['params']) || !isset($params['params']['month']) || !isset($params['params']['year']))
            return trigger_error('For tag = TAG_MONTHQUERY params "month" and "year" requred', E_USER_NOTICE);

        $cm = $params['params']['month'];
        $cy = $params['params']['year'];

        $sdate = mktime(1,1,1,$cm, 1, $cy);
        $edate = mktime(1,1,1,$cm, date('t', $sdate), $cy);

        $this->xml .= '<' . $params['tag'];
        foreach ($params['params'] as $n => $v) {
            $this->xml .= ' ' . $n . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
        }
        $this->xml .= '>';

        $tableAnswer = DBReader::callTable($params['name'], $params['params']);

        $data = Array();

        $rx = '/[\x01-\x09\x0b\x0c\x0e\x1f]/';
        foreach ($tableAnswer as $row)
        {
            if (!isset($row['date']))
            {
                return trigger_error('Cant find field "date" in answer from '.$params['name'], E_USER_NOTICE);
            }
            if (!isset($data[$row['date']]))
            {
                $data[$row['date']] = Array();
            }
            $data[$row['date']][] = $row;
        }

        while ($edate >= $sdate)
        {
            $tdate = date('d.m.Y', $sdate);
            if (!isset($data[$tdate]))
            {
                $data[$tdate] = Array();
            }
            $sdate += 24 * 3600;
        }
        ksort($data);

        foreach ($data as $cdate => $values)
        {
            $this->xml .= '<row>';
            $this->xml .= '<date>'.$cdate.'</date>';
            foreach ($values as $row)
            {
                foreach ($row as $name => $val)
                {
                    if ($name != 'date')
                    {
                        $this->xml .= '<' . $name . '><![CDATA['. preg_replace ($rx, '', $val) .']]></' . $name . '>';
                    }
                }
            }

            $this->xml .= '</row>';
        }
        $this->xml .= '</' . $params['tag'] . '>';
    }

    //заглушка, отдает пока только объект Request, надо допиливать.
    private function buildTag4(&$params, &$parentNode)
    {
    	if (!isset($params['list']) || !is_array($params['list']))
    	{
    		trigger_error('Not defined variable list for request object', E_USER_NOTICE);
    	}

    	$this->xml .= '<' . $params['tag'] . ' id="'. $params['tag'] .'">';
    	if ((isset($params['needrow'])) && ($params['needrow']) )
    	{
    		$this->xml .= '<row>';
    	}
        foreach ($params['list'] as $vname => $vtype)
        {
        	$currentValue = $params['value']->{$vname};
        	switch($vtype[1]) //тип параметра
        	{
        		case Request::TYPE_INT :
                case Request::TYPE_FLOAT :
        			$this->xml .= '<' . $vname . '>' . $currentValue . '</' . $vname . '>';
        		break;
        		case Request::TYPE_STRING :
                    $this->xml .= '<' . $vname . '>' . htmlspecialchars($currentValue, ENT_QUOTES) . '</' . $vname . '>';
        		break;
        		case Request::TYPE_ARRAY :
        			$this->xml .= '<'. $vname .'>';
        			foreach ($currentValue as $cKey => $cValue)
        			{
        				$this->xml .= '<row id="' . $cKey . '">'.$cValue.'</row>';
        			}
                    $this->xml .= '</'. $vname .'>';
        	    break;
        		case Request::TYPE_FILE :
        		break;
        	}
        }
    	if ((isset($params['needrow'])) && ($params['needrow']) )
    	{
    		$this->xml .= '</row>';
    	}

        $this->xml .= '</' . $params['tag'] . '>';
    }
}
?>
