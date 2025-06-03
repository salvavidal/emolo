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
include_once(dirname(__FILE__).'/Fs2psDtoProcessor.php');

class Fs2psUpdater extends Fs2psDtoProcessor
{
	protected $id_default_lang;
	
	protected function reloadCfg() {
		$cfg = $this->task->cfg;
		$updpart = strtoupper($this->name);
		$this->enable = $cfg->get('ENABLE_'.$updpart, false);
		$this->disable = $cfg->get('DISABLE_'.$updpart, false);
		$this->noover = $cfg->get('NOOVER_'.$updpart, false);
		$this->noover_content = $cfg->get('NOOVER_'.$updpart.'_CONTENT', false);
		$this->noover_url = $this->noover_content || $cfg->get('NOOVER_'.$updpart.'_URL', false);
		
		$this->id_default_lang = Configuration::get('PS_LANG_DEFAULT');
	}
	
	protected function keepIfHasTo($new_values, $old_values)
	{
		if (!empty($old_values) && Fs2PsTools::isAssoc($new_values)) {
			// Avoid override old values with nulls
			foreach ($new_values as $key => $value)
				$old_values[$key] = $new_values[$key];
			return $old_values;
		}
		return $new_values;
	}
	
	protected function keepNotManagedIds($new_ids, $old_ids) {
		# Take in mind $new_ids is passed by copy, not by reference
		# so original array is not modified.
		if (!empty($old_ids))
		{
			$matcher = $this->matcher;
			foreach ($old_ids as $old_id)
			{
				if (
					!in_array($old_id, $new_ids) &&
					$matcher->dtoIdStrFromRowId($old_id)==null
				)
					$new_ids[] = $old_id;
			}
		}
		return $new_ids;
	}
	
	protected function overrideValues($target, $values)
	{
		if (is_object($values))
			$values = get_object_vars($values);
	
		$row_id_field = $this->matcher->row_id_field;
		if (is_object($target))
		{
			foreach ($values as $key => $value) 
			{
				if (substr($key, 0, 1 )!="_" && $key!=$row_id_field)
				{
					// (substr($key, 0, 1 )==="_" && $key!=$this->row_id_field)
					// El id se llamará id en el ObjectModel, no id_{table}. Con ello evitamos 
					// machacar id porque será un autoincremental. 
					// Evitamos tambien sobreescribir campos auxiliares que empiezan por _
					$target->$key = $this->keepIfHasTo($value, $target->$key);
				}
			}
		}
		else
		{
			foreach ($values as $key => $value)
				if (substr($key, 0, 1 )!="_" && $key!=$row_id_field)
					$target[$key] = $this->keepIfHasTo($value, $target[$key]);
		}
	}
	
	
	
	public function refsToIds($refs) {
		$new_ids = array();
		if (!empty($refs))
		{
			foreach ($refs as $ref)
			{
				$id = $this->matcher->rowIdFromDtoId($ref);
				if ($id) $new_ids[] = $id;
			}
		}
		return $new_ids;
	}
	
	protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
	{
	    $obj = $oldObj!=null? $oldObj : new $this->object_model_cls($oldRowId);
	    if (($this->matcher->direct_match || $this->matcher instanceof Fs2psDto2RowDirectMatcher) && !empty($oldRowId)) {
		    $obj->id = $oldRowId;
		    $obj->force_id = true;
		}
		$this->overrideValues($obj, $row);
		$exists ? $obj->update() : $obj->add();
		if (empty($obj->id)) {
			throw new Fs2psException('Objeto sin ID después de '.($exists?'actualizar':'insertar'));
		}
		return $obj->id;
	}
	
	protected function onInsertedOrUpdated($dto, $row, $inserted)
	{
		$this->matcher->updateMatch($this->matcher->dtoId($dto), $this->matcher->rowId($row));
	}
	
	public function process($dtos)
	{
		$this->resetCounters($dtos);
		$this->reloadCfg();
		
		$this->onProcessStart($dtos);
		
		if (empty($dtos)) {
			$this->onProcessEnd($dtos);
			return;
		}
		
		$idx = 0;
		$group_rows = array();
		$matcher = $this->matcher;
		foreach ($dtos as $dto)
		{
			// Maybe this dto was imported before and/or we can deduce prestashop id from dto ...
		    $oldRowId = $matcher->rowIdFromDto($dto);
		    $exists = $matcher->existsRowInDatabase($oldRowId);
			
		    $row = $exists && $this->noover? null : $this->dto2row($dto, $idx, $exists, $oldRowId);
			if ($row===null)
			{
				// There was an error but must continue (stoponerr=false)
				$idx++;
				$this->nprocessed = $idx;
				continue;
			}

			// Set existing row id if any
			$row[$matcher->row_id_field] = empty($oldRowId)? null : $oldRowId;
			$inserted = !$exists;

			if (!empty($row)) {
				// We can skip this step if old row exists and nothing changed
				try
				{
					$id = $this->insertOrUpdate($row, $exists, $oldRowId);
				} 
				catch (Exception $e)
				{
				    $task = $this->task;
				    if (!($e instanceof Fs2psContinueException)) {
				        $msg = 'No se pudo guardar el objeto: '.$matcher->table.' ('.$matcher->dtoIdToStrFromDto($dto).')';
    				    if ($e instanceof PrestashopException || $e instanceof Fs2psException) {
    				        if (strlen($e->getMessage())>0) $msg = $msg.' - '.$e->getMessage();
    						if ($task->stop_on_error) throw new Fs2psException($msg, $e);
    				    } else {
    				        throw new Fs2psServerFatalException($msg, $e);
    				    }
    				    $task->log('ERROR: '.$msg);
				    }
					
					$idx++;
					$this->nprocessed = $idx;
					continue;
				}
				$row[$matcher->row_id_field] = $id; // New asigned id?
			}

			$idx++;
			$this->nprocessed = $idx;
			$inserted ? $this->ncreated++ : $this->nupdated++;

			$this->onInsertedOrUpdated($dto, $row, $inserted);

			if (!empty($group_rows) && !$this->sameGroup($group_rows[0], $row))
			{
				$this->onGroupUpdated($group_rows);
				$group_rows = array();
			}

			$group_rows[] = $row;
		}

		if (!empty($group_rows))
			$this->onGroupUpdated($group_rows);

		$this->onProcessEnd($dtos);
		
		$this->logProcess();
	}
	
	public function logProcess()
	{
	    $this->task->log($this->name.': '.$this->ncreated.' creados, '.$this->nupdated.($this->noover? ' actualizados (noover), ' : ' actualizados, ').$this->ndeleted.' eliminados');
	}
    
	protected function onProcessStart($dtos) {}
	protected function onProcessEnd($dtos) {}

}
