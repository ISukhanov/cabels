<?php

require_once 'xmlbuilder.php';

final class OutControl extends XMLBuilder {

    // настройка
    protected $get_access = array ('xml', 'xsl', 'css');

    // входные данные
    private $modname = '';
    private $actname = '';

	private $user_type;

    protected $tagname = '';
    protected $xsl = '';
    protected $css = '';
    protected $params = array ();

    // выходные данные
    protected $xml = '';

    /**
     * Отдать данные из метода в ядро.
     *
     * Единственный предусмотренные в системе способ общения модулей с ядром.
     * Экшн обязан выполнять следующие действия:
     * - инстанциировать объект класса OutControl с правильными параметрами;
     * - наполнить этот объект существенными параметрами;
     * - вернуть этот объект.
     *
     * В качестве первого параметра всегда следует указывать $this->modname
     * (в контексте экшна это всегда будет имя модуля). В качестве второго -- __FUNCTION__.
     * Это будет имя экшна. Остальные два параметра необязательны.
     *
     * Через параметр $xsl можно установить желаемый путь к шаблону, который будет
     * подгружен вместо стандартного <user_group>/<module>_<action>.xsl. Если выставить $xsl = false,
     * то шаблон не будет подгружен вовсе.
     *
     * Через параметр $css можно передать желаемый путь к стилевой таблице.
     * Если его не указать, будет подгружен стандартный <module>.css. Если указать $css = false,
     * то css не будет подгружаться.
     *
     * @param string $modname
     * @param string $actname
     * @param string or false $xsl
     * @param string or false $css
     */
    public function __construct($modname, $actname, $user_type = 'user', $xsl = '', $css = '') {
        global $cmsConfig;

        $this->modname = $modname;
        $this->actname = $actname;
        $this->tagname = $modname . '_' . $actname;
        $this->user_type = $user_type;
        if (is_string($xsl)) {
            $this->xsl = $cmsConfig['templatesPath'] . '/' . $this->user_type . '/' . (empty ($xsl) ? $this->tagname : $xsl) . '.xsl';
        }
        if (is_string($css)) {
            if (empty ($css))
                $this->css = $modname;
            else
                $this->css = $css;
        }
    }
}
?>
