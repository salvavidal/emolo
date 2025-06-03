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


function upgrade_module_2_0_1($object)
{
	
	try
	{
		// Add entity field
		Fs2psTools::dbExec('
			ALTER TABLE `@DB_fs2ps_match` 
			ADD COLUMN `entity` varchar(254) AFTER `table`
		');
			
		try {
    		// Add column 'entity' to primary index
    		Fs2psTools::dbExec('
    			ALTER TABLE `@DB_fs2ps_match` DROP PRIMARY KEY
    		');
		} catch (Exception $e) {
		    // Puede que no exista. Ignoramos el error.
		}
		
		Fs2psTools::dbExec('
			ALTER TABLE `@DB_fs2ps_match`
			ADD UNIQUE INDEX `primary_replacement` (`table`, `entity`, `dto_id`)
		');
			
		// Add column 'entity' to reverse_match index
		Fs2psTools::dbExec('ALTER TABLE `@DB_fs2ps_match` DROP index `reverse_match`');
		Fs2psTools::dbExec('
			ALTER TABLE `@DB_fs2ps_match`
			ADD INDEX `reverse_match` (`table`, `entity`, row_id)
		');
	}
	catch (Exception $e)
	{
		// '@DB_fs2ps_match' is not deleted on uninstall, so column 'entity'
		// and indexes can exist allready.
		// Do not return false but log info missage.
		Logger::addLog('fs2ps upgrade 2.0.1: '.Fs2psTools::dbEscape($e->getMessage()), 1);
	}
	
	return true;
}
