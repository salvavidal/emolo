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
include_once(dirname(__FILE__).'/Fs2psMatcherFactory.php');


class Fs2psDtoAbstractProcessor
{
    protected $task;
    protected $name;
    protected $nprocessed = 0;
    protected $ntotal = 0;
    
    public function __construct($task, $name)
    {
        $this->task = $task;
        $this->name = $name;
        $this->reloadCfg();
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    protected function resetCounters($dtos=null) {
        $this->nprocessed = 0;
        $this->ntotal = $dtos==null? 0 : count($dtos);
    }
    
    protected function reloadCfg() {
    }
    
    public function process($dtos)
    {
        throw new Fs2psNotImplemented();
    }
    
    public function getCompletedPercent()
    {
        return (int)(($this->nprocessed * 100) / $this->ntotal);
    }
    
}

class Fs2psDtoProcessor extends Fs2psDtoAbstractProcessor
{
	protected $ncreated = 0;
	protected $nupdated = 0;
	protected $ndeleted = 0;
	public $matcher;

	public function __construct($task, $name)
	{
		$this->task = $task;
		$this->name = $name;
		$this->matcher = Fs2psMatcherFactory::get($task, $name);
		$this->object_model_cls = isset($this->matcher->table)? 'Fs2ps'.Fs2psTools::tableToObjectModelCls($this->matcher->table) : null;
		$this->reloadCfg();
	}

	protected function resetCounters($dtos=null) {
	    parent::resetCounters($dtos);
		$this->ncreated = 0;
		$this->nupdated = 0;
		$this->ndeleted = 0;
	}
	
	protected function dto2row($dto, $idx, $exists, $oldRowId)
	{
	    throw new Fs2psNotImplemented();
	}
	
	protected function sameGroup($row_a, $row_b)
	{
		return false;
	}

	protected function onGroupUpdated($group_rows)
	{

	}

}
