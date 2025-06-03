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

class Fs2psDisabler
{

	protected $task;
	protected $nprocessed = 0;
	protected $ntotal = 0;
	protected $ndisabled = 0;

	protected $name;
	protected $table;

	public function __construct($task, $name, $table)
	{
		$this->task = $task;
		$this->name = $name;
		$this->table = $table;
		$this->row_id_field = 'id_'.$table;
		$this->object_model_cls = Fs2psTools::tableToObjectModelCls($table);
	}

	protected function loadDisabledByTable()
	{
		return Fs2psTools::dbSelect('
			SELECT dto_id, row_id from `@DB_fs2ps_match`
			WHERE `table`=\''.$this->table.'\' and m.entity=\''.$this->name.'\' and uploaded=0
		');
	}

	protected function disable($id)
	{
		$obj = new $this->object_model_cls($id);
		$exists = $id && ObjectModel::existsInDatabase($id, $this->table);
		if ($exists)
		{
			$obj->active = 0;
			$obj->update();
			return true;
		}
		return false;
	}

	public function process()
	{
		$cfg = $this->task->cfg;
		$disable = $cfg->get('DISABLE_'.strtoupper($this->name), false);
		if (!$disable)
		{
			$this->task->log($this->name.': 0 deshabilitados');
			return;
		}
		
		$rows = $this->loadDisabledByTable();
		$this->ntotal = count($rows);

		$idx = 0;
		foreach ($rows as $row)
		{

			try
			{

				$this->disable($row['row_id']);
				$this->ndisabled++;

			} catch (Exception $e)
			{
				$msg = 'No se pudo deshabilitar el objeto: '.$this->table.' ('.$row['dto_id'].')';
				if (is_a($e, 'PrestashopException'))
					$msg = $msg.' - '.$e->getMessage();

				$task = $this->task;
				if ($task->stop_on_error)
					throw new Fs2psException($msg, $e);
				else
					$this->task->log('ERROR: '.$msg);
			}

			$idx++;
			$this->nprocessed = $idx;
		}

		$this->task->log($this->name.': '.$this->ndisabled.' deshabilitados');
	}
}

class Fs2psFastDisabler extends Fs2psDisabler
{

	public function __construct($task, $name, $table, $apply_shop_table)
	{
		parent::__construct($task, $name, $table);
		$this->apply_shop_table = $apply_shop_table;
	}

	public function process()
	{
		$cfg = $this->task->cfg;
		$disable = $cfg->get('DISABLE_'.strtoupper($this->name), false);
		if (!$disable)
		{
			$this->task->log($this->name.': 0 deshabilitados');
			return;
		}
		
		$nbefore = Fs2psTools::dbValue('
			SELECT count(1)
			FROM `@DB_'.$this->table.'`
			WHERE `active` = 1
		');

		Fs2psTools::dbExec('
			UPDATE `@DB_'.$this->table.'` ot
			INNER JOIN `@DB_fs2ps_match` m on m.row_id=ot.'.$this->row_id_field.' and m.`table`=\''.$this->table.'\' and m.entity=\''.$this->name.'\' and m.uploaded=0
			SET ot.active=0
		');

		if ($this->apply_shop_table)
		{
			Fs2psTools::dbExec('
				UPDATE `@DB_'.$this->table.'_shop` ot
				INNER JOIN `@DB_fs2ps_match` m on m.row_id=ot.'.$this->row_id_field.' and m.`table`=\''.$this->table.'\' and m.entity=\''.$this->name.'\' and m.uploaded=0
				SET ot.active=0
			');
		}

		$nafter = Fs2psTools::dbValue('
			SELECT count(1)
			FROM `@DB_'.$this->table.'`
			WHERE `active` = 1
		');

		$ndisabled = $nbefore - $nafter;
		$this->task->log($this->name.': '.$ndisabled.' deshabilitados');
	}
}

class Fs2psFastDeleteDisabler extends Fs2psDisabler
{
    
    public function __construct($task, $name, $table)
    {
        parent::__construct($task, $name, $table);
    }
    
    public function process()
    {
        $cfg = $this->task->cfg;
        $disable = $cfg->get('DISABLE_'.strtoupper($this->name), false);
        if (!$disable)
        {
            $this->task->log($this->name.': 0 deshabilitados');
            return;
        }
        
        $nbefore = Fs2psTools::dbValue('
			SELECT count(1)
			FROM `@DB_'.$this->table.'`
			WHERE `deleted` <> 1
		');
        
        Fs2psTools::dbExec('
			UPDATE `@DB_'.$this->table.'` ot
			INNER JOIN `@DB_fs2ps_match` m on m.row_id=ot.'.$this->row_id_field.' and m.`table`=\''.$this->table.'\' and m.uploaded=0
			SET ot.deleted=1
		');
        
        $nafter = Fs2psTools::dbValue('
			SELECT count(1)
			FROM `@DB_'.$this->table.'`
			WHERE `deleted` <> 1
		');
        
        $ndisabled = $nbefore - $nafter;
        $this->task->log($this->name.': '.$ndisabled.' deshabilitados');
    }
}

class Fs2psFastHideDisabler extends Fs2psDisabler
{
    
    public function __construct($task, $name, $table, $apply_shop_table)
    {
        parent::__construct($task, $name, $table);
        $this->apply_shop_table = $apply_shop_table;
    }
    
    public function process()
    {
        $cfg = $this->task->cfg;
        $hider = $cfg->get('DISABLE_'.strtoupper($this->name), false);
        if (!$hider)
        {
            $this->task->log($this->name.': 0 ocultados');
            return;
        }
        
        $nbefore = Fs2psTools::dbValue('
			SELECT count(1)
			FROM `@DB_'.$this->table.'`
			WHERE `visibility` = "both"
		');
        
        Fs2psTools::dbExec('
			UPDATE `@DB_'.$this->table.'` ot
			INNER JOIN `@DB_fs2ps_match` m on m.row_id=ot.'.$this->row_id_field.' and m.`table`=\''.$this->table.'\' and m.entity=\''.$this->name.'\' and m.uploaded=0
			SET ot.visibility="none"
		');
        
        if ($this->apply_shop_table)
        {
            Fs2psTools::dbExec('
				UPDATE `@DB_'.$this->table.'_shop` ot
				INNER JOIN `@DB_fs2ps_match` m on m.row_id=ot.'.$this->row_id_field.' and m.`table`=\''.$this->table.'\' and m.entity=\''.$this->name.'\' and m.uploaded=0
				SET ot.visibility="none"
			');
        }
        
        $nafter = Fs2psTools::dbValue('
			SELECT count(1)
			FROM `@DB_'.$this->table.'`
			WHERE `visibility` = "both"
		');
        
        $ndisabled = $nbefore - $nafter;
        $this->task->log($this->name.': '.$ndisabled.' ocultados');
    }
}

class Fs2psProductDisabler extends Fs2psFastDisabler
{
	public function __construct($task, $name)
	{
		parent::__construct($task, $name, 'product', true);
	}
}

class Fs2psProductFastHideDisabler extends Fs2psFastHideDisabler
{
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'product', true);
    }
}

class Fs2psManufacturerDisabler extends Fs2psFastDisabler
{
	public function __construct($task, $name)
	{
		parent::__construct($task, $name, 'manufacturer', false);
	}
}

class Fs2psSupplierDisabler extends Fs2psFastDisabler
{
	public function __construct($task, $name)
	{
		parent::__construct($task, $name, 'supplier', false);
	}
}

class Fs2psCategoryDisabler extends Fs2psFastDisabler
{
	public function __construct($task, $name)
	{
		parent::__construct($task, $name, 'category', false);
	}
	
	public function process()
	{
		parent::process();
		Category::regenerateEntireNtree();
	}
	
}

class Fs2psCustomerDisabler extends Fs2psFastDisabler
{
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'customer', false);
    }
}


class Fs2psAddressDisabler extends Fs2psFastDeleteDisabler
{
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'address');
    }
}


class Fs2psAddressManagedCustomersDisabler extends Fs2psDisabler
{
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'address');
    }
    
    public function process()
    {
        $cfg = $this->task->cfg;
        $disable = $cfg->get('DISABLE_'.strtoupper($this->name), false);
        if (!$disable)
        {
            $this->task->log($this->name.': 0 deshabilitados');
            return;
        }
        
		$count_enabled_sql = 'SELECT count(1) FROM `@DB_address` where deleted<>1';

		$nbefore = Fs2psTools::dbValue($count_enabled_sql);
        
		// Deshabilitamos direcciones de clientes gestionados (subidos, mc.uploaded=1) 
		// que no estén subidas (ma.uploaded=0 o ma.uploaded is null)
        Fs2psTools::dbExec('
			UPDATE 
			`@DB_fs2ps_match` mc
			inner join `@DB_address` a on a.id_customer=mc.row_id and a.deleted<>1
			left join `@DB_fs2ps_match` ma on ma.entity=\'customer_addresses\' and ma.row_id=a.id_address
			set a.deleted=1
			where mc.entity=\'customers\' and mc.uploaded=1 and (ma.uploaded=0 or ma.uploaded is null)
		');
        
        $nafter = Fs2psTools::dbValue($count_enabled_sql);
        
        $ndisabled = $nbefore - $nafter;
        $this->task->log($this->name.': '.$ndisabled.' deshabilitados');
    }
}
