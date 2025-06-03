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
class Fs2psCfg
{

    protected $cfg = array();

    public function __construct()
    {
        $this->resetToDefaults();
    }

    public function resetToDefaults()
    {
        $this->cfg = array(
            'ENABLE_CATEGORIES' => true,
            'ENABLE_PRODUCTS' => true,
            'ENABLE_MANUFACTURERS' => false,
            'ENABLE_SUPPLIERS' => false,
            'ENABLE_CUSTOMERS' => true,
            'ENABLE_CUSTOMER_ADDRESSES' => true,

            'DISABLE_CATEGORIES' => true,
            'DISABLE_PRODUCTS' => true,
            'DISABLE_MANUFACTURERS' => false,
            'DISABLE_SUPPLIERS' => false,
            'DISABLE_CUSTOMERS' => true,
            'DISABLE_CUSTOMER_ADDRESSES' => false,

            'NOOVER_CATEGORIES_CONTENT' => false,
            'NOOVER_CATEGORIES_PARENT' => false,
            'NOOVER_PRODUCTS_CONTENT' => false,
            'NOOVER_PRODUCTS_CATEGORIES' => false,
            'NOOVER_PRODUCTS_MANUFACTURER' => false,
            'NOOVER_PRODUCTS_SUPPLIERS' => false,
            'NOOVER_MANUFACTURERS_CONTENT' => false,
            'NOOVER_SUPPLIERS_CONTENT' => false,
            'NOOVER_SIZES_CONTENT' => false,
            'NOOVER_COLOURS_CONTENT' => false,

            'MATCH_CATEGORIES' => array(),
            'MATCH_MANUFACTURERS' => array(),
            'MATCH_SUPPLIERS' => array(),

            'MATCH_SUPPLIERS' => array(),

            'MAX_DTOS_BY_DOWNLOAD' => 50,

            'DOWNLOAD_ORDERS' => true,
            'ORDER_LINES_NAME_PATTERN' => '',
            
            'ORDERS_HAVE_PAYMENT_FEE' => false,
            'ORDERS_PAYMENT_FEES' => array(),

            'INVOICES_SEND_EMAIL' => 'ifchanged',

            'MANAGING_PRICE_BY_CUSTOMER' => false,
            'MANAGING_PRICE_FROM_QUANTITY' => false,
            'SAME_GROUP_PRICE_RATES' => true,
            
            'DOWNLOAD_SIZES' => array(),
            'DOWNLOAD_COLOURS' => array(),
            
        );
    }

    public function get($key, $default = null)
    {
        return isset($this->cfg[$key]) ? $this->cfg[$key] : $default;
    }

    public function loadCfg($cfg = null)
    {
        $this->resetToDefaults();
        if (! empty($cfg))
            foreach ($cfg as $key => $val)
                $this->cfg[$key] = $val;
    }
    
}
