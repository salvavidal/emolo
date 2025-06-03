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

class Fs2psUploadProductCoversTask extends Fs2psUpdaterTask
{

	public function __construct($mng, $cmd)
	{
		parent::__construct('upload_product_covers', $mng, $cmd);
		
		$this->addUpdater('products', 'Fs2psProductUpdater');
	}

	protected function _execute($cmd)
	{
		// { op:'...', covers: [ product: 'Z001', file_name: '...., data: ' ' ]}

		$products_udt = $this->getUpdater('products');
		
		/// XXX cfillol: Sure backwards compatibility.
		// 'covers' was used before version 1.1.5.
		$covers = array();
		if (isset($cmd['covers'])) $covers = $cmd['covers'];
		if (isset($cmd['product_covers'])) $covers = $cmd['product_covers'];
		
		foreach ($covers as $cover_dto)
		{
			$id_product = $products_udt->matcher->rowIdFromDtoId($cover_dto['product']);
			
			if (!$id_product)
			{
				$msg = 'El producto no existe: '.$cover_dto['product'];
				if ($this->stop_on_error) throw new Fs2psException($msg);
				$this->log('ERROR: '.$msg);
				continue;
			}
			
			$old_image = Product::getCover($id_product);
			if ($old_image)
			{
				$old_image = new Image($old_image['id_image']);
				$old_image->delete();
			}

			if (!empty($cover_dto['data'])) {
    			$image = new Image(null);
    			$image->id_product = $id_product;
    			$image->position = 1; //Image::getHighestPosition($id_product) + 1;
    			$image->cover = true;
    			$image->save();
    
    			$tmp_name = tempnam(_PS_IMG_DIR_, 'PS');
    			Fs2psTools::base64DataToFile($cover_dto['data'], $tmp_name);
    			$new_path = $image->getPathForCreation();
    			ImageManager::resize($tmp_name, $new_path.'.'.$image->image_format);
    			$images_types = ImageType::getImagesTypes('products');
    			foreach ($images_types as $image_type)
    			{
    				$dest_file = $new_path.'-'.Tools::stripslashes($image_type['name']).'.'.$image->image_format;
    				ImageManager::resize($tmp_name, $dest_file, $image_type['width'],
    					$image_type['height'], $image->image_format);
    			}
    		
    			unlink($tmp_name); // Borramos la imagen temporal
    		
    			Hook::exec('actionWatermark', array(
    				'id_image' => $image->id,
    				'id_product' => $id_product
			    ));
			}
		}

	}

}
