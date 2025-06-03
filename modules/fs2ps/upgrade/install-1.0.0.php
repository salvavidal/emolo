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

if (!defined('_PS_VERSION_'))
	exit;

include_once(dirname(__FILE__).'/../Fs2psTools.php');

function upgrade_module_1_0_0($object)
{
	if (!$object->registerHook('displayBackOfficeHeader'))
		return false;
	
	try 
	{
		Fs2psTools::dbExec('
			CREATE TABLE `@DB_fs2ps_task` (
			`id_task` int(10) unsigned auto_increment,
			`op` varchar(50),
			`started` datetime,
			`finished` datetime default null,
			`status_msg` varchar(100),
			`status` varchar(50),
			`progress` float unsigned,
			`extra_info` mediumtext,
			PRIMARY KEY (`id_task`))
			ENGINE=@ENG DEFAULT CHARSET=utf8
		');
		
		Fs2psTools::dbExec('
			CREATE TABLE `@DB_fs2ps_task_log` (
			`id_log` int(10) unsigned auto_increment,
			`id_task` int(10) unsigned,
			`order` int(10) unsigned,
			`msg` text,
			`created` datetime,
			`read` tinyint(1) unsigned NOT NULL default \'0\',
			PRIMARY KEY (`id_log`))
			ENGINE=@ENG DEFAULT CHARSET=utf8
		');
	} 
	catch (Exception $e)
	{
		return false;
	}
	
	return true;
}
