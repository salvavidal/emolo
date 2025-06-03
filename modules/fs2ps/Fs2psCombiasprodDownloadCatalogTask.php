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

class Fs2psCombiasprodDownloadCatalogTask extends Fs2psExtractorTask
{
	public function __construct($mng, $cmd)
	{
		parent::__construct('download_catalog', $mng, $cmd);
	}
	
	public function preExecute()
	{
	    $cfg = $this->cfg;
	    if ($cfg->get('DOWNLOAD_SECTIONS', true)) $this->addExtractor('sections', 'Fs2psSectionExtractor');
	    if ($cfg->get('DOWNLOAD_FAMILIES', true)) $this->addExtractor('families', 'Fs2psFamilyExtractor');
        
        //Pediente de poner  if ($cfg->get('DOWNLOAD_X', false)) para gestionar estos extractores
        if ($cfg->get('DOWNLOAD_MANUFACTURERS', false)) $this->addExtractor('manufacturers', 'Fs2psManufacturerExtractor');
        if ($cfg->get('DOWNLOAD_SUPPLIERS', false)) $this->addExtractor('suppliers', 'Fs2psSupplierExtractor');

	    $this->addExtractor('products', 'Fs2psCombiasprodProductExtractor');
        if ($cfg->get('DOWNLOAD_SPECIFIC_PRICES', false)) $this->addExtractor('specific_prices', 'Fs2psCombiasprodSpecificPriceExtractor');
        if ($cfg->get('DOWNLOAD_PACK_ITEMS', false)) $this->addExtractor('pack_items', 'Fs2psCombiasprodPackItemExtractor');
        
	}
	
}

class Fs2psCombiasprodProductExtractor extends Fs2psProductExtractor
{
    protected $familyLevel;
    
    public function __construct($task, $name, $matcher=null)
    {
        parent::__construct($task, $name, $matcher? $matcher : new Fs2psCombiasprodRefMatcher($task, $name, array('ref')));
    }
    
    protected function reloadCfg() {
        parent::reloadCfg();

        $cfg = $this->task->cfg;
        $download_catalog = $cfg->get('DOWNLOAD_CATALOG', '');
        $download_products = $cfg->get('DOWNLOAD_PRODUCTS', 'true');
        if ($download_products === true) $download_products = 'true';
        if ($download_catalog) $download_products = $download_products.','.$download_catalog;
        
        $this->onlyactive = strpos($download_products, 'onlyactive') !== false;
        $this->nesku = strpos($download_products, 'nesku') !== false;
        $this->nepsku = strpos($download_products, 'nepsku') !== false;
        $this->neporsku = strpos($download_products, 'neporsku') !== false;
        $this->nocombi = strpos($download_products, 'nocombi') !== false;
        $this->products = array_filter(array_map('intval', preg_split("/ *, */", $download_products)));

        $this->download_longdescrip = $cfg->get('DOWNLOAD_LONGDESCRIP');
        $this->download_description_short = $cfg->get('DOWNLOAD_DESCRIPTION_SHORT');
        
        $familyExtractor = $this->task->getExtractor('families');
        $this->familyLevel = $familyExtractor? $familyExtractor->getLevelFromDownloadCfg($cfg->get('DOWNLOAD_FAMILIES', true)) : null;

        $this->manufacturerMatcher = $this->task->getExtractor('manufacturers')? $this->task->getExtractor('manufacturers')->matcher : null;
        $this->supplierMatcher = $this->task->getExtractor('suppliers')? $this->task->getExtractor('suppliers')->matcher : null;
    }
    
    protected function getProductsWhereCondition() {
        $where = array();
        if ($this->nesku) $where[] = 'p.pref>\'\'';
        if ($this->nepsku) $where[] = 'p.parent_pref>\'\'';
        if (!($this->nesku || $this->nepsku) && $this->neporsku) $where[] = '(p.pref>\'\' or p.parent_pref>\'\')';
        $where = empty($where)? '' : 'WHERE '.join(" and ", $where);
        return $where;
    }

    protected function getAfterDateWhereCondition()
    {
        $where = array();
        $where_condition = '';

        if (version_compare(_PS_VERSION_, '1.7.0.0') >= 0) $where[] = 'p.state>0';
        
        if (empty($this->products)) {
            if ($this->onlyactive) $where[] = 'p.active=1';
            
            if (!empty($this->task->cmd['after'])) {
                $after_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['after']));
                $where[] = 'p.date_upd>\''.$after_str.'\'';
            }
            
            if (!empty($this->task->cmd['created_after'])) {
                $created_after_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['created_after']));
                $where[] = 'p.date_add>\''.$created_after_str.'\'';
            }
            
            if (!empty($this->task->cmd['until'])) {
                $until_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['until']));
                $where[] = 'p.date_upd<=\''.$until_str.'\'';
            }
        } else {
            $where[] = 'p.id_product in ('.implode(',', $this->products).')';
        }
            
        if ($this->nocombi) $where[] = 'pa.id_product is null';
        if (!empty($where)) $where_condition = 'where ';
        
        return $where_condition.join(" and ", $where);
    }
    
    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');        

        return '
            select * from (
                select
                    IF(pa.id_product_attribute is null, p.id_product*10, pa.id_product_attribute*10 + 1) as id,
                    p.id_product as product_id,
                    pa.id_product_attribute as product_attribute_id,

                    min(p.date_add) as date_add,
                    min(p.date_upd) as date_upd,
                    IF(pa.id_product_attribute is null, false, true) as iscombi,
                    IF(pa.id_product_attribute is null, min(pl.name), concat(min(pl.name), \' \', group_concat(atl.name ORDER BY atl.id_attribute ASC SEPARATOR \' \' ))) as name,
                    min(p.active) as active,

					'.($this->download_longdescrip? 'min(pl.description)' : '\'\'').' as longdescrip, 
                    '.($this->download_description_short?  'min(pl.description_short)' : '\'\'').' as descrip,

                    IFNULL(min(pa.price) + min(p.price), min(p.price)) as price,
                    IF(min(pa.wholesale_price) is null or min(pa.wholesale_price)=0, min(ps.wholesale_price), min(pa.wholesale_price)) as wholesale_price,
                    IFNULL(min(pa.reference), min(p.reference)) as pref,
                    min(p.reference) as parent_pref,
                    IF(pa.id_product_attribute is null, min(sp.quantity), min(spa.quantity)) as stock,
                    min(tax.rate) as tax_class,
                    IFNULL(min(pa.weight) + min(p.weight), min(p.weight)) as weight,
                    IFNULL(min(pa.ean13), min(p.ean13)) as ean13,
                    IFNULL(min(pa.upc), min(p.upc)) as upc,
                    IFNULL(min(pa.minimal_quantity), min(p.minimal_quantity)) as minqty,
                    id_manufacturer,
                    id_supplier,
                    min(p.id_category_default) as family
                from @DB_product p
                inner join @DB_product_shop ps on ps.id_product=p.id_product and ps.id_shop=1 -- Evitamos problemas multitienda?
                inner join @DB_product_lang pl on pl.id_product=p.id_product and pl.id_shop=1 and pl.id_lang='.$id_default_lang.'
                left join (
                    select max(t.rate) as rate, tr.id_tax_rules_group
                    from @DB_country c
                    left join @DB_tax_rule tr on tr.id_country=c.id_country
                    left join @DB_tax t on t.id_tax=tr.id_tax
                    where c.iso_code=\'ES\'
                    group by tr.id_tax_rules_group
                ) as tax on tax.id_tax_rules_group=p.id_tax_rules_group
                left join @DB_stock_available sp on sp.id_product=p.id_product and sp.id_product_attribute=0 and sp.id_shop=1

                left join @DB_product_attribute pa on pa.id_product=p.id_product
                left join @DB_product_attribute_combination pac on pac.id_product_attribute=pa.id_product_attribute
                left join @DB_stock_available spa on spa.id_product=p.id_product and spa.id_product_attribute=pa.id_product_attribute and spa.id_shop=1

                left join @DB_attribute at on at.id_attribute=pac.id_attribute
                left join @DB_attribute_lang atl on atl.id_attribute=at.id_attribute and atl.id_lang='.$id_default_lang.'
    
                '.$this->getAfterDateWhereCondition().'
    
                group by p.id_product,pa.id_product_attribute
            ) as p
            '.$this->getProductsWhereCondition().'
			ORDER BY p.id
		';
    }
    
    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        if(empty($dto)) return $dto;
        
        $matcher = $this->matcher;
        $row_id = $row['id'];
        $product_id = $row['product_id'];
        
        if ($this->familyMatcher) {
            if ($this->familyLevel!==null) {
                $level = $this->familyLevel + 2; // 0=root, 1=inicio
                $id_family = Fs2psTools::dbValue('
                    select min(c.id_category)
                    from @DB_category c
                    inner join @DB_category_product pc on pc.id_category=c.id_category
                    where c.id_shop_default=1 and c.level_depth='.$level.' and pc.id_product='.$product_id.'
                ');
            } else {
                $id_family = Fs2psTools::dbValue('
                    select id_family from (
                        select c.id_category as id_family, if(p.id_category_default=c.id_category,0,1) as preference
                        from @DB_category_product cp
                        inner join @DB_product p on p.id_product=cp.id_product
                        inner join @DB_category c on c.id_category=cp.id_category
                        left join @DB_category cc on cc.id_parent=c.id_category
                        left join @DB_category pc on pc.id_category=c.id_parent and pc.is_root_category=0
                        where cc.id_category is null and cp.id_product='.$product_id.'
                        order by preference,id_family
                        limit 1
                    ) s'
                );
            }
            $dto['family'] = empty($id_family)? '' : $this->familyMatcher->dtoIdStrFromRowId($id_family);
        }

        if (!empty($row['parent_pref'])) $dto['parent_pref'] = $row['parent_pref'];
               
        if ($this->matcher->direct) {
            $dto['pref'] = $matcher->referenceFromRowId($row_id);
        }

        if ($this->supplierMatcher) {
            $dto['supplier'] = empty($row['id_supplier'])? '' : $this->supplierMatcher->dtoIdStrFromRowId($row['id_supplier']);
        }
        if ($this->manufacturerMatcher) {
            $dto['manufacturer'] = empty($row['id_manufacturer'])? '' : $this->manufacturerMatcher->dtoIdStrFromRowId($row['id_manufacturer']);
        }

        if (!empty($row['minqty'])) $dto['minqty'] = $row['minqty'];

        if (!$this->download_description_short && isset($dto['descrip'])) unset($dto['descrip']);
        if (!$this->download_longdescrip && isset($dto['longdescrip'])) unset($dto['longdescrip']);
        
        if (array_key_exists('pref',$dto) && $dto['pref']===null) unset($dto['pref']); // Evitamos dto['pref']==null

        return $dto;
    }
}

class Fs2psCombiasprodRefMatcher extends Fs2psDto2AbstractMatcher // Fs2psDto2RowRefMatcher
{
    
    protected $gencombiref;
    protected $setref;
    protected $attributes_name_replacex;
    protected $attributes_name_patterns;
    
    public function __construct($task, $entity, $dto_id_fields)
    {
        $this->task = $task;
        parent::__construct($entity, 'id', $dto_id_fields, FALSE, FALSE, FALSE);
        $this->post_type = array('product', 'product_variation');
        $this->id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        $this->reloadCfg($task->cfg);
    }
    
    public function reloadCfg($cfg) {
        parent::reloadCfg($cfg);
        
        $download_catalog = $cfg->get('DOWNLOAD_CATALOG', '');
        $this->gencombiref = strpos($download_catalog, 'gencombiref') !== false;
        $this->forcegencombiref = strpos($download_catalog, 'forcegencombiref') !== false;
        $this->direct = strpos($download_catalog, 'direct') !== false;
        $this->persist = strpos($download_catalog, 'persist') !== false;
        $this->setref = strpos($download_catalog, 'setref') !== false;
        $this->refsep = $cfg->get('DOWNLOAD_CATALOG_REFSEP', '_');
        
        $this->attributes_name_replacex = $cfg->get('IMATCH_ATTRIBUTES_NAME_REPLACEX', array());
        $this->attributes_name_patterns = $cfg->get('IMATCH_ATTRIBUTES_NAME_PATTERNS', array());
    }
    
    public function realRowId($row_id) {
        return substr($row_id, 0, -1);
    }
    
    public function tableForRowId($row_id) {
        return substr($row_id, -1, 1)? 'product_attribute' : 'product';
    }
    
    public function entityForRowId($row_id) {
        return substr($row_id, -1, 1)? 'combinations' : 'products';
    }
    
    // Ignoramos _ como separador de $dto_id compuestos
    public function dtoIdToStr($dto_id) { return (string)$dto_id; }
    public function strToDtoId($str)  {
        if (empty($str) && $str!=='0') return null; // empty de string '0' se evalúa a true!!!??
        return $str;
    }
    
    public function deduceDtoIdStrFromName($name)
    {
        if (empty($name)) return null;
        foreach ($this->attributes_name_replacex as $search => $replacex) {
            $name = preg_replace($search, $replacex, $name);
        }
        if (empty($name)) return null;
        
        $matches = null;
        foreach ($this->attributes_name_patterns as $pattern) {
            preg_match($pattern, $name, $matches);
            if ($matches) {
                $dto_id = strtoupper(implode('', array_slice($matches, 1)));
                break;
            }
        }
        
        if (empty($dto_id)) {
            $msg = 'No se pudo deducir el dto_id para '.$name;
            throw new Fs2psException($msg);
        }
        
        return $dto_id;
    }
    
    protected function getPostTypeAndSku($row_id) {
        $table = $this->tableForRowId($row_id);
        $row = Fs2psTools::dbRow('
			select reference as sku
            from @DB_'.$table.' p
            where p.id_'.$table.'='.$this->realRowId($row_id).' 
		');
        if (empty($row)) return null;
        $row['post_type'] = $table=='product'? 'product' : 'product_variation';
        return $row;
    }
    
    protected $referenceByRowId = array();
    
    public function referenceFromRowId($row_id)
    {
        if (isset($this->referenceByRowId[$row_id])) return $this->referenceByRowId[$row_id];
        $ref = $this->_referenceFromRowId($row_id);
        $this->referenceByRowId[$row_id] = $ref;
        return $ref;
    }
    
    protected function _referenceFromRowId($row_id)
    {
        $post = $this->forcegencombiref? $this->getPostTypeAndSku($row_id) : null;
        
        if (!$this->forcegencombiref || $post['post_type']!='product_variation') {
            if (empty($post)) $post = $this->getPostTypeAndSku($row_id);
            if (!$post) return null;
            if (!empty($post['sku'])) return $post['sku'];
        }
        
        $real_row_id = $this->realRowId($row_id);
        if ($this->gencombiref && $post['post_type']=='product_variation') {
            $sku_suffix = Fs2psTools::dbValue('
                select group_concat(atl.name ORDER BY atl.id_attribute ASC SEPARATOR \'||\' )))
                from @DB_product p
                left join @DB_product_attribute pa on pa.id_product=p.id_product
                left join @DB_product_attribute_combination pac on pac.id_product_attribute=pa.id_product_attribute
                left join @DB_stock_available sa on sa.id_product=p.id_product and ((pa.id_product_attribute is not null and sa.id_product_attribute=pa.id_product_attribute) or (pa.id_product_attribute is null and sa.id_product_attribute=0))
                left join @DB_attribute at on at.id_attribute=pac.id_attribute
                left join @DB_attribute_lang atl on atl.id_attribute=at.id_attribute and atl.id_lang='.$this->id_default_lang.'
                WHERE p.id_product='.$real_row_id.'
                GROUP BY p.id_product
    		');
            if (!$sku_suffix) return null;
            
            $parent_sku = Fs2psTools::dbValue('
                select p.reference
                from @DB_product_attribute pa
                inner join @DB_product p on p.id_product=pa.id_product
                WHERE pa.id_product_attribute='.$real_row_id.'
            ');
            if (!$parent_sku) return null;
            
            if (!empty($parent_sku) && !empty($sku_suffix)) {
                $names = preg_split("/\|\|/", $sku_suffix);
                $dto_id_parts = array($parent_sku);
                foreach ($names as $name) $dto_id_parts[] = $this->deduceDtoIdStrFromName($name);
                return implode($this->refsep, $dto_id_parts);
            }
        }
    }  
    
    public function _dtoIdStrFromRowId($row_id)
    {
        if ($this->direct) return $this->dtoIdToStr($row_id);       
        return $this->referenceFromRowId($row_id);
    }
    
    public function updateReverseMatch($dto_id, $row_id)
    {
        $table = $this->tableForRowId($row_id);
        $real_row_id = $this->realRowId($row_id);
        if ($this->persist) {
            $entity = $this->entityForRowId($row_id);
            parent::_updateReverseMatch($dto_id, $real_row_id, $table, $entity);
        }
        if ($this->setref) {
            #$ref = $this->referenceFromRowId($row_id);
            Fs2psTools::dbUpdate($table, array('reference'=>$dto_id), array('id_'.$table=>$real_row_id));
        }
    }
}

class Fs2psCombiasprodSpecificPriceExtractor extends Fs2psCombiasprodProductExtractor
{
    public function __construct($task, $name)
    {
        //Fs2psExtractors no te matcher pero Fs2psCombiasprodProductExtractor si, per aixo dona error...
        parent::__construct($task, $name);
        $this->familyMatcher = null;
    }

    protected function buildSql()
    {
        return '
            select * 
            
            from (
                select
                    IF(pa.id_product_attribute is null, p.id_product*10, pa.id_product_attribute*10 + 1) as id,
                    p.id_product as product_id,
                    IF(spc.id_product_attribute is null, sp.price, spc.price) as price,
                    IF(spc.id_product_attribute is null, sp.from_quantity, spc.from_quantity) as quantity,
                    IF(spc.id_product_attribute is null, sp.reduction, spc.reduction) as reduction,
                    IF(spc.id_product_attribute is null, sp.reduction_type, spc.reduction_type) as reduction_type,
                    IFNULL(IFNULL(spc1.price, sp1.price), IFNULL(min(pa.price) + min(p.price), min(p.price))) as original_price,
                    IFNULL(min(pa.reference), min(p.reference)) as pref,
                    min(p.reference) as parent_pref
                from @DB_product p                     
                left join @DB_product_attribute pa on pa.id_product=p.id_product       
                left join @DB_specific_price sp1 on sp1.id_group=0 and sp1.id_product=p.id_product and sp1.id_product_attribute=0 and sp1.from_quantity=1 and sp1.price>0
                left join @DB_specific_price spc1 on spc1.id_group=0 and spc1.id_product=p.id_product and spc1.id_product_attribute=pa.id_product_attribute and spc1.from_quantity=1 and spc1.price>0
                left join @DB_specific_price sp on sp.id_group=0 and sp.id_product=p.id_product and sp.id_product_attribute=0
                left join @DB_specific_price spc on spc.id_group=0 and spc.id_product=p.id_product and spc.id_product_attribute=pa.id_product_attribute
                '.$this->getAfterDateWhereCondition().' 
                and (spc.id_product_attribute is not null or sp.id_product is not null) 
                group by p.id_product,pa.id_product_attribute, quantity
            ) as p
            '.$this->getProductsWhereCondition().'
			ORDER BY p.id,p.quantity
		';
    }
    
    protected function row2dto($row)
    {
        $dto = Fs2psMatchedExtractor::row2dto($row);
        if(empty($dto)) return $dto;

        # Cuando price=-1 significa que han marcado la casilla 'Mantener precio original'
        $dto['product'] = $row['id'];
        $dto['price'] = floatval($row['price']==-1 ? $row['original_price'] : $row['price']);
        $dto['original_price'] = floatval($row['original_price']);
        $dto['quantity'] = floatval($row['quantity']);

        if ($row['reduction_type'] == 'amount') {
            if ($row['original_price']>$row['price']) {
                if (!empty($row['reduction']) && floatval($row['reduction']) != 0) {
                    $dto['dis'] = floatval($row['reduction']);
                } else {
                    $dto['dis'] = floatval($row['original_price']) - floatval($row['price']);
                }
            } else{
                $dto['dis'] = 0.0;
            }
        } else {
            $dto['disp'] = floatval($row['reduction'] * 100);
        }
        
        return $dto;
    }

}

class Fs2psCombiasprodPackItemExtractor extends Fs2psCombiasprodProductExtractor
{
    protected function getProductsWhereCondition() {
        $where = array();
        if ($this->nesku || (!($this->nesku || $this->nepsku) && $this->neporsku) ) $where[] = 'p.pref>\'\'';
        $where = empty($where)? '' : 'WHERE '.join(" and ", $where);
        return $where;
    }

    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        
        // Combinaciones en packs disponible a partir de la ver. 1.6.2.x
        $product_attribute_in_pack = version_compare(_PS_VERSION_, '1.6.2.0') >= 0;
        
        return '
            select * from (
                select
                    pk.id_product_pack*10 as id,
                    pk.id_product_item*10 as item,
                    '.($product_attribute_in_pack? 'IF(pk.id_product_attribute_item, pk.id_product_attribute_item*10 + 1, 0)' : '0').' as combi_item, 
                    pk.quantity,
                    p.reference as pref,
                    IF('.($product_attribute_in_pack? 'pk.id_product_attribute_item' : '1=0').', concat(min(pil.name), \' \', group_concat(atl.name ORDER BY atl.id_attribute ASC SEPARATOR \' \' )), min(pil.name)) as item_descrip
                from @DB_pack pk
                inner join @DB_product p on p.id_product=pk.id_product_pack
                left join @DB_product_lang pil on pil.id_product=pk.id_product_item and pil.id_lang='.$id_default_lang.'

                left join @DB_product_attribute_combination pac on pac.id_product_attribute='.($product_attribute_in_pack? 'pk.id_product_attribute_item' : '-1').'
                left join @DB_attribute at on at.id_attribute=pac.id_attribute
                left join @DB_attribute_lang atl on atl.id_attribute=at.id_attribute and atl.id_lang='.$id_default_lang.'
                '.$this->getAfterDateWhereCondition().'
                group by pk.id_product_pack,pk.id_product_item'.($product_attribute_in_pack? ',pk.id_product_attribute_item' : '').'
            ) as p
            '.$this->getProductsWhereCondition().'
            ORDER BY p.id, p.item, p.combi_item
        ';
    }

    protected function row2dto($row)
    {       
        $item = $row['combi_item']? $row['combi_item'] : $row['item'];
        $item_dto_id = $this->matcher->dtoIdStrFromRowId($item);
        if (empty($item_dto_id)) {
            $this->task->log('ERROR: No se pudo obtener dto_id para item '.$item);
            return null;
        }
        return array(
            'pack' => $this->safeDtoIdStrFromRowId($row['id']),
            'item' => $item_dto_id,
            'quantity' => intval($row['quantity']),
            'item_descrip' => $row['item_descrip']
        );
    }
    
}