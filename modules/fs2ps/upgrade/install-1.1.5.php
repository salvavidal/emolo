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


function upgrade_module_1_1_5($object)
{
	try 
	{
		// Create index to optimize reverse match search
		Fs2psTools::dbExec('
			ALTER TABLE `@DB_fs2ps_match` 
			ADD INDEX `reverse_match` (`table`, row_id)
		');
	}
	catch (Exception $e)
	{
		// '@DB_fs2ps_match' is not deleted on uninstall, so index can exist allready.
		// Do not return false but log info missage.
		Logger::addLog('fs2ps upgrade 1.1.5: '.Fs2psTools::dbEscape($e->getMessage()), 1);
	}
	
	return true;
}
