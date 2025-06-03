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
include_once(dirname(__FILE__).'/Fs2psCfg.php');

class Fs2psTask
{

	protected $mng;
	protected $valid_subops = array('can_execute', 'execute', 'status', 'msgs', 'extra_info', 'cancel');
	protected $save_fields = array('op', 'id_task', 'started', 'finished', 'status', 'status_msg', 'progress');

	protected $op;
	protected $id_task;
	protected $started;
	protected $finished;
	protected $status; /* none or new, running, error, ok */
	protected $status_msg;
	protected $progress;
	protected $extra_info = array();

	protected $log_order = 0;
	protected $log_enabled = true;

	public $stop_on_error = true;
	public $cfg = null;
	
	protected $no_override = array();
	
	public $cmd;

	public function __construct($op, $mng, $cmd)
	{
		$this->op = $op;
		$this->mng = $mng;
		$this->cmd = $cmd;

		$this->cfg = new Fs2psCfg();
		if (!empty($cmd['cfg']))
			$this->cfg->loadCfg($cmd['cfg']);
	}
	
	public function getOp() { return $this->op; }
	
	public function attendCmd()
	{
		$cmd = $this->cmd;
		$cfg = $this->cfg;
		
		if (empty($cmd['subop']))
			throw new Fs2psException('No se indicó "subop"');

		// What to do with the task?
		// Can execute new task?
		// Cannot run several tasks (subop=='execute') but can query info (subop=='status')
		$subop = $cmd['subop'];

		if (!in_array($subop, $this->valid_subops))
			throw new Fs2psException('"subop" inválida: '.$subop);

		// Check as error running tasks after 10 mins. and remove info after 7 days
		$this->mng->purgeLogs($cfg->get('IGNORE_RUNNING_TASK_EXCEPTION')? 10 : 600, 1);

		if (isset($cmd['stoponerr']))
			$this->stop_on_error = $cmd['stoponerr'];
		
		if ($subop == 'execute')
		{
			// Allow custom timeout
			if (isset($cmd['timeout']))
				set_time_limit((int)$cmd['timeout']);

			if (!$cfg->get('IGNORE_RUNNING_TASK_EXCEPTION') && !$this->mng->canExecuteTask($this)) {
				throw new Fs2psRunningTaskException();
			}
			
			// Capacidad multitienda. Definimos el contexto de actuación.
			$context = Context::getContext();
			if (intval($cfg->get('CONTEXT_SHOP'))) {
				$context->shop->setContext(Shop::CONTEXT_SHOP, intval($cfg->get('CONTEXT_SHOP')));
			} else if (intval($cfg->get('CONTEXT_GROUP'))) {
				$context->shop->setContext(Shop::CONTEXT_GROUP, intval($cfg->get('CONTEXT_GROUP')));
			} else if ($cfg->get('CONTEXT_ALL')) {
			    $context->shop->setContext(Shop::CONTEXT_ALL);
			}
			
			// ¿Se fuerza un employee desde la configuración?
			if (intval($cfg->get('PRESTASHOP_EMPLOYEE'))) {
			    $context->employee = new Employee(intval($cfg->get('PRESTASHOP_EMPLOYEE')));
			}
			
		}
		else
		{
			// subop de consulta sin riesgo
			$id_task = !empty($cmd['task']) ? (int)$cmd['task'] : null;
			$status = !empty($cmd['status']) ? $cmd['status'] : null;
			$id_task ? $this->loadByTaskId($id_task) : $this->loadLastByOpAndStatus(null, $status);

			if (!$this->id_task)
			{
				// Aún no se ejecutó ninguna op de este tipo
				return array('op' => $this->op, 'status' => 'none');
			}
		}

		return $this->$subop($cmd);
	}

	protected function can_execute($cmd)
	{
		return $this->mng->canExecuteTask($this);
	}

	protected function preExecute() {
	    // Inicializaciones propias de la ejecución
	    // Anyadir extractores, importadores, ...
	}
	
	protected function execute($cmd)
	{
		$this->status = 'running';
		$this->started = Fs2psTools::now();
		$this->progress = 0;
		$this->save();

		try
		{
			
		    $this->preExecute();
			$returns = $this->_execute($cmd);

		} catch (Exception $e)
		{
			$this->status = 'error';
			$this->status_msg = $e->getMessage();
			$this->finished = Fs2psTools::now();
			$this->save();
			throw $e;
		}

		$this->status = 'ok';
		$this->finished = Fs2psTools::now();
		$this->progress = 100;
		$this->status_msg = 'Tarea ejecutada con éxito';
		$this->save();

		$response = array(
			'op' => $this->op, 
			'task' => $this->id_task, 
			'status' => $this->status, 
			'started' => $this->started, 
			'finished' => $this->finished, 
			'status_msg' => $this->status_msg,
			'progress' => $this->progress
		);
		
		if (empty($cmd['async']))
			$response['msgs'] = $this->readPendingMsgs();
		
		if ($returns)
			$response['returns'] = $returns;
		
		return $response;
	}

	protected function _execute($cmd)
	{
		// set $this->status_msg when completed
		// set $this->progress
	    throw new Fs2psNotImplemented();
	}

	protected function status($cmd)
	{
		return array(
			'op' => $this->op, 
			'task' => $this->id_task, 
			'status' => $this->status, 
			'started' => $this->started, 
			'finished' => $this->finished, 
			'status_msg' => $this->status_msg,
			'progress' => $this->progress,
		);
	}

	protected function msgs($cmd)
	{
		return array(
			'op' => $this->op, 
			'task' => $this->id_task, 
			'status' => $this->status, 
			'started' => $this->started, 
			'finished' => $this->finished, 
			'status_msg' => $this->status_msg,
			'progress' => $this->progress, 
			'msgs' => $this->readPendingMsgs()
		);
	}

	protected function extra_info($cmd)
	{
		return array(
			'op' => $this->op, 
			'task' => $this->id_task, 
			'status' => $this->status, 
			'started' => $this->started, 
			'finished' => $this->finished, 
			'status_msg' => $this->status_msg,
			'progress' => $this->progress, 
			'extra_info' => $this->extra_info
		);
	}

	protected function cancel($cmd)
	{
		if ($this->status=='running') {
			$this->status = 'canceled';
			$this->finished = Fs2psTools::now();
			$this->save();
		}
		
		return array(
			'op' => $this->op, 
			'task' => $this->id_task, 
			'status' => $this->status, 
			'started' => $this->started, 
			'finished' => $this->finished,
			'status_msg' => $this->status_msg,
			'progress' => $this->progress,
		);
	}
	
	public function log($msg)
	{
		if ($this->log_enabled)
		{
			$values = array(
				'id_task' => $this->id_task, 
				'msg' => $msg, 
				'created' => Fs2psTools::now(), 
				'order' => $this->log_order, 
				'read' => 0
			);
			Fs2psTools::insertOrUpdate('fs2ps_task_log', 'id_task_log', $values);
			$this->log_order++;
		}
	}

	protected function loadByTaskId($id_task)
	{
	    $row = Fs2psTools::dbRow('
			SELECT *
			FROM `@DB_fs2ps_task`
			WHERE `id_task` = '.$id_task);
	    
	    if ($row) $row['extra_info'] = !empty($row['extra_info']) ? Fs2psTools::jsonDecode($row['extra_info']) : null;
	    else $row = array();
	    
	    foreach ($row as $key => $value) $this->$key = $value;
	    $this->id_task = (int)$this->id_task;
	}

	protected function loadLastByOpAndStatus($op = null, $status = null)
	{
		$conds = array();
		if (!empty($op))
			$conds[] = '`op`=\''.$op.'\'';
		if (!empty($status))
			$conds[] = '`status`=\''.$status.'\'';

		$where = empty($conds) ? '' : 'WHERE '.join(' and ', $conds);
		$id_task = Fs2psTools::dbValue('
			select max(id_task) 
			from @DB_fs2ps_task 
			'.$where);

		if ($id_task)
			$this->loadByTaskId($id_task);
	}

	protected function save()
	{
		$values = array();
		foreach ($this->save_fields as $f) $values[$f] = $this->$f;

		$values['extra_info'] = !empty($this->extra_info) ? Fs2psTools::jsonEncode($this->extra_info) : null;
		$this->id_task = (int)Fs2psTools::insertOrUpdate('fs2ps_task', 'id_task', $values);
	}

	protected function readPendingMsgs()
	{
		$msgs = Fs2psTools::dbSelect('
			SELECT *
			FROM `@DB_fs2ps_task_log`
			WHERE 
				`id_task` = '.$this->id_task.' and 
				`read` = 0 
			ORDER BY `order`				
		');

		if (!$msgs)
			$msgs = array();

		if (!empty($msgs))
		{
			Fs2psTools::dbExec('
				UPDATE `@DB_fs2ps_task_log`
				SET `read` = 1
				WHERE
					`id_task` = '.$this->id_task.' and
					`read` = 0
			');
		}

		return $msgs;
	}

}

class Fs2psUpdaterTask extends Fs2psTask
{

	protected $updaters = array();
	protected $updaters_by_name = array();
	
	protected function addUpdater($upd_name, $updater_cls)
	{
		$updater = new $updater_cls($this, $upd_name);
		$this->updaters[] = $updater;
		$this->updaters_by_name[$upd_name] = $updater;
	}

	public function getUpdater($upd_name)
	{
		if (isset($this->updaters_by_name[$upd_name]))
			return $this->updaters_by_name[$upd_name];
		else
			return null;
	}

	protected function replaceUpdater($upd_name, $updater_cls)
	{
		$updater = new $updater_cls($this, $upd_name);
		$this->updaters[array_search($this->updaters_by_name[$upd_name], $this->updaters)] = $updater;
		$this->updaters_by_name[$upd_name] = $updater;
	}

	protected function _execute($cmd)
	{
		// Process updaters
		foreach ($this->updaters as $updater)
		{
			$upd_name = $updater->getName();
			$updater->process(Fs2psTools::get($cmd, $upd_name));
		}

	}

}


class Fs2psExtractorTask extends Fs2psTask
{
	protected $extractors = array();
	protected $extractors_by_name = array();

	protected function addExtractor($ext_name, $extractor_cls)
	{
		$extractor = new $extractor_cls($this, $ext_name);
		$this->extractors[] = $extractor;
		$this->extractors_by_name[$ext_name] = $extractor;
	}

	public function getExtractor($ext_name)
	{
		if (isset($this->extractors_by_name[$ext_name]))
			return $this->extractors_by_name[$ext_name];
		else
			return null;
	}

	protected function replaceExtractor($ext_name, $extractor_cls)
	{
		$extractor = new $extractor_cls($this, $ext_name);
		$this->extractors[array_search($this->extractors_by_name[$ext_name], $this->extractors)] = $extractor;
		$this->extractors_by_name[$ext_name] = $extractor;
	}

	protected function _execute($cmd)
	{
		$returns = array();
		
		$from = null;
		if (!empty($cmd['from']))
			$from = $cmd['from'];
		elseif ($this->extractors)
		{
			$from = array(
				'ext' => $this->extractors[0]->getName(),
				'offset' => 0
			);
		}
		$from_ext = $from? $this->extractors_by_name[$from['ext']] : null;
		if (!$from_ext)
			throw new Fs2psException('Invalid extractor in \'from\': '.$from['ext']);

		if (!empty($cmd['limit']))
			$limit = $cmd['limit'];
		else
			$limit = $this->cfg->get('MAX_DTOS_BY_DOWNLOAD', 50);
		
		$total = 0;
		foreach ($this->extractors as $extractor)
		{
			$total += $extractor->getNtotal();
		}
		$returns['total'] = $total;
		$nsend_by_request = 0;
		
		// Process extractors
		$ext_pos = 0;
		foreach ($this->extractors as $extractor)
		{
			$from_ext_pos = array_search($from_ext, $this->extractors);
			if ($ext_pos>=$from_ext_pos)
			{
				$limit = $limit - $nsend_by_request;
				if ($limit<=0) break;
				
				$ext_name = $extractor->getName();
				if ($ext_name!=$from['ext'])
				{
					$from['ext'] = $ext_name;
					$from['offset'] = 0;
				}
					
				$dtos = $extractor->process($limit, $from['offset']);
				if ($dtos)
					$returns[$ext_name] = $dtos;
				
				$from['offset'] = $extractor->getNprocessed();
				$nsend_by_request += sizeof($dtos);
			}
			else
			{
				$extractor->flush();
			}
			$ext_pos++;
		}

		$returns['from'] = $from;
		return $returns;
	}

}

