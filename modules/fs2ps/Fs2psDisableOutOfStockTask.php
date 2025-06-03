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
       
class Fs2psDisableOutOfStockTask extends Fs2psTask
{

	public function __construct($mng, $cmd)
	{
		parent::__construct('disable_out_of_stock', $mng, $cmd);
	}

	protected function _execute($cmd)
	{
		// Desactivamos productos activos sin stock siendo el stock la suma del stock de las combinaciones si el producto tiene combinaciones
		Fs2psTools::dbExec(
			'
			UPDATE @DB_product p
			inner join @DB_product_shop ps on ps.id_product=p.id_product
			inner join (
				select id_product, if(ncombis, combi_stock, parent_stock) as stock
				from (
					select
						p.id_product,
						sum(if(s.id_product_attribute>0 and s.quantity>0, s.quantity, 0)) as combi_stock, -- Si el stock es negativo consideramos 0 para evitar restar de la suma global
						sum(if(s.id_product_attribute>0, 0, s.quantity)) as parent_stock,
						sum(if(s.id_product_attribute>0, 1, 0)) as ncombis
					from @DB_product p
					inner join @DB_stock_available s on s.id_product=p.id_product
					where p.active = 1
					group by p.id_product
				) s
			) s on s.id_product=p.id_product
			SET	p.active=0, ps.active=0
			where s.stock<=0
			'
        );
	}

}
