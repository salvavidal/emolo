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


function upgrade_module_1_1_0($object)
{
	try 
	{
		// This table won't be deleted, so use 'IF NOT EXISTS'
		// to avoid problems when reinstall (unistall and install again)
		// XXX: PRIMARY KEY (`table`, `dto_id`) no funciona con Mariadb??!!
		Fs2psTools::dbExec('
			CREATE TABLE IF NOT EXISTS `@DB_fs2ps_match` (
			`table` varchar(254) NOT NULL,
			`dto_id` varchar(254) NOT NULL,
			`row_id` integer NOT NULL,
			`uploaded` tinyint NOT NULL
			)
			ENGINE=@ENG DEFAULT CHARSET=utf8
		');
	} 
	catch (Exception $e)
	{
		return false;
	}
	
	return true;
}
