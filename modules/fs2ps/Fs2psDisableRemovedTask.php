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
include_once(dirname(__FILE__).'/Fs2psDto2RowMatcher.php');
include_once(dirname(__FILE__).'/Fs2psDisablers.php');

class Fs2psDisableRemovedTask extends Fs2psTask
{

	public function __construct($mng, $cmd)
	{
		parent::__construct('disable_removed', $mng, $cmd);
	}

	protected function _execute($cmd)
	{
	    
	    Fs2psDto2RowMatcher::deleteRepeatedZeroMarks();
	    
	    // Deshabilitamos no subidos
	    $disablers_info = array(
			array('category', 'categories'),
			array('product', 'products'),
			array('manufacturer', 'manufacturers'),
			array('supplier', 'suppliers'),
		    array('customer', 'customers'),
	        array('address', 'customer_addresses'),
		);
		foreach ($disablers_info as $disabler_info)
		{
		    $disable_cfg = $this->cfg->get('DISABLE_'.strtoupper($disabler_info[1]), false);
		    $disabler_subcls = is_string($disable_cfg)? join(array_map('ucfirst', explode("_", $disable_cfg))) : '';
		        
		    $cls = 'Fs2ps'.Fs2psTools::tableToObjectModelCls($disabler_info[0]).$disabler_subcls.'Disabler';
			$disabler = new $cls($this, $disabler_info[1]);
			$disabler->process();
		}

	}

}
