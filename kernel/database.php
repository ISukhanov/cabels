<?php
require_once dirname(__FILE__) . '/../setup/setup.php';

class DBWriter
{
    private static $DBID = 'main';

    /**
     * Изменяет идентификатор базы
     *
     * @param string
     */
    public static function switchDB($db)
    {
        self::$DBID = $db;
    }

    /**
     * Выполняет запрос к БД, возвращает last_insert_id, если имеется.
     *
     * @param string $query
     * @return string
     */
    public static function rawQuery($query)
    {
        return DB::Get(self::$DBID)->idQuery($query);
    }

    /**
     * Экринирует данные перед вставкой в БД.
     *
     * @param string $var
     * @return string
     */
    public static function escape($var)
    {
        return DB::Get(self::$DBID)->escape($var);
    }

    /**
     * Выполняет insert-запрос
     *
     * @param string имя таблицы (экранировать не надо)
     * @param список полей в формате Array(имя поля => значение, ...). Экранировать имена полей не надо
     * @return string
     */
    public static function Insert($table, $valueList)
    {
        $fl = Array();
        $vl = Array();
        foreach ($valueList as $fn=>$val)
        {
            $fl[] = '`'.$fn.'`';
            $vl[] = '"'.self::escape($val).'"';
        }

        $query = 'INSERT INTO `'.$table.'` ('.implode(',', $fl).') VALUES ('.implode(', ',$vl).')';
        return DB::Get(self::$DBID)->idQuery($query);
    }

    /**
     * Выполняет update-запрос
     *
     * @param $table string имя таблицы (экранировать не надо)
     * @param $valueList array список полей в формате Array(имя поля => значение, ...). Экранировать имена полей не надо
     * @param $where string строка
     * @param $limit int по умолчанию - 1
     * @return bool
     */
    public static function Update($table, $valueList, $where, $limit = 1)
    {
        $fl = Array();
        foreach($valueList as $fn=>$val)
        {
            $fl[] = '`'.$fn.'` = "'.self::escape($val).'"';
        }
        //fetch any id from where clause
        $oname = '';
        $tmp = explode('=', $where);
        if (isset($tmp[0]))
        {
            $oname = trim($tmp[0]);
        }

        $query =
            'UPDATE `' . $table . '` ' .
            'SET ' . implode(',', $fl) . ' ' .
            'WHERE ' . $where .
            (!empty($oname) ? ' ORDER BY ' . $oname : '' ) .
            ($limit == 'no' ? '' : ' LIMIT ' . $limit)
        ;
        DB::Get(self::$DBID)->nullQuery($query);
        return true;
    }

    /**
     * Выполняет удаление данных
     *
     * @param таблица
     * @param where-строка
     * @param limit, по умолчанию - отсутствует. Если подать = 0, тоже отсутствует.
     * @return bool
     */
    public static function Delete($table, $where, $limit = 0)
    {
        $query = 'DELETE FROM `'.$table.'` WHERE '.$where;
        if ($limit > 0)
        {
            $query .= ' LIMIT '.$limit;
        }
        DB::Get(self::$DBID)->nullQuery($query);
        return true;
    }
	
	/**
	 * @static
	 * @param string $procName
	 * @param mixed $args,...
	 * @return mixed
	 */

    public static function Call()
    {
        $argList = func_get_args();
        $procName = $argList[0];
        unset($argList[0]);
        $values = Array();
        if (isset($argList[1]) && is_array($argList[1]))
        {
            foreach($argList[1] as $one)
            {
                $values[] = '"'.self::escape($one).'"';
            }
        }
        else
        {
            foreach($argList as $one)
            {
                $values[] = '"'.self::escape($one).'"';
            }
        }
        $query = 'CALL `'.$procName.'` ('.implode(', ', $values).')';
        return DB::Get(self::$DBID)->idQuery($query);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////

class DBReader
{
    private static $DBID = 'main';

    /**
     * Изменяет идентификатор базы
     *
     * @param string
     */
    public static function switchDB($db)
    {
        self::$DBID = $db;
    }

	public static function rawQuery($query)
	{
	    return DB::Get(self::$DBID)->readValue($query);
	}

    private static function _prepareCall($argList)
    {
        $procName = $argList[0];
        unset($argList[0]);
        $values = Array();
        if (isset($argList[1]) && is_array($argList[1]))
        {
            foreach($argList[1] as $one)
            {
                $values[] = '"'.DB::Get(self::$DBID)->escape($one).'"';
            }
        }
        else
        {
            foreach($argList as $one)
            {
                $values[] = '"'.DB::Get(self::$DBID)->escape($one).'"';
            }
        }
        return 'CALL `'.$procName.'` ('.implode(', ', $values).')';
    }

    /**
     * Экринирует данные перед вставкой в БД.
     *
     * @param string $var
     * @return string
     */
    public static function escape($var)
    {
        return DB::Get(self::$DBID)->escape($var);
    }

    /**
     * Вызывает хранимую процедуру, возвращает набор записей
     * @param $procName -- имя процедуры
     * @param $argList - список аргументов процедуры через запятую
     *
     * @return Array
     */
    public static function callTable()
    {
        $args = func_get_args();
        $query = self::_prepareCall($args);
        return DB::Get(self::$DBID)->readArray($query);
    }

    /**
     * Вызывает хранимую процедуру, возвращает одну запись
     * @param $procName -- имя процедуры
     * @param $argList - список аргументов процедуры через запятую
     *
     * @return Array
     */
    public static function callRow()
    {
        $args = func_get_args();
        $query = self::_prepareCall($args);
        return DB::Get(self::$DBID)->readRow($query);
    }

    /**
     * Вызывает хранимую процедуру, возвращает одно значение (скаляр)
     * @param $procName -- имя процедуры
     * @param $argList - список аргументов процедуры через запятую
     *
     * @return string
     */
    public static function callValue()
    {
        $args = func_get_args();
        $query = self::_prepareCall($args);
        return DB::Get(self::$DBID)->readValue($query);
    }

    private static function _prepareQuery($fields, $table, $where, $order, $limit)
    {
        if ($fields != '')
            $query = 'SELECT ' . $fields . ' FROM ' . $table;
        else
            $query = 'SELECT * FROM ' . $table;
        if (!empty($where))
            $query .= ' WHERE ' . $where;
        if (!empty($order))
            $query .= ' ORDER BY ' . $order;
        if (!empty($limit))
            $query .= ' LIMIT ' . $limit;
        return $query;
    }

    /**
     * Выполняет SELECT запрос к базе, возвращает набор записей
     *
     * @param список полей, строка для подстановки в запрос. Если пустая - ставится *
     * @param название таблицы
     * @param условие на выборку
     * @param порядок сортировки
     * @param границы выборки
     * @return Array
     */
    public static function selectArray($fields, $table, $where = '', $order = '', $limit = '')
    {
        $query = self::_prepareQuery($fields, $table, $where, $order, $limit);
        return DB::Get(self::$DBID)->readArray($query);
    }

    /**
     * Выполняет SELECT запрос к базе, возвращает одну запись
     *
     * @param список полей, строка для подстановки в запрос. Если пустая - ставится *
     * @param название таблицы
     * @param условие на выборку
     * @param порядок сортировки
     * @param границы выборки
     * @return Array
     */
    public static function selectRow($fields, $table, $where = '', $order = '', $limit = '')
    {
        $query = self::_prepareQuery($fields, $table, $where, $order, $limit);
        return DB::Get(self::$DBID)->readRow($query);
    }

    /**
     * Выполняет SELECT запрос к базе, возвращает одно значение
     *
     * @param список полей, строка для подстановки в запрос. Если пустая - ставится *
     * @param название таблицы
     * @param условие на выборку
     * @param порядок сортировки
     * @param границы выборки
     * @return string
     */
    public static function selectValue($fields, $table, $where = '', $order = '', $limit = '')
    {
        $query = self::_prepareQuery($fields, $table, $where, $order, $limit);
        return DB::Get(self::$DBID)->readValue($query);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////

class DB
{
    private $dbLink = false;
    private $argList;
    private $procName;
    private $args;

    private function __construct($dbid)
    {
        global $cmsConfig; //хорошо бы конфиг сделать статик-классом.

        if (!isset($cmsConfig['db']))
        {
            throw new Exception('No database info in config file');
        }

        if (!isset($cmsConfig['db'][$dbid]))
        {
            throw new Exception('Unknown dbid: ' . $dbid);
        }

        if(!isset($cmsConfig['db'][$dbid]['host']) ||
           !isset($cmsConfig['db'][$dbid]['user']) ||
           !isset($cmsConfig['db'][$dbid]['pass']) ||
           !isset($cmsConfig['db'][$dbid]['name']) ||
           !isset($cmsConfig['db'][$dbid]['port']) ||
           !isset($cmsConfig['db'][$dbid]['timeout']))
        {
                throw new Exception('Database '.$dbid.' misconfigurated.');
        }

        $fp = @fsockopen(
            $cmsConfig['db'][$dbid]['host'],
            $cmsConfig['db'][$dbid]['port'],
            $errno,
            $errstr,
            $cmsConfig['db'][$dbid]['timeout']
        );
        if (! $fp) $dbid = 'alter';
        if (is_resource($fp)) fclose($fp);

        try {
            $this->dbLink = new mysqli(
                $cmsConfig['db'][$dbid]['host'],
                $cmsConfig['db'][$dbid]['user'],
                $cmsConfig['db'][$dbid]['pass'],
                $cmsConfig['db'][$dbid]['name'],
                $cmsConfig['db'][$dbid]['port']
            );
        }
        catch (Exception $e) {
            echo 'Database Connection Error';
            die;
        }
        $this->dbLink->query('SET NAMES utf8');
    }

    public function escape($string)
    {
        return $this->dbLink->real_escape_string($string);
    }

    /**
     * Возвращает экземпляр базы, идентифицируемый строкой.
     *
     * @param string $dbid
     * @return DB
     */
    public static function Get($dbid = 'main')
    {
        static $links;
        if (!is_array($links))
        {
            $links = Array();
        }

        if (!isset($links[$dbid])) {
            $links[$dbid] =  new DB($dbid);
        }

        return $links[$dbid];
    }

    public function query ($query) {
        $query = trim ($query);
        if (empty($query)) return false;
        $res = $this->dbLink->query($query);
        if ($e = $this->dbLink->error)
            throw new Exception('Query = ' . $query . '; Error = ' . $e);
        return $res;
    }

    public function idQuery($query)
    {
        $this->dbLink->query($query);
        if ($e = $this->dbLink->error)
        {
            throw new Exception('Query = ' . $query . '; Error = ' . $e);
        }
        return $this->dbLink->insert_id;
    }

    public function nullQuery($query)
    {
	    $res = $this->query($query);
        $this->clear_result($res);
        return true;
    }

    public function readArray($query)
    {
        $res = $this->query($query);
        $rv = Array();
        while($data = $res->fetch_assoc())
        {
            $rv[] = $data;
        }
        $this->clear_result($res);
        return $rv;
    }

    public function readRow($query)
    {
        $res = $this->query($query);
        $rv = Array();
        if($data = $res->fetch_assoc())
        {
            $rv = $data;
        }
        $this->clear_result($res);
        return $rv;
    }

    public function readValue($query)
    {
        $res = $this->query($query);
        $rv = false;
        if ($data = $res->fetch_array(MYSQLI_NUM))
        {
            $rv = $data[0];
        }
        $this->clear_result($res);
        return $rv;
    }

    public function next_result()
    {
        return $this->dbLink->next_result();
    }

    public function more_results()
    {
        return $this->dbLink->more_results();
    }

    public function clear_result(&$res) {
        if ($this->more_results()) while ($this->next_result());
        if ($res instanceof mysqli_result) $res->free_result();
    }

    public function __destruct()
    {
        $this->dbLink->close();
    }
}
?>
