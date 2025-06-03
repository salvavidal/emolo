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

class Fs2psExtractor
{
	protected $task;
	protected $name;
	protected $sql;
	
	protected $offset;
	protected $ntotal;
	protected $pendingDtoGroup;
	
	public function __construct($task, $name)
	{
		$this->task = $task;
		$this->name = $name;
		$this->reloadCfg();
		$this->sql = $this->buildSql();
		$this->ntotal = $this->count();
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	protected function reloadCfg() { }
	
	protected function buildSql()
	{
	    throw new Fs2psNotImplemented();
	}
	
	public function count()
	{
		 return (int)Fs2psTools::dbValue('
			select count(1) from ('.$this->sql.') t
		');
	}
	
	protected function filterDto($dto)
	{
		return $dto;
	}
	
	protected function row2dto($row)
	{
	    throw new Fs2psNotImplemented();
	}
	
	public function process($limit, $offset) {
		$dtos = array();
		$this->offset = $offset;
		
		// $this->getNpending()>0
		if ($this->ntotal > $this->offset)
		{
			$this->pendingDtoGroup = array();
			
			// Fetch one more that will be pending candidate
			$limit = $limit + 1;
			
			// Not all rows fetched
			while (!$dtos && $this->ntotal > $this->offset)
			{
				$rows = Fs2psTools::dbSelect('
					'.$this->sql.'
					limit '.$this->offset.', '.$limit.'
				');
				$dtos = array_merge($dtos, $this->processWithoutBreakingGroups($rows, $limit));
				$this->offset += sizeof($rows);
			}
			
			$this->offset -= sizeof($this->pendingDtoGroup);
		}
		
		return $dtos;
	}
	
	/**
	 * Sometimes we'll new to download a whole group of rows without 
	 * breaking it. For example the lines of one order. 
	 */
	protected function processWithoutBreakingGroups($rows, $limit)
	{
		$dtos = array();
		$rowIdx = 0;
		while (true)
		{
			$res = $this->nextDtoGroup($rows, $rowIdx, $limit);
			$dtoGroup = $res[0];
			$rowIdx = $res[1];
			
			if (!$dtoGroup) break;
			$dtos = array_merge($dtos, $dtoGroup);
		}
		return $dtos;
	}
	
	protected function sameGroup($dtoA, $dtoB)
	{
		return false;
	}
	
	/**
	 * Gets the next dto group or [] if cannot sure that has 
	 * collected all the dtos of the current group.
	 * Noe that needs to reach a diferent group dto to know 
	 * that has collected a whole group. 
	 */
	protected function nextDtoGroup($rows, $rowIdx, $limit)
	{
		$dtoGroup = $this->pendingDtoGroup;
		$this->pendingDtoGroup = array();
		
		while (
			$rows && $rowIdx<sizeof($rows) && 
			!$this->pendingDtoGroup
		)
		{
			$dtos = $this->row2dto($rows[$rowIdx]);
			if (Fs2psTools::isAssoc($dtos)) $dtos = [ $dtos ];
			if (!empty($dtos)) {
				foreach ($dtos as $dto) {
					$dto = $this->filterDto($dto);
					if ($dto)
					{
						if (!$dtoGroup or $this->sameGroup($dtoGroup[0], $dto))
							$dtoGroup[] = $dto;
						else
							$this->pendingDtoGroup[] = $dto;
					}
				}
			}
			$rowIdx++;
		}
		
		if ($this->pendingDtoGroup || !$rows || sizeof($rows)<$limit)
		{
			// if len(rows)<limit we can be sure there are no more groups and so
			// can flush dto_group and, in next iteration, _pending_dto_group
			return array($dtoGroup, $rowIdx);
		}
		else
		{
			$this->pendingDtoGroup = $dtoGroup;
			return array(null, $rowIdx);
		}
	}
	
	public function getProgressPercent()
	{
		if (!$this->ntotal) 
			return 100;
		return int($this->getNprocessed()/$this->ntotal);
	}
	
	public function getNtotal()
	{
		return $this->ntotal;
	}
	
	public function getNpending()
	{
		return $this->ntotal - $this->offset;
	}
	
	public function getNprocessed()
	{
		return $this->offset;
	}
	
	public function flush()
	{
		$this->offset = $this->ntotal;
	}

}


class Fs2psMatchedExtractor extends Fs2psExtractor
{
    public $matcher;
    public $discard_disabled_products;
    
    public function __construct($task, $name, $matcher=null)
    {
        $this->matcher = empty($matcher)? Fs2psMatcherFactory::get($task, $name) : $matcher;
        $this->discard_disabled_products = $task->cfg->get('DISCARD_DISABLED_PRODUCTS', false);
        parent::__construct($task, $name);
    }

	protected function safeDtoIdStrFromRowId($row_id)
    {
        $matcher = $this->matcher;
        $dto_id_str = $matcher->dtoIdStrFromRowId($row_id);
        if (empty($dto_id_str)) {
            $msg = 'No se pudo deducir el dto_id para '.$this->name.'['.$row_id.']';
            if (!($matcher instanceof Fs2psAttributeExtractorMatcher)) {
                $dto_id_str = $matcher->dtoIdStrFromRowId($row_id);
            }
            throw new Fs2psCannotGetDtoIFromRowId($msg);
        }
        return $dto_id_str;
    }
   
    /**
     * Devolvemos dto con los campos que forman el dto_id inicializados
     * guardando el match en fs2ps_match para que las posteriores subidas
     * del catálog en sentido FS -> Prestashop hagan match.
     */
    protected function row2dto($row)
    {
        $matcher = $this->matcher;
        $row_id = $row['id'];

		$dto_id_str = $this->safeDtoIdStrFromRowId($row_id);
        $matcher->updateReverseMatch($dto_id_str, $row_id);
        
        $dto_id_fields = $matcher->dto_id_fields;
        $nfields = sizeof($dto_id_fields);
        $dto = array();
        $dto_id = $matcher->strToDtoId($dto_id_str);
        if ($nfields==1) {
            $dto[$dto_id_fields[0]] = $dto_id;
        } else {
            for ($i = 0; $i < $nfields; $i++) {
                $dto[$dto_id_fields[$i]] = $dto_id[$i];
            }
        }
        
        return $dto;
    }
    
}
