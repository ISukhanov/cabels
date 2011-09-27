<?php
/**
 * Класс для регистрации и хранения входных переменных в локальном контексте.
 *
 */
class Request {
    private $data = array ();

    const KEY_INNAME  = 0;
    const KEY_TYPE    = 1;
    const KEY_DEFAULT = 2;
    const KEY_METHOD  = 3;

    const METHOD_GET     = 1;
    const METHOD_POST    = 2;
    const METHOD_COOKIE  = 3;
    const METHOD_REQUEST = 4; // REQUEST = GET + POST + COOKIE
    const METHOD_SESSION = 5;
    const METHOD_FILE    = 6;

    const TYPE_INT    = 1;
    const TYPE_STRING = 2;
    const TYPE_ARRAY  = 3;
    const TYPE_FLOAT  = 4;
    const TYPE_FILE   = 5;

    /**
     * Регистрация входных параметров.
     *
     * Если входные параметры берутся из одного источника, его можно указать в качестве второго параметра.
     * Иначе надо указывать в каждом параметре отдельно.
     *
     * Массив $input должен быть устроен таким образом. Его ключи - это имена будущих полей нового объекта.
     * Ключам соответствуют массивы с параметрами в таком порядке:
     * - имя переменной во входном массиве;
     * - тип переменной, следует использовать константы TYPE_*;
     * - значение по умолчанию.
     * - источник переменной (POST, GET, etc), следует использовать константы METHOD_*;
     *
     * Параметры (кроме имени переменной) можно опускать, но тогда для остальных параметров рекомендуется
     * явное указание их ключей из списка констант KEY_*. Источником по умолчанию является $_REQUEST.
     * Типом по умолчанию является строка. Если не указано значение по умолчанию, то в случае отсутствия
     * переменной в источнике, она не будет зарегистрирована вовсе.
     *
     * @param array $input
     */
    public function __construct($input, $default_method = false) {
        // Производится несколько проверок на адекватность запроса, но пока никаких ошибок или исключений
        // не порождается. Возможно, это будет исправлено позже.
        foreach ($input as $outname => $i) {
            $inname = array_key_exists (self::KEY_INNAME, $i) ? $i[self::KEY_INNAME] : $outname;
            if (array_key_exists ($outname, $this->data)) continue;
            $method = array_key_exists (self::KEY_METHOD, $i) ? $i[self::KEY_METHOD] : ($default_method ? $default_method : self::METHOD_REQUEST);

            $fou = false;
            switch ($method) {
                case self::METHOD_GET:     if (array_key_exists ($inname, $_GET))     { $value = $_GET[$inname];     $fou = true; } break;
                case self::METHOD_POST:    if (array_key_exists ($inname, $_POST))    { $value = $_POST[$inname];    $fou = true; } break;
                case self::METHOD_COOKIE:  if (array_key_exists ($inname, $_COOKIE))  { $value = $_COOKIE[$inname];  $fou = true; } break;
                case self::METHOD_REQUEST: if (array_key_exists ($inname, $_REQUEST)) { $value = $_REQUEST[$inname]; $fou = true; } break;
                case self::METHOD_SESSION:
                    if (array_key_exists ($inname, $_SESSION)) { $value = $_SESSION[$inname]; $fou = true; }
                break;
                case self::METHOD_FILE:    if (array_key_exists ($inname, $_FILES))   { $value = $_FILES[$inname];   $fou = true; } break;
            }
            if (! $fou && array_key_exists (self::KEY_DEFAULT, $i)) {
                $value = $i[self::KEY_DEFAULT];
                $fou = true;
            }
            if (! $fou) continue;
            $type = array_key_exists (self::KEY_TYPE, $i) ? $i[self::KEY_TYPE] : self::TYPE_STRING;
            $this->data[$outname] = self::setType($value, $type);
            unset ($value);
        }
        // Здесь можно проверить, а не получился ли случаем пустой массив, да только кого это волнует.
    }

    public function __get ($name) {
        if (array_key_exists ($name, $this->data))
            return $this->data[$name];
        return null;
    }

    static private function setType ($value, $type) {
        switch ($type) {
            case self::TYPE_INT:    return intval($value);
            case self::TYPE_STRING: return strval($value);
            case self::TYPE_FLOAT:
                $x = preg_replace(array ('/\s/', '/,/'), array ('', '.'), $value);
                return floatval($x);
            case self::TYPE_ARRAY:  if (! is_array($value)) return array ($value); return $value;
            case self::TYPE_FILE:
                // TODO: написать универсальный хендлер аплоудов, который проверял бы тип ну и вы поняли.
                $error = '';
                if (! is_array ($value))
                    $error = '$value is not an array!';
                elseif (! array_key_exists ('error', $value))
                    $error = '$value doesn\'t contain error field!';
                else
                    $error = $value['error'];
                if (! empty ($error))
                    return array ('error' => $error, 'name' => 'Error', 'tmp_name' => 'Error', 'size' => 0);
                return $value;
        }
    }

}
?>
