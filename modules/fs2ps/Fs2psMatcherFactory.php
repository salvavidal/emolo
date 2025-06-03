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

include_once(dirname(__FILE__).'/Fs2psException.php');
include_once(dirname(__FILE__).'/Fs2psMatchers.php');


class Fs2psMatcherFactory
{
    
    // Instance cache by entity
    protected static $MATCHER_INSTANCES = array();
    
    // Class or static method to create instances by entity/task regexp
    protected static $MATCHER_CREATION = array(
        'categories' => array('/.*/' => 'Fs2psCategoryMatcher'),
        'sections' => array('/.*/' => 'Fs2psSectionExtractorMatcher'),
        'families' => array('/.*/' => 'Fs2psFamilyExtractorMatcher'),
        'manufacturers' => array('/.*/' => 'Fs2psManufacturerMatcher'),
        'suppliers' => array('/.*/' => 'Fs2psSupplierMatcher'), // Only PS
        'price_rates' => array('/.*/' => 'Fs2psGroupMatcher'), // Only PS
        'attribute_groups' =>array('/.*/' => 'createAttributeGroupMatcher'),
        'sizes' => array('/.*/' => 'createAttributeMatcher'),
        'colours' => array('/.*/' => 'createAttributeMatcher'),
        'features' => array('/.*/' => 'Fs2psFeatureMatcher'), // Only PS
        'products' => array('/.*/' => 'createProductMatcher'),
        'special_offers' => array('/.*/' => 'createProductMatcher'),
        'combinations' => array(
            '/^download_(products|catalog|images)$/' => 'Fs2psSizeColourExtractorMatcher',
            '/(?!(^download_(products|catalog)))/' => 'createCombinationMatcher',
        ),
        'orders' => array('/.*/' => 'Fs2psOrderMatcher'), // Only PS
        'orders_downloaded' => array('/.*/' => 'Fs2psOrderMatcher'), // Only PS
        'orders_returned' => array('/.*/' => 'Fs2psOrderMatcher'), // Only PS
        'orders_sent' => array('/.*/' => 'Fs2psOrderMatcher'), // Only PS
        'orders_invoiced' => array('/.*/' => 'Fs2psOrderMatcher'), // Only PS
        'orders_sent_track' => array('/.*/' => 'Fs2psOrderMatcher'), // Only PS
        'orders_invoiced_track' => array('/.*/' => 'Fs2psOrderMatcher'), // Only PS
        'customers' => array('/.*/' => 'Fs2psCustomerUpdateByEmailMatcher'), // Only PS
        'customer_addresses' => array('/.*/' => 'Fs2psAddressUpdateByNifMatcher'), // Only PS
        'product_images' => array('/.*/' => 'Fs2psProductImagesMatcher'),
    );
    
    public static function get($task, $entity) {
        
        // First check if there is an instance allready in cache
        if (!empty(self::$MATCHER_INSTANCES[$entity])) return self::$MATCHER_INSTANCES[$entity];
        
        // Create matcher instance
        if (empty(self::$MATCHER_CREATION[$entity])) {
            throw new Fs2psServerFatalException('No se pudo encontrar un mapper para '.$entity);
        }
        $creation_by_task = self::$MATCHER_CREATION[$entity];
        $task_name = $task->getOp();
        foreach ($creation_by_task as $task_regexp => $creator) {
            $matches = $task_regexp=='/.*/'; // '/.*/' regexp super optimization :P
            if (!$matches) preg_match($task_regexp, $task_name, $matches);
            if ($matches) {
                $instance = (
                    strtolower($creator[0])==$creator[0]? // Start with lowercase?
                    self::$creator($task, $entity) :  // Call static method
                    new $creator($task, $entity)  // Call matcher class constructor
                );
                break;
            }
        }
        if (empty($instance)) {
            throw new Fs2psServerFatalException('No se pudo encontrar un mapper para '.$task_name.'/'.$entity);
        }
        
        // Save instance in cache
        self::$MATCHER_INSTANCES[$entity] = $instance;
        
        return $instance;
    }
    
    public static function createAttributeGroupMatcher($task, $entity) {
        $matches = null;
        preg_match('/^download_(products|catalog)$/', $task->getOp(), $matches);
        return new Fs2psAttributeGroupMatcher($task, $entity, $matches? TRUE : FALSE);
    }
    
    public static function createAttributeMatcher($task, $entity) {
        $matches = null;
        preg_match('/^download_(products|catalog)$/', $task->getOp(), $matches);
        $matcher_cls = $matches? 'Fs2psAttributeExtractorMatcher' : 'Fs2psAttributeMatcher';
        return new $matcher_cls($task, $entity);
    }
    
    public static function createProductMatcher($task, $entity) {
        $task_name = $task->getOp();
        
        $pm = $task->cfg->get('PRODUCTS_MATCHER', 'ref');
                
        // Estos matchers se usan también para la extracción de productos.
        // pref=reference y ean=ena13 se envían siempre en la extracción para que en la parte de Factusol se decida qué hacer con ellos.
        // En la extracción, si persist=true se intenta obtener el dto_id de la tabla de match. Si no hay match o persist=false, se 
        // genera dtoid a partir info de row según matcher (ver comentarios "dto_id generado = " en cada matcher abajo) 
        $matcher_cls = null;
        switch ($pm) {
            case 'direct': $matcher_cls = 'Fs2psDto2RowDirectMatcher'; break; // dto_id generado = row_id
            case 'stockabledirect': // dto_id generado = row_id.'0'. Necesario en Prestashop para diferenciar tabla product/product_attribute
                return new Fs2psStockableDirectMatcher($task, $entity);
                break;       
            case 'productcombi': // dto_id generado = <id_product>_<id_product_attribute> o <id_product> si es producto simple.
                return new Fs2psProductCombiMatcher($task, $entity);
                break;         
            case 'basic': $matcher_cls = 'Fs2psDto2RowBasicMatcher'; break; // dto_id generado = row_id
            case 'ref': $matcher_cls = 'Fs2psDto2RowRefMatcher'; break; // dto_id generado = reference
            case 'pref': $matcher_cls = 'Fs2psDto2RowPRefMatcher'; break; // dto_id generado = reference
            case 'ean': $matcher_cls = 'Fs2psDto2RowEanMatcher'; break; // dto_id generado = ean
            case 'null': $matcher_cls = 'Fs2psDto2RowNullMatcher'; break; // no hacer nunca match
            
            case 'multiref': $matcher_cls = 'Fs2psDto2MultiRowRefMatcher'; break;
            case 'multipref': $matcher_cls = 'Fs2psDto2MultiRowPRefMatcher'; break;
            case 'multiean': $matcher_cls = 'Fs2psDto2MultiRowEanMatcher'; break;
        }
        if (empty($matcher_cls)) {
            throw new Fs2psServerFatalException("PRODUCTS_MATCHER no implementado '$pm'");
        }
        
        $matcher = new $matcher_cls($entity, 'product', array('ref'), true);
        $matcher->reloadCfg($task->cfg);
        return $matcher;
    }
    
    /*
     Estrategias de matching de combinaciones.
     
     0) En cualquier caso:
     Download:
     - FORCED_MATCHES no aplica en el caso de las combinaciones.
     - DIRECT_MATCH no aplica en el caso de las combinaciones.
     ?) SLUG. dto_id coincidirá con el slug. Caso de Nonbak. TODO. Es la estragegia por defecto en WooCommerce.
     1) REF. No persistimos.
     Update: row <= dto
     - Si USE_CREF row[sku] <= dto_id = dto[cref].
     - Si no, row[sku] <= dto_id = dto[ref]_dto[size]_dto[colour].
     Download: dto <= row
     - Si USE_CREF, dto[cref] = dto_id <= row[sku]. Si no hay row[sku] -> ERROR. En Factusol almacenaremos dto_id en campo ean.
     - Si no, row[sku] <= dto[ref]_dto[size]_dto[colour] = dto_id = <parent_dto_id>_<size_dto_id>_<colour_dto_id>. Guardamos dto_id en <sku>.
     3) BASIC. Persistimos.
     Update: row <= dto
     - Si USE_CREF match_dto_id = dto_id = dto[cref]
     - Si no, match_dto_id = dto_id = dto[ref]_dto[size]_dto[colour]
     - En cualquier caso:
     Se busca matching match_dto_id = dto_id
     row[sku] = dto[pref] si isset(dto[pref])
     Download: dto <= row
     - Si USE_CREF, dto[cref] = dto_id <= match_dto_id. Si no hay match_dto_id -> ERROR. En Factusol almacenaremos dto_id en campo ean.
     - Si no, match_dto_id <= dto[ref]_dto[size]_dto[colour] = dto_id = <parent_dto_id>_<size_dto_id>_<colour_dto_id>. Guardamos match_dto_id.
     - En cualquier caso dto[pref] = row['sku']
     4) PREF. Persistimos y exportamos sku a Factusol como pref. En el lado de Factusol usaremos el ean para almacenar pref.
     Update: Ídem que BASIC pero si !empty(dto['pref']) se busca primero matching por row['reference']=dto['pref'] y si no hay matching se hace después por matched_dto_id=dto_id.
     Download: Ídem que BASIC.
     
     N) DIRECT, EAN, AGREGATE ...
     
     */
    public static function createCombinationMatcher($task, $entity) {
        $cfg = $task->cfg;
        $task_name = $task->getOp();
        $pm = $task->cfg->get('COMBINATIONS_MATCHER', 'ref');
                
        // Estos matchers no se usan para la extracción de combinaciones.
        // La extracción de combinaciones se hace siempre a través de Fs2psSizeColourExtractorMatcher
        $matcher_cls = null;
        switch ($pm) {
            case 'direct': $matcher_cls = 'Fs2psDto2RowDirectMatcher'; break; // Tendría sentido con USE_CREF y el row_id en cref
            case 'stockabledirect': // dto_id generado = row_id.'1'. Necesario en Prestashop para diferenciar tabla product/product_attribute
                return new Fs2psStockableDirectMatcher($task, $entity);
                break;   
            case 'productcombi': // dto_id generado = <id_product>_<id_product_attribute> o <id_product> si es producto simple.
                return new Fs2psProductCombiMatcher($task, $entity);
                break;     
            case 'basic': $matcher_cls = 'Fs2psDto2RowBasicMatcher'; break;
            case 'ref': $matcher_cls = 'Fs2psDto2RowRefMatcher'; break;
            case 'pref': $matcher_cls = 'Fs2psDto2RowPRefMatcher'; break;
            case 'ean': $matcher_cls = 'Fs2psDto2RowEanMatcher'; break;
            case 'null': $matcher_cls = 'Fs2psDto2RowNullMatcher'; break; // no hacer nunca match
            
            case 'multiref': $matcher_cls = 'Fs2psDto2MultiRowRefMatcher'; break;
            case 'multipref': $matcher_cls = 'Fs2psDto2MultiRowPRefMatcher'; break;
            case 'multiean': $matcher_cls = 'Fs2psDto2MultiRowEanMatcher'; break;
        }
        if (empty($matcher_cls)) {
            throw new Fs2psServerFatalException("COMBINATIONS_MATCHER no implementado '$pm'");
        }

        // USE_CREF indica que la combinación lleva su propia referencia.
        // En este caso no se cumple que $dto_id_fields == array('ref', 'size', 'colour'), pero
        // la descarga de pedidos debería funcionar correctamente porque se basa en el mismo matcher.
        $dto_id_fields = $cfg->get('USE_CREF', FALSE)? array('cref') : array('ref', 'size', 'colour');
        
        $persist = $pm!='ref' && $pm!='direct';

        $matcher = new $matcher_cls($entity, 'product_attribute', $dto_id_fields, $persist);

        // XXX cfillol (Ver FS2PS-291): A partir de cierta versión de Prestashop las combinaciones no se borran,
        // se pone el id_product a 0!!! Evitamos que esto interfiera.
        if (method_exists($matcher, 'setOidExtraWhere')){
            $matcher->setOidExtraWhere('id_product>0');
        }

        $matcher->reloadCfg($task->cfg);
        
        return $matcher;
    }
	
}
