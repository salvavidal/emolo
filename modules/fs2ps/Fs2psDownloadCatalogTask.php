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
include_once(dirname(__FILE__).'/Fs2psExtractors.php');

class Fs2psDownloadCatalogTask extends Fs2psExtractorTask
{
	public function __construct($mng, $cmd)
	{
		parent::__construct('download_catalog', $mng, $cmd);
	}
	
	public function preExecute()
	{
	    $this->addExtractor('attribute_groups', 'Fs2psAttributeGroupExtractor');
	    $this->addExtractor('sizes', 'Fs2psAttributeExtractor');
	    $this->addExtractor('colours', 'Fs2psAttributeExtractor');
	    $this->addExtractor('sections', 'Fs2psSectionExtractor');
	    $this->addExtractor('families', 'Fs2psFamilyExtractor');
	    $this->addExtractor('manufacturers', 'Fs2psManufacturerExtractor');
	    $this->addExtractor('suppliers', 'Fs2psSupplierExtractor');
	    $this->addExtractor('products', 'Fs2psProductExtractor');
	    $this->addExtractor('combinations', 'Fs2psSizeColourCombinationExtractor');
	    //$this->addExtractor('pack_items', 'Fs2psPackItemExtractor');
	}
	
}
