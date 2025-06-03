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

include_once(dirname(__FILE__).'/Fs2psTask.php');

class Fs2psTruncateCatalogTask extends Fs2psTask
{

	public function __construct($mng, $cmd)
	{
		parent::__construct('truncate_catalog', $mng, $cmd);
	}

	protected function _execute($cmd)
	{
		// Task only available for test mode
		if (!defined('_FS2PS_TEST_MODE_') || !_FS2PS_TEST_MODE_)
			throw new Fs2psException(
				'"truncate_catalog" disponible sólo en modo de pruebas');
		
		if (empty($cmd['notcatalog']))
		{
			// Truncate Prestashop catalog tables
			$pscleaner = Module::getInstanceByName('pscleaner');
			if (!$pscleaner)
				throw new Fs2psException(
					'No se pudo cargar el módulo "pscleaner"');
			$pscleaner->truncate('catalog');
			Fs2psTools::dbExec('ALTER TABLE `@DB_category` AUTO_INCREMENT = 3');
		}
		
		if (empty($cmd['notcatalog']) && empty($cmd['notgroups']))
		{
			// empty($cmd['notcatalog']) -> specific_price* tables must be deleted first by pscleaner
			$tables = array(
				'cart_rule_group', 'customer_group', 'category_group', 
				'group_reduction', 'product_group_reduction_cache', 'module_group', 
				'group_lang', 'group'
			);
			foreach ($tables as $table)
			{
				Fs2psTools::dbExec('
					DELETE spt
					FROM `@DB_'.$table.'` as spt
						inner join `@DB_fs2ps_match` m on `table`=\'group\' and m.row_id=spt.id_group
				');
			}
		}
		
		if (empty($cmd['notmathes']))
		{
			// Truncate fs2ps tables

			if (empty($cmd['notgroupmathes']))
				Fs2psTools::dbExec('TRUNCATE TABLE `@DB_fs2ps_match`');
			else
			{
				Fs2psTools::dbExec('
					DELETE FROM `@DB_fs2ps_match`
					WHERE `table`<>\'group\'
				');
			}
			
			$tables = array('fs2ps_task_log', 'fs2ps_task');
			foreach ($tables as $table)
			{
				Fs2psTools::dbExec('
					DELETE FROM `@DB_'.$table.'` 
					WHERE id_task<>'.$this->id_task
				);
			}
		}
		
	}

}
