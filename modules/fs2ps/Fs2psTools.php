<?php
/**
* LICENCIA
*
* Este programa se propociona "tal cual", sin garantía de ningún tipo más allá del soporte
* pactado a la hora de adquirir el programa.
*
* En ningún caso los autores o titulares del copyright serán responsables de ninguna
* reclamación, daños u otras responsabilidades, ya sea en un litigio, agravio o de otro
* modo, que surja de o en conexión con el programa o el uso u otro tipo de acciones
* realizadas con el programa.
*
* Este programa no puede modificarse ni distribuirse sin el consentimiento expreso del autor.
*
*    @author    Carlos Fillol Sendra <festeweb@festeweb.com>
*    @copyright 2014 Fes-te web! - www.festeweb.com
*    @license   http://www.festeweb.com/static/licenses/fs2ps_1.1.0.txt
*/

include_once(dirname(__FILE__).'/Fs2psException.php');

class Fs2psTools
{

	public static $round_mode = null;


	public static function array_column($input, $columnKey)
	{
		if (function_exists('array_column')) {
			return array_column($input, $columnKey);
		} else {
			return array_map(function($element) use($columnKey) {
				return $element[$columnKey];
			}, $input);
		}
	}

	public static function strtolower($str)
	{
		return strtolower($str);
	}
	
	public static function ucfirst($str)
	{
		return ucfirst($str);
	}
	
	public static function get($_array, $key, $_default = null)
	{
		return isset($_array[$key]) ? $_array[$key] : $_default;
	}

	public static function hashedBy($array, $prop_name)
	{
		$hashed_array = array();
		foreach ($array as $el)
			$hashed_array[$el[$prop_name]] = $el;
		return $hashed_array;
	}

	public static function jsonEncode($obj)
	{
		return json_encode($obj);
	}

	public static function jsonDecode($str)
	{
		return json_decode($str, true);
	}

	public static function base64DataToFile($base64_string, $output_file)
	{
		$ifp = fopen($output_file, 'wb');

		$data = explode(',', $base64_string);

		fwrite($ifp, base64_decode(end($data)));
		fclose($ifp);

		return $output_file;
	}

	public static function insertOrUpdate($table, $id_col, $values)
	{
		$db = Db::getInstance();
		$id = !empty($values[$id_col]) ? $values[$id_col] : null;

		$escaped = array();
		foreach ($values as $key => $value)
            $escaped[$key] = is_string($value) ? $db->escape($value) : ($value===NULL? array('type'=>'sql', 'value'=> 'NULL') : $value);

		if ($id)
		{
			if ($db->update($table, $escaped, $id_col.'='.$id, 0, false, false))
				return $id;
			else
				throw new Fs2psException("No se pudo actualizar registro $id_col=$id en $table: ".$db->getMsgError()." \n".Fs2psTools::jsonEncode($values));
		}
		else
		{
			if ($db->insert($table, $escaped, false, false))
				return $db->Insert_ID();
			else
				throw new Fs2psException("No se pudo insertar en $table: ".$db->getMsgError()." \n".Fs2psTools::jsonEncode($values));
		}
	}
	
	public static function multiLangField($value, $all_langs, $opts = null)
	{
		$max_length = $opts && !empty($opts['max_length'])? $opts['max_length'] : false;
		$strip_tags = $opts && !empty($opts['strip_tags']);
		$replace = $opts && !empty($opts['replace'])? $opts['replace'] : false;
		
		$ml_values = array();
		$id_default_lang = Configuration::get('PS_LANG_DEFAULT');
		
		if (is_array($value))
		{
			$default_lang = Language::getLanguage($id_default_lang);
			$default_value = self::get($value, $default_lang['iso_code'], '');
			if ($strip_tags) $default_value = strip_tags($default_value);
			if ($max_length>0) $default_value = Tools::substr($default_value, 0, $max_length);
			if ($replace) $default_value = preg_replace($replace[0],$replace[1], $default_value);
			
			$languages = Language::getLanguages(true, false);
			foreach ($languages as $lang)
			{
				if (!empty($value[$lang['iso_code']]))
				{
					$val = $value[$lang['iso_code']];
					if ($strip_tags) $val = strip_tags($val);
					if ($max_length>0) $val = Tools::substr($val, 0, $max_length);
					if ($replace) $val = preg_replace($replace[0],$replace[1], $val);
					$ml_values[$lang['id_lang']] = $val;
				}
				elseif ($all_langs==true) 
					$ml_values[$lang['id_lang']] = $default_value;
			}
		}
		else 
		{
			if ($strip_tags) $value = strip_tags($value);
			if ($max_length>0) $value = Tools::substr($value, 0, $max_length);
			if ($replace) $value = preg_replace($replace[0],$replace[1], $value);
			
			if ($all_langs==true) 
			{
				$languages = Language::getLanguages(true, false);
				foreach ($languages as $lang)
				{
					$ml_values[$lang['id_lang']] = $value;
				}
			} 
			else 
				$ml_values[$id_default_lang] = $value;
		}
		
		return $ml_values;
	}

	public static function linkRewrite($text)
	{
		if (is_array($text))
		{
			$ml_links = array();
			foreach ($text as $key => $ltext)
				$ml_links[$key] = self::linkRewrite($ltext);
			return $ml_links;
		} 
		else 
		{
			// PS 1.5 uses '_' by default. We force to use '-'.
			$text = preg_replace("/\r\n|\r|\n/",'', $text);
			return str_replace('_', '-', Tools::link_rewrite(Tools::substr($text, 0, 128)));
		}
	}

	public static function multiLangLinkRewrite($text, $all_langs)
	{
		return Fs2psTools::multiLangField(Fs2psTools::linkRewrite($text), $all_langs);
	}

	public static function arrayToObject($array)
	{
		foreach ($array as $key => $value)
		{
			if (is_array($value))
				$array[$key] = self::arrayToObject($value);
		}
		return (object)$array;
	}

	public static function isAssoc($arr)
	{
		return is_array($arr) && array_keys($arr)!==range(0, count($arr) - 1);
	}
	
	public static function replaceObjOrArray(&$target, $values)
	{
		if (is_object($values))
			$values = get_object_vars($values);

		if (is_object($target))
		{
			foreach ($values as $key => $value)
				$target->$key = $value;
		}
		else
		{
			foreach ($values as $key => $value)
				$target[$key] = $value;
		}
	}

	public static function tableToObjectModelCls($table)
	{
		$parts = explode('_', $table);
		$capitalized_parts = array();
		foreach ($parts as $part)
			$capitalized_parts[] = Tools::ucfirst(Tools::strtolower($part));
		return join('', $capitalized_parts);
	}

	public static function now()
	{
		return date('Y-m-d H:i:s');
	}
	
	protected static function sqlReplace($sql) 
	{
		$sql = str_replace('@DB_', _DB_PREFIX_, $sql);
		$sql = str_replace('@ENG', _MYSQL_ENGINE_, $sql);
		return $sql;
	}
	
	public static function dbEscape($str)
	{
		return Db::getInstance()->escape($str);
	}
	
	/**
	 * Comprueba si la cadena dada cumple el formato de select de un campo
	 * (?P<select>[a-zA-Z\.]+)
	 * 
	 * Ej: tablax.campox ó campox
	 *  
	 * @param $str string Cadena cuyo formato se va a comprobar
	 * @throws Fs2psServerFatalException si la cadena no cumple el formato
	 * @return $str si cumple el formato
	 */
	public static function dbFieldName($str)
	{
	    $matches = null;   
	    preg_match('/^[a-zA-Z\.\)\(= _]+$/', $str, $matches);
	    if (!$matches) {
	        throw new Fs2psServerFatalException('Invalid DB field name: '.$str);
	    }
	    return $str;
	}
	
	public static function dbExecFile($file_path)
	{
		$commands = file_get_contents($file_path);
		$statements = preg_split('/; *(\r\n|\n)+/', $commands);
		foreach ($statements as $statement)
		{
			$statement = trim($statement);
			if (!empty($statement)) 
				self::dbExec($statement);
		}
	}
	
	public static function dbExec($sql)
	{
		$sql = self::sqlReplace($sql);
		$db = Db::getInstance();
		
		try {
		    $result = $db->query($sql, true, false);
		} catch (Exception $e) {
		    throw new Fs2psDbException($sql, $e);
		}
		
		if ($result===false) {
			throw new Fs2psDbException($sql);
		}
		
		return $result;
	}
	
	public static function dbSelect($sql)
	{
		$sql = self::sqlReplace($sql);
		$db = Db::getInstance();
		
		try {
		    $result = $db->executeS($sql, true, false);
		} catch (Exception $e) {
		    throw new Fs2psDbException($sql, $e);
		}
		
		if ($result===false)
		    throw new Fs2psDbException($sql);
		
		return $result;
	}
	
	public static function dbRow($sql)
	{
		$sql = self::sqlReplace($sql);
		$db = Db::getInstance();
		
		try {
		  $result = $db->query($sql);
		} catch (Exception $e) {
		    throw new Fs2psDbException($sql, $e);
		}
		
		if ($result===false)
		    throw new Fs2psDbException($sql);
		
		$result = $db->nextRow($result);
			
		return $result;
	}
	
	public static function dbValue($sql)
	{
		$row = self::dbRow($sql);
		if ($row===false) 
			return false;

		return array_shift($row);
	}
	
	public static function dbInsert($table, $data)
	{
		$db = Db::getInstance();
		
		$escaped = array();
		foreach ($data as $key => $value)
			$escaped[$key] = is_string($value) ? $db->escape($value) : $value;
		
		try {
		    $result = $db->insert($table, $escaped, false, false);
		} catch (Exception $e) {
		    throw new Fs2psDbException(null, $e);
		}
		
		if ($result) {
			return $db->Insert_ID();
		} else {
			throw new Fs2psDbException();
		}
	}
	
	protected static function dbInsertId()
	{
		$db =  Db::getInstance();
		return $db->Insert_ID();
	}
	
	public static function dbUpdate($table, $data, $where)
	{
		$db =  Db::getInstance();
		
		$escaped = array();
		foreach ($data as $key => $value)
			$escaped[$key] = is_string($value) ? $db->escape($value) : $value;
		
		$where_str_list = [];
		foreach ($where as $key => $value)
		    $where_str_list[] = $key.'='.(is_string($value) ? $db->escape($value) : $value);
		
	    try {
	        $result = $db->update($table, $escaped, join(' and ', $where_str_list), 0, false, false);
	    } catch (Exception $e) {
	        throw new Fs2psDbException(null, $e);
	    }
		    
		if ($result===false)
			throw new Fs2psDbException();
			
		return $result;
	}
	
	public static function dbInsertOrUpdate($table, $data, $where)
	{
	    $db =  Db::getInstance();
		$where_str_list = [];
		foreach ($where as $key => $value)
		    $where_str_list[] = $key.'='.(is_string($value) ? $db->escape($value) : $value);
		$where_str = join(' and ', $where_str_list);
		
	    $exist = self::dbValue('
			select count(1) from `@DB_'.$table.'`
			where '.$where_str.'
		');
	    $exist? self::dbUpdate($table, $data, $where): self::dbInsert($table, array_merge($data, $where));
	}
	
	public static function dbExists($table, $id_field, $id_value)
	{
		return self::dbValue('
			select count(1) from `@DB_'.$table.'` 
			where '.$id_field.'=\''.$id_value.'\'
		');
	}
	
	public static $DB_TDATE_FORMAT = 'Y-m-d';
	public static function date2tdb($date)
	{
	    return $date->format(self::$DB_TDATE_FORMAT);
	}
	public static function tdb2date($db_date)
	{
	    return DateTime::createFromFormat(self::$DB_TDATE_FORMAT , $db_date);
	}
	
	public static $DB_DATE_FORMAT = 'Y-m-d H:i:s';
	public static function date2db($date)
	{
		return $date->format(self::$DB_DATE_FORMAT);
	}
	public static function db2date($db_date)
	{
		return DateTime::createFromFormat(self::$DB_DATE_FORMAT , $db_date);
	}
	
	public static $DTO_DATE_FORMAT = 'Y-m-d H:i:s';
	public static function date2dto($date)
	{
		return $date->format(self::$DTO_DATE_FORMAT);
	}
	public static function dto2date($dto_date)
	{
		return DateTime::createFromFormat(self::$DTO_DATE_FORMAT , $dto_date);
	}
	
	public static function randomPassword() {
	    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	    $pass = array(); //remember to declare $pass as an array
	    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
	    for ($i = 0; $i < 8; $i++) {
	        $n = rand(0, $alphaLength);
	        $pass[] = $alphabet[$n];
	    }
	    return implode($pass); //turn the array into a string
	}
	
	public static function mkdirs($base_folder, $subpath, $mode=0770, $add_index_file=true) {
	    if (!file_exists($base_folder)) throw new Fs2psServerFatalException("No exists base folder: ".$base_folder);
	    
	    $base_folder = rtrim($base_folder, " \t\n\r\0\x0B/");
	    $subpath = trim($subpath, " \t\n\r\0\x0B/");
	    
	    $folder = $base_folder;
	    $subdirs = explode('/', $subpath);
	    foreach ($subdirs as $subdir) {
	        $folder = $folder.'/'.$subdir;
	        if (!file_exists($folder)) {
	            mkdir($folder, $mode);
	            file_put_contents($folder.'/index.html', '');
	        }
	    }
	    
	}
	
	
	////////////////////////////////////
	// PS 1.6.1 backward compatibility
	////////////////////////////////////
	
	public static function spreadAmount($amount, $precision, &$rows, $column)
	{
		if (!is_array($rows) || empty($rows)) {
			return;
		}
		
		$sort_function = function($a, $b){
			return $b['$column'] > $a['$column'] ? 1 : -1;
		};
		//$sort_function = create_function('$a, $b', "return \$b['$column'] > \$a['$column'] ? 1 : -1;");
	
		uasort($rows, $sort_function);
	
		$unit = pow(10, $precision);
	
		$int_amount = (int)round($unit * $amount);
	
		$remainder = $int_amount % count($rows);
		$amount_to_spread = ($int_amount - $remainder) / count($rows) / $unit;
	
		$sign = ($amount >= 0 ? 1 : -1);
		$position = 0;
		foreach ($rows as &$row) {
			$adjustment_factor = $amount_to_spread;
	
			if ($position < abs($remainder)) {
				$adjustment_factor += $sign * 1 / $unit;
			}
	
			$row[$column] += $adjustment_factor;
	
			++$position;
		}
		unset($row);
	}
	
	public static function ps_round($value, $precision = 0, $round_mode = null)
	{
		if (version_compare(_PS_VERSION_, '1.6.0.11') < 0)
			return Tools::ps_round($value, $precision);
			
		if ($round_mode === null) {
			if (self::$round_mode == null) {
				self::$round_mode = (int)Configuration::get('PS_PRICE_ROUND_MODE');
			}
			$round_mode = self::$round_mode;
		}
	
		switch ($round_mode) {
			case PS_ROUND_UP:
				return Tools::ceilf($value, $precision);
			case PS_ROUND_DOWN:
				return Tools::floorf($value, $precision);
			case PS_ROUND_HALF_DOWN:
			case PS_ROUND_HALF_EVEN:
			case PS_ROUND_HALF_ODD:
				return Tools::math_round($value, $precision, $round_mode);
			case PS_ROUND_HALF_UP:
			default:
				return Tools::math_round($value, $precision, PS_ROUND_HALF_UP);
		}
	}
	
	/**
	 * Utils for translating email templates (also module email templates)
	 */
	public static function l($string, $id_lang = null, Context $context = null)
	{
	    global $_LANGMAIL;
	    if (! $context)
	        $context = Context::getContext();
	        
	        $key = str_replace('\'', '\\\'', $string);
	        if ($id_lang == null) {
	            $id_lang = (! isset($context->language) || ! is_object($context->language)) ? (int) Configuration::get('PS_LANG_DEFAULT') : (int) $context->language->id;
	        }
	        
	        $iso_code = Language::getIsoById((int) $id_lang);
	        
	        $file_core = _PS_ROOT_DIR_ . '/mails/' . $iso_code . '/lang.php';
	        if (Tools::file_exists_cache($file_core) && empty($_LANGMAIL)) {
	            include_once ($file_core);
	        }
	        
	        $file_theme = _PS_THEME_DIR_ . 'mails/' . $iso_code . '/lang.php';
	        if (Tools::file_exists_cache($file_theme)) {
	            include_once ($file_theme);
	        }
	        
	        $file_module =  _PS_MODULE_DIR_.'fs2psinvoices/mails/' . $iso_code . '/lang.php';
	        if (Tools::file_exists_cache($file_module)) {
	            include_once ($file_module);
	        }
	        
	        if (! is_array($_LANGMAIL))
	            return (str_replace('"', '&quot;', $string));
	            if (array_key_exists($key, $_LANGMAIL) && ! empty($_LANGMAIL[$key])) {
	                $str = $_LANGMAIL[$key];
	            } else {
	                $str = $string;
	            }
	            return str_replace('"', '&quot;', stripslashes($str));
	}
	
	public static function extractEmails($string){
	    $matches = array();
	    $pattern = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i';
	    preg_match_all($pattern, $string, $matches);
	    return $matches? $matches[0] : array();
	}
	
	public static function replaceTags($template, $placeholders){
	    $placeholders = array_merge($placeholders, array('<?'=>'', '?>'=>''));
	    return str_replace(array_keys($placeholders), $placeholders, $template);
	}
	
	public static function htmlToPlainText($html) {
	    $patrones = array ('/(<br>|\n\r|\n)/', '/<[^>]+>/');
	    $sustitución = array (' ', '');
	    return preg_replace($patrones, $sustitución, $html);
	}
	
	public static function shorten($id, $alphabet='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
	{
	    $base = strlen($alphabet);
	    $short = '';
	    while($id) {
	        $id = ($id-($r=$id%$base))/$base;
	        $short = $alphabet[$r] . $short;
	    };
	    return $short;
	}
	
	public static function shortenUpper($id, $alphabet='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ')
	{
	    return self::shorten($id, $alphabet);
	}
		
	public static function isSlug($str)
	{
	    $matches = null;
	    preg_match('/^[a-z0-9\-\_]+$/', $str, $matches);
	    return $matches? true : false;
	}
	
	
	public static function num2alpha($n) {
	    $r = '';
	    do {
	        $pos = $n % 36;
	        $r = chr(($pos<10? 48 : 65 - 10) + $pos) . $r;
	        $n = intval($n/36);
	    } while ($n > 0);
	    return $r;
	}
	
	public static function alpha2num($a) {
	    $r = 0;
	    $l = strlen($a);
	    $pow = 1;
	    for ($i = 0; $i < $l; $i++) {
	        $pos = ord($a[$l - $i - 1]);
	        $r += $pow * ($pos - ($pos>=65? 65 - 10: 48));
	        $pow *= 36;
	    }
	    return $r;
	}
	
}
