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

include_once(dirname(__FILE__).'/Fs2psDto2RowMatcher.php');
include_once(dirname(__FILE__).'/Fs2psException.php');
include_once(dirname(__FILE__).'/Fs2psTools.php');


class Fs2psCategoryMatcher extends Fs2psDto2RowOidMatcher
{
    public function __construct($task, $entity) {
        // OJO: La entity de Section y Family matcher será 'categories' y no el nombre del extractor
        parent::__construct('categories', 'category', array('ref'), null, null, true, true);
        $this->reloadCfg($task->cfg);
    }
}

class Fs2psSectionExtractorMatcher extends Fs2psCategoryMatcher
{
    // TODO: Implementar traducción inversa en _rowIdFromOid($oid)?
    public function _oidStrFromRowId($row_id) { 
        return sprintf('S%03s', $this->generateDtoIdStrFromRowId($row_id)); 
    }
}

class Fs2psFamilyExtractorMatcher extends Fs2psCategoryMatcher
{
    public function _oidStrFromRowId($row_id) {
        return sprintf('F%03s', $this->generateDtoIdStrFromRowId($row_id));
    }
}

class Fs2psManufacturerMatcher extends Fs2psDto2RowMatcher
{
    public function __construct($task, $entity) {
        parent::__construct($entity, 'manufacturer', 'id_manufacturer', array('ref'), true);
        $this->reloadCfg($task->cfg);
    }
    
    public function _dtoIdStrFromRowId($row_id)
    {
        // TODO: Revisar
        // Si no hay match, generamos un dto_id numérico a partir del row_id
        $dto_id = parent::_dtoIdStrFromRowId($row_id);
        return empty($dto_id)? $this->generateDtoIdStrFromRowId($row_id) : $dto_id;
    }
}

class Fs2psSupplierMatcher extends Fs2psDto2RowMatcher
{
    public function __construct($task, $entity) {
        parent::__construct($entity, 'supplier', 'id_supplier', array('ref'), true);
        $this->reloadCfg($task->cfg);
    }
    
    public function _dtoIdStrFromRowId($row_id)
    {
        // TODO: Revisar
        // Si no hay match, generamos un dto_id numérico a partir del row_id
        $dto_id = parent::_dtoIdStrFromRowId($row_id);
        return empty($dto_id)? $this->generateDtoIdStrFromRowId($row_id) : $dto_id;
    }
}

class Fs2psGroupMatcher extends Fs2psDto2RowMatcher
{
    public function __construct($task, $entity) {
        parent::__construct($entity, 'group', 'id_group', array('ref'), true, true);
        $this->reloadCfg($task->cfg);
    }
}

class Fs2psAttributeGroupMatcher extends Fs2psDto2RowMatcher
{
    protected $several_row_ids_allowed = false;
    
    public function __construct($task, $entity, $several_row_ids_allowed = false)
    {
        $this->several_row_ids_allowed = $several_row_ids_allowed;
        parent::__construct($entity, 'attribute_group', 'id_attribute_group', array('ref'), !$several_row_ids_allowed);
        $this->reloadCfg($task->cfg);
    }
    
    public function rowIdFromDtoId($dto_id)
    {
        $row_id = parent::rowIdFromDtoId($dto_id);
        if ($row_id && is_string($row_id)) {
            $row_id = array_map('intval', preg_split("/ *, */", $row_id));
            if (sizeof($row_id)==1) $row_id = $row_id[0];
            else if (sizeof($row_id)>1 && !$this->several_row_ids_allowed) {
                throw new Fs2psException(
                    'No se permite '.$dto_id.' mapeado con varios grupos: '.implode(',', $row_id)
                );
            }
        }
        return $row_id;
    }
}

class Fs2psAttributeMatcher extends Fs2psDto2RowMatcher
{
    protected $task;
    
    public function __construct($task, $entity)
    {
        $this->task = $task;
        parent::__construct($entity, 'attribute', 'id_attribute', array('ref'), true);
        $this->reloadCfg($task->cfg);
    }
}

class Fs2psAttributeExtractorMatcher extends Fs2psAttributeMatcher
{
    protected $dto_id_from_name_replacex;
    protected $dto_id_from_name_patterns;
    protected $id_default_lang;
    
    public function reloadCfg($cfg) {
        parent::reloadCfg($cfg);
        $this->dto_id_from_name_replacex = $cfg->get(
            'IMATCH_'.strtoupper($this->entity).'_NAME_REPLACEX',
            array()
        );
        $this->dto_id_from_name_patterns = $cfg->get(
            'IMATCH_'.strtoupper($this->entity).'_NAME_PATTERNS',
            array('/^([^ ])$/', '/^([^ ])([^ ])$/', '/^([^ ])([^ ])([^ ])$/', '/^([^ ])([^ ])[^ ]* ([^ ]).*$/')
        );
        $this->id_default_lang = Configuration::get('PS_LANG_DEFAULT');
    }
    
    public function deduceDtoIdStrFromName($name, $row_id=null)
    {
        if (empty($name) && $name!=='0') return null;
        foreach ($this->dto_id_from_name_replacex as $search => $replacex) {
            $name = preg_replace($search, $replacex, $name);
        }
        if (empty($name)) return null;
        
        $matches = null;
        foreach ($this->dto_id_from_name_patterns as $pattern) {
            preg_match($pattern, $name, $matches);
            if ($matches) {
                $dto_id = strtoupper(implode('', array_slice($matches, 1)));
                if ($row_id) {
                    $other_row_id = $this->rowIdFromDtoId($dto_id);
                    if (!empty($other_row_id)) {
                        $this->task->log('WARN: '.$this->entity.' '.$row_id.' y '.$other_row_id.' comparten el mismo dto_id: '.$dto_id);
                    }
                }
                break;
            }
        }
        
        if (empty($dto_id)) {
            $msg = 'No se descartó ni se pudo deducir el dto_id para '.$this->entity.($row_id? '['.$row_id.']' : '').': '.$name;
            throw new Fs2psException($msg);
        }
        
        return $dto_id;
    }
    
    public function _dtoIdStrFromRowId($row_id)
    {
        if (empty($row_id)) return '';
        
        $dto_id = parent::_dtoIdStrFromRowId($row_id);
        if (!empty($dto_id)) return $dto_id;
        
        // Si no hay match trataremos de obtener el dtoId a partir del nombre
        $name = Fs2psTools::dbValue('
            select al.name
            from `@DB_attribute` a
            inner join `@DB_attribute_lang` al on al.id_attribute=a.id_attribute and al.id_lang='.$this->id_default_lang.'
            where a.id_attribute = '.$row_id.'
		');
        return $this->deduceDtoIdStrFromName($name, $row_id);
    }
}

class Fs2psFeatureMatcher extends Fs2psDto2RowMatcher
{
    public function __construct($task, $entity) {
        parent::__construct($entity, 'feature', 'id_feature', array('ref'), true);
        $this->reloadCfg($task->cfg);
    }
}

class Fs2psSizeColourExtractorMatcher extends Fs2psDto2RowMatcher
{
    protected $productMatcher;
    protected $sizeMatcher;
    protected $colourMatcher;
    
    public $sizeAttributeGroupIds;
    public $sizeAttributeGroupIdsInSql;
    public $colourAttributeGroupIds;
    public $colourAttributeGroupIdsInSql;
    
    public function __construct($task, $entity) {
        $this->productMatcher = $task->getExtractor('products')->matcher;
        $this->sizeMatcher = $task->getExtractor('sizes')->matcher;
        $this->colourMatcher = $task->getExtractor('colours')->matcher;
        
        $attribute_group_matcher = $task->getExtractor('attribute_groups')->matcher;
        $this->sizeAttributeGroupIds = $attribute_group_matcher->rowIdFromDtoId('SIZES');
        $this->sizeAttributeGroupIdsInSql = $this->sizeAttributeGroupIds? (is_array($this->sizeAttributeGroupIds)? implode(',', $this->sizeAttributeGroupIds) : $this->sizeAttributeGroupIds) : 0;
        $this->colourAttributeGroupIds = $attribute_group_matcher->rowIdFromDtoId('COLOURS');
        $this->colourAttributeGroupIdsInSql = $this->colourAttributeGroupIds? (is_array($this->colourAttributeGroupIds)? implode(',', $this->colourAttributeGroupIds) : $this->colourAttributeGroupIds) : 0;
        
        parent::__construct($entity, 'product_attribute', 'id_product_attribute', null, null);
        $this->reloadCfg($task->cfg);
    }
    
    public function reloadCfg($cfg) {
        parent::reloadCfg($cfg);
        // USE_CREF indica que la combinación lleva su propia referencia.
        $this->dto_id_fields = $cfg->get('USE_CREF', False)? array('cref') : array('ref', 'size', 'colour');
        
        $pm = $cfg->get('COMBINATIONS_MATCHER');
        $this->persist = ($pm=='basic' || $pm=='pref');
    }
    
    public function _dtoIdStrFromRowId($row_id)
    {
        $dto_id = parent::_dtoIdStrFromRowId($row_id);
        if (!empty($dto_id)) return $dto_id;
        
        $r = Fs2psTools::dbRow('
            select
                min(pa.id_product) as product,
                GROUP_CONCAT(distinct at.id_attribute order by at.id_attribute_group) as size,
                GROUP_CONCAT(distinct ac.id_attribute order by ac.id_attribute_group) as colour
            from @DB_product_attribute pa
            inner join @DB_product_attribute_combination pac on pac.id_product_attribute=pa.id_product_attribute
            inner join @DB_product p on p.id_product=pa.id_product
            left join @DB_attribute at on at.id_attribute=pac.id_attribute and at.id_attribute_group in ('.$this->sizeAttributeGroupIdsInSql.')
            left join @DB_attribute ac on ac.id_attribute=pac.id_attribute and ac.id_attribute_group in ('.$this->colourAttributeGroupIdsInSql.')
            where pa.id_product_attribute='.$row_id.'
		');
        if (!$r) return null;
        
        $product = $this->productMatcher->dtoIdStrFromRowId($r['product']);
        $size = $this->sizeMatcher->dtoIdStrFromRowId($r['size']);
        $colour = $this->colourMatcher->dtoIdStrFromRowId($r['colour']);
        
        if (!($product && ($size || $colour))) return null;
        
        return $this->dtoIdToStr(array($product, $size, $colour));
    }
}


class Fs2psStockableDirectMatcher extends Fs2psDto2AbstractMatcher
{
    public function __construct($task, $entity) 
    {
        $this->task = $task;
        $es_combi = $entity == 'combinations'; // $entity: products ó combinations
        parent::__construct($entity, $es_combi? 'id_product' : 'id_product_attribute', array('cref'), FALSE, FALSE, FALSE);
        $this->suffix = $es_combi? '1' : '0';
    }

    public function _rowIdFromDtoId($dto_id) 
    {
        if (empty($dto_id)) return null;
        
        $row_id = (int)substr($dto_id, 0, -1);
        if (!$row_id) return null;
        
        if ($this->suffix!=substr($dto_id, -1)) return null;

        return $row_id? $row_id : null;
    }

    public function _dtoIdStrFromRowId($row_id) 
    {
        $dto_id = $this->dtoIdToStr($row_id);
        if (empty($dto_id)) return $dto_id;

        return $dto_id.$this->suffix;
    }

}


class Fs2psProductCombiMatcher extends Fs2psDto2AbstractMatcher
{
    public function __construct($task, $entity) 
    {
        $this->task = $task;
        // XXX Sólo se usará al extraer con Fs2psCombiasProdDownloadCatalogTask
        $this->es_combi = $entity===null? null: $entity == 'combinations'; // $entity: products ó combinations
        parent::__construct($entity, $this->es_combi? 'id_product' : 'id_product_attribute', array('cref'), FALSE, FALSE, FALSE);
    }

    // cfillol: Evitamos problemas con los _ en las referencias
    public function dtoIdToStr($dto_id) { return (string)$dto_id; }
    public function strToDtoId($str) { return $str; }

    public function _rowIdFromDtoId($dto_id) 
    {
        if (empty($dto_id)) return null;
        
        $parts = explode("_",$dto_id);
        if (count ($parts) > 1) {
            // Si es combi devolvemos la string despues de la barra baja
            $row_id = (int)$parts[1];
        }
        else {
            $row_id = (int)$dto_id;
        }

        return $row_id? $row_id : null;
    }

    public function _dtoIdStrFromRowId($row_id) 
    {
        if (empty($row_id)) return null;

        // XXX Sólo se usará al extraer con Fs2psCombiasProdDownloadCatalogTask
        if ($this->es_combi===null) {
            return strval($row_id);
        }
        
        $row_id = (int)$row_id;
        if ($this->es_combi) {
            $id_parent = Fs2psTools::dbValue('
                select id_product from @DB_product_attribute where id_product_attribute='.$row_id.'
            ');
            if ($id_parent) {
                $dto_id = $id_parent."_".$row_id;
            } else {
                // No se ha encontrado el producto padre de la combinación
                return null;
            }
        } else {
            $dto_id = strval($row_id);
        }

        return $dto_id;
    }

    public function updateReverseMatch($dto_id, $row_id) { }
}


class Fs2psOrderMatcher extends Fs2psDto2RowDirectMatcher
{
    public function __construct($task, $entity) {
        parent::__construct($entity, 'orders', array('ref'));
    }
}

class Fs2psProductImagesMatcher extends Fs2psDto2RowDirectMatcher
{
    public function __construct($task, $entity) {
        parent::__construct($entity, 'product_images', array('id_image'));
    }
}

class Fs2psCustomerUpdateByEmailMatcher extends Fs2psDto2RowMatcher
{
    
    public function __construct($task, $entity) {
        parent::__construct($entity, 'customer', 'id_customer', array('ref'), true);
        $this->reloadCfg($task->cfg);
    }
    
    /**
     * Sólo debe usarse esta función en caso de que dto tenga suficiente información
     * como en el momento de la creación/actualización.
     *
     * Si existe un producto con matching por email -> matching (si hay varios se cogerá el primero)
     * En caso contrario se intenta el matching básico por ref (cod FS)
     */
    public function rowIdFromDto($dto)
    {
        if (!empty($dto['email']))
        {
            $dto_pref_sql = Fs2psTools::dbEscape($dto['email']);
            $row_id = (int)Fs2psTools::dbValue('
    			SELECT '.$this->row_id_field.'
    			FROM `@DB_'.$this->table.'`
    			WHERE `email` = \''.$dto_pref_sql.'\'
                ORDER BY id_customer
                LIMIT 1
    		');
            if ($row_id) return $row_id;
        }
        
        return $this->rowIdFromDtoId($this->dtoId($dto));
    }
    
}

class Fs2psAddressUpdateByNifMatcher extends Fs2psDto2RowMatcher
{
    protected $task;
    public function __construct($task, $entity) {
        $this->task = $task;
        parent::__construct($entity, 'address', 'id_address', array('ref'), true);
        $this->reloadCfg($task->cfg);
    }
    
    /**
     * Sólo debe usarse esta función en caso de que dto tenga suficiente información
     * como en el momento de la creación/actualización.
     *
     * Primero se intenta el matching básico por ref (cod FS)
     * y si no tiene éxito se busca una única dirección con matching por nif que no esté eliminada
     * ni mapeada.
     */
    public function rowIdFromDto($dto)
    {
        
        $row_id = $this->rowIdFromDtoId($this->dtoId($dto));
        
        if (empty($row_id) && $this->task && !empty($dto['nif']) && !empty($dto['customer']))
        {
            $id_customer = $this->task->getUpdater('customers')->matcher->rowIdFromDtoId($dto['customer']);
            if ($id_customer) {
                $nif_sql = Fs2psTools::dbEscape($dto['nif']);
                $row = Fs2psTools::dbRow('
                    SELECT count(CASE WHEN a.deleted=0 THEN 1 END) as not_deleted, count(m.row_id) as mapped, min(CASE WHEN a.deleted=0 THEN a.id_address END) as id_address 
                    FROM `@DB_address` a
                    left join `@DB_fs2ps_match` m on m.`table`=\'address\' and m.entity=\'customer_addresses\' and m.row_id=a.id_address
                    WHERE a.dni=\''.$nif_sql.'\' and a.id_customer='.$id_customer.' and a.deleted=0
        		');
                if (empty($row['mapped']) && $row['not_deleted']==1 && !empty($row['id_address'])) $row_id = $row['id_address'];
            }
        }
        
        return $row_id;
    }
    
}

