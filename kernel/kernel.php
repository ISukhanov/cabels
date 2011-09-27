<?php
require_once dirname(__FILE__) . '/../setup/setup.php';
require_once dirname(__FILE__) . '/database.php';
require_once dirname(__FILE__) . '/module.php';
require_once dirname(__FILE__) . '/outcontrol.php';
require_once dirname(__FILE__) . '/request.php';
require_once dirname(__FILE__) . '/user.php';
require_once dirname(__FILE__) . '/utils.php';
require_once dirname(__FILE__) . '/event.php';

final class Kernel {

    // реестр для инстанциированных модулей
    private $modules = array ();

    public  $actions = array ();
    public  $actionsHash = array ();
    private $xml = array ();
    private $xsl = array ();
    private $css = array ();
    private $outputHeader = 'text/html';
    private $user_prefix;
    private $debug = '';

    public function __construct() {
        global $cmsConfig;

        $this->xml[] = '<?xml version="1.0" encoding="' . $cmsConfig['dataEncoding'] . '"?><globalpage>';
        $this->xsl = array ();

        $this->user_prefix = User::getInstance()->group_prefix;
        $this->createImagePaths();

        $this->debug = new Request(array ('show' => array ()), Request::METHOD_REQUEST);
    }

    private function createImagePaths()
    {
        global $cmsConfig;
        if (!file_exists($cmsConfig['vitrinesPath'] . '/0')) // структура папок еще не создана
        {
            if (! is_writeable($cmsConfig['vitrinesPath'])) {
                throw new Exception('check write rights on ' . $cmsConfig['imagesPath']);
            }
            for ($i = 0x0; $i < 0x10; $i++)
            {
                mkdir($cmsConfig['vitrinesPath'] . '/' . strtoupper(dechex($i)));
                chmod($cmsConfig['vitrinesPath'] . '/' . strtoupper(dechex($i)), 0777);
                for ($j = 0x0; $j < 0x10; $j++)
                {
                    mkdir($cmsConfig['vitrinesPath'] . '/' . strtoupper(dechex($i)) . '/' . strtoupper(dechex($j)));
                    chmod($cmsConfig['vitrinesPath'] . '/' . strtoupper(dechex($i)) . '/' . strtoupper(dechex($j)), 0777);
                }
            }
        }
    }

    public function __get ($name) {
        if (in_array($name, array ('outputHeader'))) return $this->$name;
        throw new Exception('Permission denied to access ' . __CLASS__ . '::' . $name);
    }

    public function appendXml ($xml) {
        if (is_string($xml)) $this->xml[] = $xml;
    }
	public function appendXsl ($xsl)
	{
		if (is_string($xsl)) $this->xsl[] = $xsl;
	}

    public function callModule ($mod, $actName = false, $args = false, $user_prefix = '') {
        global $cmsConfig;
        if (empty($mod)) return false;
        if (empty ($user_prefix)) $user_prefix = $this->user_prefix;

        // используем реестр модулей вместо неудобной конструкции синглтона
        if (array_key_exists ($mod, $this->modules)) {
            $module = $this->modules[$mod];
        }
        else {
            // инстанциация модуля
            $fileName = $cmsConfig['modulePath'] . '/' . $mod . '/' . $user_prefix . '.php';
            if (! is_readable($fileName)) throw new Exception('Source file (' . $fileName . ') for module "' . $mod . '" does not exist');
            require_once $fileName;
            $modName = $cmsConfig['modulePrefix'] . ucfirst($mod);
            if (! class_exists($modName)) throw new Exception('Class "' . $modName . '" does not exist');
            $this->modules[$mod] = $module = new $modName ($mod);
            if ($this->debug->show == 'modules')
            {
                echo 'file required: '.$fileName.'<br />';
                echo 'new '.$modName.'('.$mod.')<br />';
            }
        }
        // получаем данные от модуля через специальный класс, который возвращает xml, xsl, css.
        $ret = $module->run ($actName, $args);
        if ($this->debug->show == 'modules')
        {
            echo 'Module run with param actName = '.$actName.'<br />';
        }
        if (! $ret instanceof OutControl) return false;
        $this->xml[] = $ret->xml;
        if ($xsl = $ret->xsl) {
            // поддерживаем уникальность подключенных шаблонов
            if (! in_array($xsl, $this->xsl)) {
                $this->xsl[] = $xsl;
            }
        }
        if ($css = $ret->css) {
            // поддерживаем уникальность подключенных стилевых таблиц
            if (! in_array($css, $this->css)) {
                $this->css[$css] = $css;
            }
        }
        return true;
    }

    /*
     * Компилирует только тот OutControl, который передан параметром. Никакие другие XML и XSL не попадают в результат.
     * Функция удобна, когда нужно в экшене модуля получить готовый HTML-код и, например, отправить его на почту или выгрузить в Excel.
     */
    public function compileTemplate($out){
        if (! $out instanceof OutControl) return false;
        $out->buildXML();
        $this->xml = array('<globalpage>'.$out->xml); // убираем всё лишнее, оставляем только xml для данного шаблона
        $this->xsl = array($out->xsl); // убираем всё лишнее, оставляем только xsl данного шаблона
        return $this->compile();
    }

    public function compile () {
        global $cmsConfig;
        $xml = implode('', $this->xml) . '</globalpage>';
        if (empty($xml)) return '';

        $xsl = '<?xml version="1.0" encoding="' . $cmsConfig['dataEncoding'] . '"?>' .
            '<!DOCTYPE xsl:stylesheet><xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">' .
            '<xsl:output method="xml" indent="no" encoding="' . $cmsConfig['dataEncoding'] . '"/>';
        foreach ($this->xsl as $path) {
            $xsl .= '<xsl:include href="' . $path . '"/>';
        }
        $xsl .= '<xsl:template match="/"><xsl:apply-templates/></xsl:template></xsl:stylesheet>';

        switch ($this->debug->show) {
            case 'xml': case 'xsl':
                header('Content-type: text/xml; encoding='. $cmsConfig['dataEncoding']);
                die (${$this->debug->show});
            break;
            case 'modules' :
                die();
            break;
        }

        $compiler = new XSLTProcessor();

        // загружаем xsl в процессор
        $xslHandler = new DomDocument();
        $xslHandler->loadXml($xsl);
        $compiler->importStylesheet($xslHandler);
        unset($xslHandler);

        // подставляем css в параметры
        // fix (?) у нас порядок загрузки css кривой. Надо бы сначала грузить index, а потом уж все остальное.
        // плюс добавляется мало-мало common.css, после индекса, перед всем-всем.
        $parameters = array();
        if (count ($this->css) > 0) {
            $css = '';
            if (in_array('index', $this->css))
            {
                $css = '@import \''. $cmsConfig['stylesPath'] . '/index.css\'; @import \''. $cmsConfig['stylesPath'] . '/common.css\'; ';
                unset($this->css['index']);
            }
            foreach ($this->css as $c) $css .= '@import \'' . $cmsConfig['stylesPath'] . '/' . $c . '.css\'; ';
            $parameters['_css'] = $css;
        }
        $compiler->setParameter('/', $parameters);

        // собственно компиляция
        $xmlHandler = new DomDocument();
        if ($cmsConfig['errorsLevel'] < ERRORS_LEVEL_HARD) {
            set_error_handler('HandleXmlError');
        }
        $xmlHandler->loadXml($xml);
        if ($cmsConfig['errorsLevel'] < ERRORS_LEVEL_HARD) {
            restore_error_handler();
        }
        return $compiler->transformToXML($xmlHandler);
    }
}

function HandleXmlError($errno, $errstr, $errfile, $errline)
{
    if ($errno == E_WARNING && substr_count($errstr, 'DOMDocument::loadXML()') > 0) {
        header('Location: /notfound');
        exit;
    }
    return false;
}
?>
