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
       
class Fs2psRedirectDisabledTask extends Fs2psTask
{

	public function __construct($mng, $cmd)
	{
		parent::__construct('redirect_disabled', $mng, $cmd);
	}

	protected function _execute($cmd)
	{
		// Redirigimos productos inactivos no redirigidos ya, a su categoria por defecto si la tienen
		Fs2psTools::dbExec('
			UPDATE 
				@DB_product p
				inner join @DB_product_shop ps on ps.id_product=p.id_product
			SET 
				p.id_type_redirected=p.id_category_default, 
				p.redirect_type = \'301-category\',
				ps.id_type_redirected=p.id_category_default,
				ps.redirect_type = \'301-category\'
			where p.active=0 and p.state>0 and p.id_category_default>0 and (p.id_type_redirected=0 or p.id_type_redirected is null)
		');
	}

}
