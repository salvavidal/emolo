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

class Fs2psCombiasprodDownloadProductImagesTask extends Fs2psExtractorTask
{
	public function __construct($mng, $cmd)
	{
		parent::__construct('download_product_images', $mng, $cmd);
	}
	
	public function preExecute()
	{
	    $this->addExtractor('product_images', 'Fs2psCombiasprodImageExtractor');
	}
	
}

class Fs2psCombiasprodImageExtractor extends Fs2psExtractor {

    protected $after;
    protected $after_timestamp;
    protected $cover;

    public function __construct($task, $name)
    {
        $this->after = (new DateTime())->setTimestamp(0);
        $this->after_timestamp = 0;
        $this->task = $task;
        parent::__construct($task, $name);
    }
    
    protected function reloadCfg() {
        parent::reloadCfg();
        if(!empty($this->task->cmd['after'])) {
            $this->after = Fs2psTools::dto2date($this->task->cmd['after']);
            $this->after_timestamp = $this->after->getTimestamp();
        }

        //$cfg = $this->task->cfg;
        //$download_images = $cfg->get('DOWNLOAD_PRODUCTS_IMAGES', '');
        //$this->cover = array_search('onlycover', $download_images) !== false;
    }

    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');

        $after_str = Fs2psTools::date2db($this->after);       
        $where = 'p.date_upd>\''.$after_str.'\'';
        //La descarga de imagenes combiasprod siempre es onlycover debido a que no tenemos la posicion de las imgenes
		return '
		select 
			IF(pa.id_product_attribute is null, p.id_product*10, pa.id_product_attribute*10 + 1) as id,
            IF(pa.reference is null, p.reference, pa.reference) as ref,
			1 as position,
			p.date_upd as updated,
			pl.link_rewrite,
            IF(pai.id_image is null, i.id_image, pai.id_image) as id_image,
            p.id_product,
			pa.id_product_attribute

			from @DB_image i
			inner join @DB_product p on p.id_product=i.id_product
			inner join @DB_product_lang pl on pl.id_product=p.id_product and pl.id_lang = '.$id_default_lang.'
			left join @DB_product_attribute pa on pa.id_product = p.id_product
			left join @DB_product_attribute_image pai on pai.id_product_attribute = pa.id_product_attribute
		where '.$where.' and i.cover=1
		group by p.id_product, pa.id_product_attribute
		
		';
    }

    protected function row2dto($row)
    {
        $dto = [
            'product' => $row['id'],
            'id_product' => $row['id_product'],
            'ref' => $row['ref'],
            'link_rewrite' => $row['link_rewrite'],
            'position' => $row['position']
        ];

        $this->context = Context::getContext();
        $dto['img_url'] = $this->context->link->getImageLink($row['link_rewrite'], $row['id_image']);

        $image = new Image($row['id_image']);
        $path = _PS_PROD_IMG_DIR_ . $image->getExistingImgPath() . '.' . $image->image_format;
        if (!file_exists($path)) return null;
        $dto['type'] = $image->image_format;

        if (filemtime($path)>=$this->after_timestamp) {
            $data = base64_encode(file_get_contents($path));
            $dto['data'] = $data;
        }
        $dto['updated'] = $row['updated'];

        return $dto;
    }
}
