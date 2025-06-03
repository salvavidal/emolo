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
include_once(dirname(__FILE__).'/Fs2psUpdaters.php');

class Fs2psUploadCatalogTask extends Fs2psUpdaterTask
{
	
	public function __construct($mng, $cmd)
	{
		parent::__construct('upload_catalog', $mng, $cmd);
		
		$this->addUpdater('categories', 'Fs2psCategoryUpdater');
		$this->addUpdater('manufacturers', 'Fs2psManufacturerUpdater');
		$this->addUpdater('suppliers', 'Fs2psSupplierUpdater');
		$this->addUpdater('price_rates', 'Fs2psGroupUpdater');
		$this->addUpdater('attribute_groups', 'Fs2psAttributeGroupUpdater');
		$this->addUpdater('sizes', 'Fs2psAttributeUpdater');
		$this->addUpdater('colours', 'Fs2psAttributeUpdater');
		$this->addUpdater('features', 'Fs2psFeatureUpdater');
		$this->addUpdater('products', 'Fs2psProductUpdater');
		$this->addUpdater('combinations', 'Fs2psSizeColourCombinationUpdater');
		$this->addUpdater('special_offers', 'Fs2psSpecialOffersUpdater');
	}

}
