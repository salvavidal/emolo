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

class Fs2psDownloadDeliveriesTask extends Fs2psExtractorTask
{
    public function __construct($mng, $cmd)
    {
        parent::__construct('download_deliveries', $mng, $cmd);
        $this->addExtractor('deliveries', 'Fs2psOrderExtractor');
        $this->addExtractor('delivery_lines', 'Fs2psOrderLineExtractor');
    }
}
