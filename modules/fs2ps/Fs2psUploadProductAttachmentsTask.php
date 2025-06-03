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

class Fs2psUploadProductAttachmentsTask extends Fs2psUpdaterTask
{
    
    public function __construct($mng, $cmd)
    {
        parent::__construct('upload_product_attachments', $mng, $cmd);
        $this->addUpdater('products', 'Fs2psProductUpdater');
    }
    
    protected function error($msg)
    {
        if ($this->stop_on_error) {
            throw new Fs2psException($msg);
        }
        $this->log('ERROR: '.$msg);
    }
    
    protected function _execute($cmd)
    {
        // { op:'...', product_attachments: [ product: 'Z001', name: '...., data: ' ' ]}
        if (!isset($cmd['product_attachments'])) return;
        
        $products_udt = $this->getUpdater('products');
        
        $attachments = $cmd['product_attachments'];
        
        foreach ($attachments as $attachment_dto)
        {
            $id_product = $products_udt->matcher->rowIdFromDtoId($attachment_dto['product']);
            
            if (!$id_product)
            {
                $msg = 'El producto no existe: '.$attachment_dto['product'];
                if ($this->stop_on_error) throw new Fs2psException($msg);
                $this->log('ERROR: '.$msg);
                continue;
            }
            
            $old_id_attachment = Fs2psTools::dbValue('
                SELECT at.id_attachment
                FROM `@DB_product_attachment` pat
                INNER JOIN `@DB_attachment` at on at.id_attachment=pat.id_attachment
                WHERE pat.id_product='.$id_product.' and at.file_name=\''.Fs2psTools::dbEscape($attachment_dto['name']).'\'
            ');
            if ($old_id_attachment) {
                $old_attachment = new Attachment($old_id_attachment);
                $old_attachment->delete();
            }
            
            if (!empty($attachment_dto['data'])) {
                
                // Creamos el adjunto
                do $uniqid = sha1(microtime());
                while (file_exists(_PS_DOWNLOAD_DIR_.$uniqid));
                
                file_put_contents(_PS_DOWNLOAD_DIR_.$uniqid, base64_decode($attachment_dto['data']));
                
                // Creamos fichero de adjunto y lo vinculamos con el producto
                // Create file attachment
                $attachment = new Attachment();
                $attachment->file = $uniqid;
                $attachment->mime = $attachment_dto['mime'];
                $attachment->file_name = $attachment_dto['name'];
                
                $attachment->name = Fs2psTools::multiLangField(substr($attachment_dto['name'], 0, 32), true);
                $attachment->description = Fs2psTools::multiLangField('', true);
                $attachment->add();
                $attachment->attachProduct($id_product);
                
            }
        }
    }
}
