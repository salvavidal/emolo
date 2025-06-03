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
include_once(dirname(__FILE__).'/Fs2psTools.php');


class Fs2psTaskManager
{
    public $task;

	public function purgeLogs($timeout, $storagedays)
	{
	    $now = Fs2psTools::now(); # El NOW() de mysql puede tener un desfase importante respecto al de PHP
		Fs2psTools::dbExec('
			UPDATE `@DB_fs2ps_task`
			SET status=\'error\'
			WHERE
				status=\'running\' and
				started < DATE_SUB(\''.$now.'\', INTERVAL '.$timeout.' SECOND)
		');

		Fs2psTools::dbExec('
			DELETE FROM `@DB_fs2ps_task_log`
			WHERE id_task in (
				SELECT id_task FROM `@DB_fs2ps_task`
				WHERE started < DATE_SUB(\''.$now.'\', INTERVAL '.$storagedays.' DAY)
			)
		');

		Fs2psTools::dbExec('
			DELETE FROM `@DB_fs2ps_task`
			WHERE started < DATE_SUB(\''.$now.'\', INTERVAL '.$storagedays.' DAY)
		');
	}

	public function attendCmd($cmd)
	{
		if (empty($cmd['op']))
			throw new Fs2psException('No se indicó "op"');
		if (empty($cmd['subop']))
			throw new Fs2psException('No se indicó "subop"');

		if (empty($cmd['key']) || $cmd['key'] != Configuration::get('FS2PS_KEY'))
			throw new Fs2psException('Clave de seguridad incorrecta');

		// Which type of task?
		$op = $cmd['op'];
		$task_cls = 'Fs2ps'.str_replace(' ', '', ucwords(str_replace('_', ' ', $op))).'Task';
		$task_cls_file = dirname(__FILE__).'/'.$task_cls.'.php';
		if (!file_exists($task_cls_file)) {
		    $task_cls_file = null;
		    $modules = scandir(_PS_MODULE_DIR_);
		    foreach($modules as $module) {
		        if(preg_match('/^fs2ps.+/', $module) && is_dir(_PS_MODULE_DIR_.$module)) {
		            $module_task_cls_file = _PS_MODULE_DIR_.$module.'/'.$task_cls.'.php';
		            if (file_exists($module_task_cls_file)) {
		                $task_cls_file = $module_task_cls_file;
		                break;
		            }
		        }
		    }
		    if (!$task_cls_file) throw new Fs2psException('op inválida: '.$op);
		}
		
		include_once($task_cls_file);
		$task = new $task_cls($this, $cmd);
		$this->task = $task;
		
		return $task->attendCmd();
	}

	public function canExecuteTask()
	{
		$nrunning = Fs2psTools::dbValue('
			SELECT count(1)
			FROM `@DB_fs2ps_task`
			WHERE `status` = \'running\'
		');

		return ($nrunning == 0);
	}

}
