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

include_once(dirname(__FILE__).'/Fs2psTools.php');
include_once(dirname(__FILE__).'/Fs2psTask.php');
include_once(dirname(__FILE__).'/Fs2psUpdaters.php');
include_once(dirname(__FILE__).'/Fs2psObjectModels.php');

class Fs2psUploadProductImagesTask extends Fs2psUpdaterTask
{

	public function __construct($mng, $cmd)
	{
		parent::__construct('upload_product_images', $mng, $cmd);
		$this->addUpdater('products', 'Fs2psProductUpdater');
		$this->addUpdater('colours', 'Fs2psAttributeUpdater');
	}

	protected function error($msg)
	{
		if ($this->stop_on_error)
			throw new Fs2psException($msg);
		$this->log('ERROR: '.$msg);
	}
	
	protected function _execute($cmd)
	{
		// { op:'...', images: [ product: 'Z001', file_name: '...., data: ' ' ]}

		$products_udt = $this->getUpdater('products');
		$colours_upd = $this->getUpdater('colours');
		
		$product_images = array();
		if (isset($cmd['product_images'])) $product_images = $cmd['product_images'];
		
		foreach ($product_images as $dto)
		{
			$id_product = $products_udt->matcher->rowIdFromDtoId($dto['product']);
			if (!$id_product) {
				$this->error('El producto no existe: '.$dto['product']);
				continue;
			}

			if (empty($dto['position'])) {
				$lastPosition = Fs2psTools::dbValue('
					SELECT max(position) FROM `@DB_image`
					WHERE `id_product` = '.$id_product
				);
				$position = empty($lastPosition)? 1 : intval($lastPosition);
			} else {
				$position = intval($dto['position']);
			}
			
			$id_colour = null;
			if (isset($dto['colour']) && (!empty($dto['colour']) || $dto['colour']==='0'))
			{
				$id_colour = $colours_upd->matcher->rowIdFromDtoId($dto['colour']);
				if (!$id_colour) {
					$this->error('El color no existe: '.$dto['colour']);
					continue;
				}
			}
			
			if (isset($dto['last'])) {
			    // Si se indica el último índice, eliminamos imágenes posteriores a la última si las hubiera 
			    // (si last==0 se eliminarían todas)
			    $delete_ids = Fs2psTools::dbSelect('
    				SELECT id_image FROM `@DB_image`
    				WHERE `id_product` = '.$id_product.' and position>'.$dto['last']
		        );
			    foreach ($delete_ids as $delete_id_row) {
			        $delete_image = new Fs2psImage($delete_id_row['id_image']);
			        $delete_image->deleteNoOrder();
			    }
			}
			
			if (empty($dto['data']))
			{
				// Modo eliminar si data está vacío
				
				if (empty($id_colour)) {
					// Eliminamos todas las imágenes del producto si no se indicó color
					$product = new Fs2psProduct($id_product);
					$product->deleteImages();
				} else	{
					// TODO: Sólo eliminamos las del color indicado si se indicó un color			
				}
			} 
			else 
			{
				// Creamos la imagen
				$cover = ($position==1);

				// Quitamos cover de otras imágenes si procede
				if ($cover) {
					$cover_ids = Fs2psTools::dbSelect('
						SELECT id_image FROM `@DB_image`
						WHERE `id_product` = '.$id_product.' and cover=1 and position<>'.$position
					);
					foreach ($cover_ids as $cover_id_row) {
						$cover_image = new Fs2psImage($cover_id_row['id_image']);
						$cover_image->cover = 0;
						$cover_image->save();
					}
				}

				// Eliminamos imágen anterior en la misma posición si la hubiera
			    $old_id_image_ids = Fs2psTools::dbSelect('
					SELECT id_image FROM `@DB_image`
					WHERE `id_product` = '.$id_product.' and position='.$position 
				);
			    foreach ($old_id_image_ids as $old_id_row) {
			        $delete_image = new Fs2psImage($old_id_row['id_image']);
			        $delete_image->deleteNoOrder();
				}

				// Creamos fichero de imagen y lo vinculamos con el producto
				$image = new Fs2psImage(null);
				$image->id_product = $id_product;
				$image->position = $position;
				$image->cover = $cover;
				if (isset($dto['alt'])) $image->legend = $dto['alt'];
				$image->save();
	
				$tmp_name = tempnam(_PS_IMG_DIR_, 'PS');
				Fs2psTools::base64DataToFile($dto['data'], $tmp_name);
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
				
				// Incorporamos marca de agua si procede
				Hook::exec('actionWatermark', array(
					'id_image' => $image->id,
					'id_product' => $id_product
				));
				
				// Vinculamos imagen con combinaciones correspondientes
				if ($id_colour)
				{
					$rows = Fs2psTools::dbSelect('
						SELECT pa.id_product_attribute
						FROM @DB_product_attribute pa
						JOIN @DB_product_attribute_combination pac ON pac.id_product_attribute=pa.id_product_attribute
						WHERE pa.id_product='.$id_product.' and pac.id_attribute='.$id_colour.'
					');
				
					foreach ($rows as $row) {
						Fs2PsTools::dbExec('
							INSERT INTO `@DB_product_attribute_image` (`id_product_attribute`, `id_image`)
							VALUES ('.$row['id_product_attribute'].', '.$image->id.')'
						);
					}
				}
			}
		}

	}

}
