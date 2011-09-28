<?php
abstract class Module {
    const DEFAULT_METHOD = 'defaultAction';

    protected $modname; // имя модуля необходимо для составления путей до кода и до шаблонов
    /**
     * Объект, содержащий информацию о пользователе, обратившемся к странице.
     *
     * @var User
     */

    final public function __construct($name = '') {
        if (empty($name)) throw new Exception('Module name can not be empty');
        $this->modname = $name;
        if (method_exists($this, 'init'))
            $this->init();
    }

    final public function run ($action, $params) {
        global $cmsConfig;
        if (empty($action)) $action = self::DEFAULT_METHOD;
        if (! method_exists($this, $action))
        {
            if ($cmsConfig['errorsLevel'] == ERRORS_LEVEL_HARD)
            {
                print 'Module "' . $this->modname . '" does not have method "' . $action . '"; ';
            }
            $action = self::DEFAULT_METHOD;
        }
        $return = $this->$action ($params);
        if ($return && $return instanceof OutControl) {
            $return->buildXML();
            return $return;
        }
        return false;
    }
}
?>
