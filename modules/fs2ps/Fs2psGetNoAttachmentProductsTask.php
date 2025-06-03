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

class Fs2psGetNoAttachmentProductsTask extends Fs2psTask
{

	public function __construct($mng, $cmd)
	{
		parent::__construct('get_no_attachment_products', $mng, $cmd);
	}

	
	protected function _execute($cmd)
	{
		// { op:'...', product: 'Z001', file_name: '...., data: ' '}
		$no_attachment_products = array();
		$rows = Fs2psTools::dbSelect('
            SELECT dto_id 
            from `@DB_fs2ps_match` m
            LEFT JOIN `@DB_product_attachment` pat on pat.id_product=m.row_id
            WHERE `table`=\'product\' and uploaded=1 and pat.id_product is null
		');

		foreach ($rows as $row)
			$no_attachment_products[] = $row['dto_id'];

		$this->extra_info['no_attachment_products'] = $no_attachment_products;
	}

}
