<?php
final class Utils {
    static function isUtf8($string) {
        return preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
            |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*$%xs', $string);
    }

    static function toUtf8($string, $from = 'windows-1251') {
        return self::isUtf8($string) ? $string : iconv ($from, 'utf-8', $string);
    }

    static function clearQS($str, $navVar = 'np')
    {
        if (is_array ($navVar) && sizeof($navVar) > 0) {
            $replace = array ();
            $replaceString = '~(\?|&|)%s=\d+~i';
            foreach ($navVar as $k => $v) {
                $navVar[$k] = sprintf($replaceString, preg_quote($v, '~'));
                $replace[] = '';
            }
            $str = preg_replace($navVar, $replace, $str);
            return preg_replace('~&{2,}~i', '&', $str);
        }
        return preg_replace('~(\?|&|)' . preg_quote($navVar, '~') . '=\d+~i', '', $str);
    }
    /**
     * Sends email of necessary type
     *
     * @param string $email
     * @param string $subject
     * @param string $body
     * @param boolean $isHtml
     * @param string $receiver
     * @param string $cc
     *
     * @return boolean
     */
    static function sendemail($email, $subject, $body, $isHtml = true, $receiver = '', $cc = '')
    {
        global $cmsConfig;

        $send = array ();
        $send['headers'] = '';
        $t = array ();
        if (strpos($email, ',') !== false) {
            $m = explode(',', $email);
            foreach ($m as $k => $v) {
                $t[] = '"'. $v .'" <'.$v.'>';
            }
            $mailTo = implode(', ', $t);
        } else {
            $mailTo = '"'. ((strlen($receiver) > 0) ? $receiver : $email) .'" <'.$email.'>';
        }
        if (strlen($cc) > 0) {
            $mailTo .= ', "'. $cc .'" <'. $cc .'>';
        }
        
        if ($isHtml) $send['headers'] .= "MIME-Version: 1.0\r\n";
        $send['headers'] .=
            "Content-type: text/". (($isHtml) ? 'html' : 'plain') ."; charset=UTF-8\r\n".
            "Content-Transfer-Encoding: 8bit\r\n" .
            "From: =?UTF-8?B?" . base64_encode('"' . $cmsConfig['mailerSender'] . '"') . "?= <". $cmsConfig['mailerEmail'] .">\r\n".
            "Date: ". date("r") ."\r\n";

        $send['body'] = $body;
        $send['subject'] = '=?UTF-8?B?' . base64_encode($subject) . '?=';

//        foreach ($send as $k => $v) {
            //$send[$k] = iconv('UTF-8//IGNORE', 'KOI8-R//IGNORE', $v);
//        }

        $additional = '-f' . $cmsConfig['mailerEmail'];
        
        //$send['subject'] = "=?koi8-r?B?" . base64_encode($send['subject']) . "?=";

        if (mail($mailTo, $send['subject'], $send['body'], $send['headers'], $additional))
            return true;
        else
            return false;
    }

    /**
     * Handler for uploaded image
     *
     * @param Array - $_FILES['onefile'] фактически
     * @param string - имя файла (вчитываемся. Имя - оно без расширения)
     * @return string | bool
     */
    static function handleImageUpload($picture, $storePath, $newName)
    {
        global $cmsConfig;
        //TODO:: сообразить, куда его запихнуть более правильно, пока оно только тут используется
        //ну и более человекопонятные константы сотворить
        $imageTypes = Array(1 => 'gif', 2 => 'jpeg', 3 => 'png'); //gif, jpeg, png

        //вот когда мне расскажут, почему try-catch регулярно убивает скрипт, тогда на исключениях и построим
        //а пока - проверить проще и быстрее.

        $pic_name = '';


        if (is_array($picture))
            $pic_name = $picture['tmp_name'];
        else if (is_string($picture))
            $pic_name = dirname(__FILE__) . '/../_html/' . $picture;

        if (!file_exists($pic_name) || !is_readable($pic_name))
        {
            return 1;
        }

        //XXX:: определиться уже, есть у нас оно или нет, и убрать лишние телодвижения
        if (function_exists('exif_imagetype'))
        {
            $ptype = exif_imagetype($pic_name);
        }
        else
        {
            $tempo = getimagesize($pic_name);
            if (!isset($tempo[2]))
            {
                return 2;
            }
            $ptype = $tempo[2];
        }

        if (!isset($imageTypes[$ptype]))
        {
            return 3;
        }

        $overallName = $storePath . $newName . '.' . $imageTypes[$ptype];

        rename($pic_name, $overallName);

        return $newName . '.' . $imageTypes[$ptype];
    }

    /**
     * Method represents money amount in Russian words
     *
     * @param string - amount of money to be represented in words. Decimals and fractions must be delimited with dot (.).
     */
    static function convertMoneyToWords($amount)
    {
        global $cmsConfig;

        require_once $cmsConfig['libsPath'] ."/CurrencyToWords/Words.php";

        $words = new Numbers_Words();
        $rub = $words->toCurrency(strval(floor($amount)), 'ru', 'RUR');
        $kop = round($amount * 100) % 100;
        // копейки прописываются цифрами.
        return implode(' ', array ($rub, ($kop < 10 ? '0' : '') . $kop, self::toRussianForm($kop, 'копейка', 'копейки', 'копеек')));
    }


    /**
     * Method defines if the string (User-Agent) contains some information about search bots.
     *
     * @param string $user_agent
     * @return boolean
     */
    public static function possibleBot($user_agent)
    {
        $knowBots = Array('Gigabot', 'Googlebot', 'Yahoo! Slurp', 'StackRambler', 'WebAlta', 'Yandex', 'msnbot');
        $retVal = 0;
        foreach ($knowBots as $botSig)
        {
            if (strpos($user_agent, $botSig) !== false)
            {
                $retVal = 1;
            }
        }
        return $retVal;
    }

    /**
    * Extract hostname from referer string (attempt fix skipped protocol)
    * ! return value not DB-escaped !
    *
    * @param string $referer
    * @return string
    */
    public static function extractHost($referer)
    {
        $parsed = parse_url($referer);
        if (!isset($parsed['host']))
        {
            $host = explode('/', $referer);
            if (isset($host[0]))
            {
                $host = $host[0];
            }
            else
            {
                $host = '';
            }
        }
        else
        {
            $host = $parsed['host'];
        }

        return $host;
    }

    /*
     * Считает, сколько дней из интервала start + count попадает в месяц month;
     */
    public static function intervalInMonth($dayStart, $dayCount, $month)
    {

    }

    public static function setPerPage ($per_page, $values) {
        if (in_array($per_page, $values)) return $per_page;
        $r = array ();
        foreach ($values as $k => $v) {
            $r[$k] = abs ($per_page - $v);
        }
        asort($r, SORT_NUMERIC);
        list($k) = array_keys($r);
        return $values[$k];
    }

    public static function generatePassword()
    {
        static $ab = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lmu = strlen ($ab) - 1;
        while (true) {
            $pass = '';
            for ($i = 1; $i <= 8; $i++)
                $pass .= $ab[rand (0, $lmu)];
            if ((preg_match ('/[a-z]/', $pass) > 0) &&
                (preg_match ('/[A-Z]/', $pass) > 0) &&
                (preg_match ('/[0-9]/', $pass) > 0)
            )
                break;
        }
        return $pass;
    }

    public static function checkINN($str)
    {
        if ( (strlen($str) != 10) && (strlen($str) != 12) )
        {
            return false;
        }

        //чтоб не возиццо с подбором на отладке
        if ( (defined('DEBUG_MODE')) && (DEBUG_MODE === true) )
        {
            return true;
        }


        if (strlen($str) == 10)
        {
            $checker = array(2,4,10,3,5,9,4,6,8);
            $sum = 0;
            foreach ($checker as $key=>$value)
            {
                $sum += $value * intval($str[$key]);
            }

            $cv = ($sum - intval($sum / 11) * 11) % 10;

            return ($cv == intval($str[9]));
        }

        if (strlen($str) == 12)
        {
            $checker = array(7,2,4,10,3,5,9,4,6,8);
            $sum = 0;
            foreach ($checker as $key=>$value)
            {
                $sum += $value * intval($str[$key]);
            }

            $cv1 = ($sum - intval($sum / 11) * 11) % 10;

            $checker = array(3,7,2,4,10,3,5,9,4,6,8);
            $sum = 0;
            foreach ($checker as $key=>$value)
            {
                $sum += $value * intval($str[$key]);
            }

            $cv2 = ($sum - intval($sum / 11) * 11) % 10;

            return ( ($cv1 == intval($str[10])) && ($cv2 == intval($str[11])) );
        }
    }

    public static function toRussianForm($num, $form1, $form2, $form3)
    {
        if ($num > 10 && $num < 20) {
            return $form3;
        }
        $check = $num % 10;
        if ($check == 1)
        {
            return $form1;
        }
        if (($check >= 2) && ($check <= 4))
        {
            return $form2;
        }
        return $form3;
    }

    /*
     * Конвертирует дату из формата ДД.ММ.ГГГГ в формат ГГГГ-ММ-ДД
     */
    public static function dateRusToEng($date){
        if(is_string($date) && strlen($date)){
            $date = explode('.', $date);
            $date = $date[2].'-'.$date[1].'-'.$date[0];
        }
        return $date;
    }

}
?>
