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

class Fs2psObjectModel
{
	public static function idForTable($table) {
	    if ($table=='orders') return 'id_order'; 
		return 'id_'.$table;
	}
}

class Fs2psObjectModelCommons {
    
    public static function formatFields($instance, $type, $id_lang = null)
    {
        $fields = array();
        
        $def = $instance->getDef();
        $cls = get_class($instance);
        $update_fields = $instance->getUpdateFields();
        
        // Set primary key in fields
        if (isset($instance->id)) $fields[$def['primary']] = $instance->id;
        
        foreach ($def['fields'] as $field => $data)
        {
            // Only get fields we need for the type
            // E.g. if only lang fields are filtered, ignore fields without lang => true
            if (($type == $cls::FORMAT_LANG && empty($data['lang']))
                || ($type == $cls::FORMAT_SHOP && empty($data['shop']))
                || ($type == $cls::FORMAT_COMMON && (!empty($data['shop']) || !empty($data['lang']))))
                continue;
                
            if (is_array($update_fields))
                if ((!empty($data['lang']) || !empty($data['shop'])) && (empty($update_fields[$field]) || ($type == $cls::FORMAT_LANG && empty($update_fields[$field][$id_lang]))))
                    continue;
                    
            // Get field value, if value is multilang and field is empty, use value from default lang
            $value = $instance->$field;
            if ($type == $cls::FORMAT_LANG && $id_lang && is_array($value))
            {
                if (!empty($value[$id_lang])) $value = $value[$id_lang];
                else $value = !empty($data['required'])? $value[Configuration::get('PS_LANG_DEFAULT')] : '';
                //else $value = !empty($data['required']) && isset($value[$default_id_lang])? $value[$default_id_lang] : ''; // cfillol: Posible solución para IF-2138?
            }
            
            // Format field value without purifying
            $fields[$field] = ObjectModel::formatValue($value, $data['type'], false, false);
        }
        
        return $fields;
    }
    
    public static function getFieldsLang($instance)
    {
        $cls = get_class($instance);
        
        // Retrocompatibility
        if (method_exists($instance, 'getTranslationsFieldsChild'))
            return $instance->getTranslationsFieldsChild();
            
            //$instance->validateFieldsLang(); 
            
            $is_lang_multishop = $instance->isLangMultishop();
            
            $fields = array();
            $id_lang = $instance->getIdLang();
            $id_shop = $instance->getIdShop();
            if ($id_lang === null)
                foreach (Language::getLanguages(false) as $language)
                {
                    $fields[$language['id_lang']] = $instance->formatFields($cls::FORMAT_LANG, $language['id_lang']);
                    $fields[$language['id_lang']]['id_lang'] = $language['id_lang'];
                    if ($id_shop && $is_lang_multishop)
                        $fields[$language['id_lang']]['id_shop'] = (int)$id_shop;
                }
            else
            {
                $fields = array($id_lang => $instance->formatFields($cls::FORMAT_LANG, $id_lang));
                $fields[$id_lang]['id_lang'] = $id_lang;
                if ($id_shop && $is_lang_multishop)
                    $fields[$id_lang]['id_shop'] = (int)$id_shop;
            }
            
            return $fields;
    }
    
}

class Fs2psProduct extends Product
{
    
    public static function getRootCategoryIdByShop($id_shop_default=1) {
        return Fs2psTools::dbValue('
            SELECT id_category FROM @DB_category
            WHERE is_root_category=1 and id_shop_default='.(int)$id_shop_default.'
        ');
    }
    
    public static function getRootCategoryByProduct($id_product) {
        return Fs2psTools::dbValue('
            SELECT c.id_category 
            FROM @DB_product p
            inner join @DB_category c on c.id_shop_default=p.id_shop_default and c.is_root_category=1
            WHERE p.id_product='.(int)$id_product.'
        ');
    }
    
    public function getDef() { return $this->def; }
    public function getUpdateFields() { return $this->update_fields; }
    public function getIdLang() { return $this->id_lang; }
    public function getIdShop() { return $this->id_shop; }
    public function formatFields($type, $id_lang = null) { return Fs2psObjectModelCommons::formatFields($this, $type, $id_lang); }
    public function getFieldsLang() { return Fs2psObjectModelCommons::getFieldsLang($this); }
}

class Fs2psImage extends Image
{
    public function deleteNoOrder()
    {
        if (! ObjectModel::delete())
            return false;

        if ($this->hasMultishopEntries())
            return true;

        if (! $this->deleteProductAttributeImage() || ! $this->deleteImage())
            return false;

        return true;
    }
}

class Fs2psCategory extends Category
{
	public function add($autodate = true, $null_values = false)
	{
		$this->_adding = true;
		parent::add($autodate, $null_values);
	}
	
	public function update($null_values = false)
	{
		$this->_adding = false;
		parent::update($null_values);
	}
	
	public function updateGroup($list)
	{
		if ($this->_adding)
			parent::updateGroup($list);
	}
	
}

class Fs2psManufacturer extends Manufacturer
{
    public function getDef() { return $this->def; }
    public function getUpdateFields() { return $this->update_fields; }
    public function getIdLang() { return $this->id_lang; }
    public function getIdShop() { return $this->id_shop; }
    public function formatFields($type, $id_lang = null) { return Fs2psObjectModelCommons::formatFields($this, $type, $id_lang); }
    public function getFieldsLang() { return Fs2psObjectModelCommons::getFieldsLang($this); }
}

class Fs2psSupplier extends Supplier
{
    // TODO: Optimizar como Fs2psManufacturer?
}

class Fs2psGroup extends Group
{
}

class Fs2psAttributeGroup extends AttributeGroup
{
}

if (class_exists("ProductAttribute")) {
    // Compatibilidad con 81
    class Fs2psAttribute extends ProductAttribute
    {
    }
} else {
    class Fs2psAttribute extends Attribute
    {
    }
}


class Fs2psCombination extends Combination
{
    public function setAttributes($ids_attribute)
    {
        // Optimización para evitar borrar y recrear en caso de coincidencia
        // Con esto se evita también la eliminación de carritos
        if (!empty($ids_attribute)) {
            $new_ids_attribute_str = implode(',', $ids_attribute);
            $old_ids_attribute_str = Fs2psTools::dbValue('
    			SELECT GROUP_CONCAT(id_attribute order by id_attribute)
                FROM `@DB_product_attribute_combination`
    			WHERE id_product_attribute='.(int)$this->id.'
            ');
            if ($new_ids_attribute_str==$old_ids_attribute_str) return;
        }
        
        parent::setAttributes($ids_attribute);
    }
}

class Fs2psFeature extends Feature
{
}

class Fs2psFeatureValue extends FeatureValue
{
}

class Fs2psSpecificPrice extends SpecificPrice
{

    public static $ID_PRODUCT_ATTRIBUTE_ONLY_COMBIS = -1;
    
    public static function fs2psGetSpecificPrice($id_product, $id_product_attribute, $id_shop, $id_group, $id_customer, $from_quantity=1)
	{
		return Fs2psTools::dbRow('
				SELECT id_specific_price
				FROM `'._DB_PREFIX_.'specific_price`
				WHERE `id_product`='.(int)$id_product.'
				AND `id_product_attribute`='.(int)$id_product_attribute.'
				AND `id_shop`='.(int)$id_shop.'
				AND `id_currency`=0
				AND `id_country`=0
				AND `id_group`='.(int)$id_group.'
                AND `id_customer`='.(int)$id_customer.'
				AND `from` = \'0000-00-00 00:00:00\'
				AND	`to` = \'0000-00-00 00:00:00\'
				AND `from_quantity`='.(int)$from_quantity.'
				ORDER BY `id_product_attribute` DESC'
		);
	}
	
	public static function addOrUpdateProductPriceRate($price, $id_product, $id_product_attribute=0, $id_shop=0, $id_group=0, $id_customer=0, $reduction=0.0, $reduction_type='amount', $from_quantity=1)
	{
	    $oldSpecPrice = self::fs2psGetSpecificPrice($id_product, $id_product_attribute, $id_shop, $id_group, $id_customer, $from_quantity);
		$id_specific_price = $oldSpecPrice? (int)$oldSpecPrice['id_specific_price'] : null;
		$specPrice = new Fs2psSpecificPrice($id_specific_price);
		$specPrice->id_product = $id_product;
		$specPrice->id_product_attribute = $id_product_attribute;
		$specPrice->id_shop = $id_shop;
		$specPrice->id_currency = 0;
		$specPrice->id_country = 0;
		$specPrice->id_group = $id_group;
		$specPrice->id_customer = $id_customer;
		$specPrice->price = $price;
		$specPrice->from_quantity = $from_quantity;
		$specPrice->reduction = $reduction;
		$specPrice->reduction_type = $reduction_type;
		// reduction_tax existe a partir de la versión 1.6.0.11
		if (property_exists($specPrice, 'reduction_tax')) $specPrice->reduction_tax = 0;
		// TODO: Contemplar versiones anteriores a 1.6.0.11
		
		$specPrice->from = '0000-00-00 00:00:00';
		$specPrice->to = '0000-00-00 00:00:00';
		$specPrice->save();
	}
	
	public static function resetProductManagedPriceRates($id_product, $id_product_attribute=0, $id_shop=null, $id_group=null, $id_customer=0, $from_quantity=1)
	{
		$shop_where = $id_shop===null? '' : ' sp.id_shop='.(int)$id_shop.' AND ';
		$group_join = $id_group===null ? ' inner join `@DB_fs2ps_match` m on `table`=\'group\' and m.row_id=sp.id_group ' : '';
		$group_where = $id_group===null? '' : ' sp.id_group='.(int)$id_group.' AND ';
		$attribute_where = $id_product_attribute==self::$ID_PRODUCT_ATTRIBUTE_ONLY_COMBIS? ' sp.id_product_attribute>0 AND ' : ($id_product_attribute===null? '' : ' sp.id_product_attribute='.(int)$id_product_attribute.' AND ');
		$customer_where = $id_customer===null? '' : ' sp.id_customer='.(int)$id_customer.' AND ';
		$from_quantity_where = $from_quantity===null? '' : ' sp.from_quantity='.(int)$from_quantity.' AND ';
		Fs2psTools::dbExec('
		    DELETE sp
			FROM `@DB_specific_price` as sp
                '.$group_join.'
            WHERE
				'.$shop_where.'
				'.$group_where.'
            	sp.id_product='.(int)$id_product.' AND
                '.$customer_where.'
                '.$attribute_where.'
                '.$from_quantity_where.'
                sp.id_currency=0 AND sp.id_country=0 
		');
	}
	
	public static function resetProductManagedGroupPriceRates($conserve_shop_group_qty, $affected_shops, $allow_group0_deletion, $id_product=null, $id_product_attribute=null)
	{
		$where = array(
			'sp.id_currency=0 AND sp.id_country=0 AND sp.id_customer=0',
			'sp.id_shop in ('.implode(', ', $affected_shops).')',
		);
		if ($conserve_shop_group_qty) {
		    if (sizeof($conserve_shop_group_qty[0])==3) { 
		        // managing from_quantity
		        $where[] = '(sp.id_shop, sp.id_group, sp.from_quantity) not in ('.implode(', ', array_map(function ($e) { return '('.$e[0].', '.$e[1].', '.$e[2].')'; }, $conserve_shop_group_qty)).')';
		    } else {
		        // not managing from_quantity
		        $where[] = '(sp.id_shop, sp.id_group) not in ('.implode(', ', array_map(function ($e) { return '('.$e[0].', '.$e[1].')'; }, $conserve_shop_group_qty)).')';
                $where[] = 'sp.from_quantity=1';
		    }
		} else {
		    $where[] = 'sp.from_quantity=1';
		}
		if ($id_product!=null) $where[] = 'sp.id_product='.(int)$id_product;
		if ($id_product_attribute!=null) $where[] = 'sp.id_product_attribute='.(int)$id_product_attribute;
		if ($allow_group0_deletion!=null) $where[] = '((sp.id_group=0 and m.row_id is null) or (sp.id_group<>0 and m.row_id is not null))';
		
		Fs2psTools::dbExec('
		    DELETE sp
			FROM `@DB_specific_price` as sp
				'.($allow_group0_deletion? 'left' : 'inner').' join `@DB_fs2ps_match` m on `table`=\'group\' and m.row_id=sp.id_group
				'.($id_product==null? 'inner join `@DB_fs2ps_match` mp on mp.`table`=\'product\' and mp.row_id=sp.id_product and mp.uploaded=1' : '').'
            WHERE '.implode(' AND ', $where)
		);
	}
	
	public static function resetProductManagedCustomerPriceRates($conserve_customers_qty, $affected_shops, $id_product=null, $id_product_attribute=null)
	{
	    $where = array(
	        'sp.id_currency=0 AND sp.id_country=0',
	        'sp.id_shop in ('.implode(', ', $affected_shops).')'
	    );
	    
	    if ($conserve_customers_qty) {
	        if (is_array($conserve_customers_qty[0])) {
	            // managing from_quantity
	            $where[] = '(sp.id_customer, sp.from_quantity) not in ('.implode(', ', array_map(function ($e) { return '('.$e[0].', '.$e[1].')'; }, $conserve_customers_qty)).')';
	        } else {
	            // not managing from_quantity
	            $where[] = 'sp.id_customer not in ('.implode(', ', $conserve_customers_qty).')';
	            $where[] = 'sp.from_quantity=1';
	        }
	    }
	    if ($id_product!==null) $where[] = 'sp.id_product='.(int)$id_product;
	    if ($id_product_attribute!==null) $where[] = 'sp.id_product_attribute='.(int)$id_product_attribute;
	    
	    Fs2psTools::dbExec('
		    DELETE sp
			FROM `@DB_specific_price` as sp
				inner join `@DB_fs2ps_match` m on `table`=\'customer\' and m.row_id=sp.id_customer
				'.($id_product==null? 'inner join `@DB_fs2ps_match` mp on mp.`table`=\'product\' and mp.row_id=sp.id_product and mp.uploaded=1' : '').'
            WHERE '.implode(' AND ', $where)
	    );
	}
	
	public static function resetProductManagedFromQuantityPriceRates($from_quantities, $affected_shops, $id_product=null, $id_product_attribute=null)
	{
	    $where = array(
	        'sp.id_currency=0 AND sp.id_country=0',
	        'sp.id_shop in ('.implode(', ', $affected_shops).')'
	    );
	    if ($from_quantities) {
	        $where[] = 'sp.from_quantity not in ('.implode(', ', $from_quantities).')';
	    }
	    if ($id_product!=null) $where[] = 'sp.id_product='.(int)$id_product;
	    if ($id_product_attribute!=null) $where[] = 'sp.id_product_attribute='.(int)$id_product_attribute;
	    
	    Fs2psTools::dbExec('
		    DELETE sp
			FROM `@DB_specific_price` as sp
				'.($id_product==null? 'inner join `@DB_fs2ps_match` mp on mp.`table`=\'product\' and mp.row_id=sp.id_product and mp.uploaded=1' : '').'
            WHERE '.implode(' AND ', $where)
	    );
	}
	
}

class Fs2psCustomer extends Customer
{
    
    public static function resetFs2psGroups($idCustomer, $excluded_groups=null)
    {
        $where = array('cg.id_customer='.(int)$idCustomer);
        if (!empty($excluded_groups)) {
            $where[] = 'not cg.id_group in ('.implode(', ', $excluded_groups).')';
        }
        return Fs2psTools::dbExec('
            DELETE cg
            FROM `@DB_customer_group` cg
            INNER JOIN `@DB_fs2ps_match` m on m.entity=\'price_rates\' and m.row_id=cg.id_group
            WHERE '.implode(' AND ', $where)
        );
    }
    
    public static function getFs2psGroups($idCustomer)
    {
        return Fs2psTools::dbSelect('
			SELECT cg.id_group
            FROM `@DB_customer_group` cg
            INNER JOIN `@DB_fs2ps_match` m on m.entity=\'price_rates\' and m.row_id=cg.id_group
            WHERE cg.id_customer='.(int)$idCustomer.'
        ');
    }
    
}

class Fs2psAddress extends Address
{
    
}

class Fs2psOrder extends Order
{
    public function getFs2psInvoicesPdfsSubfolder()
    {
        $reference = $this->reference;
        $sf1 = substr($reference, 0, 2);
        $sf2 = substr($reference, 2, 2);
        return ($sf1? $sf1 : 'AA').'/'.($sf2? $sf2 : 'AA');
    }
    
    public static function setCurrentStateIfChanged($order, $id_order_state, $id_employee = 0)
    {
        $old_os = $order->getCurrentOrderState();
        if (empty($old_os) || $old_os->id!=$id_order_state) {
            // Sólo cambiamos el estado si es necesario
            $order->setCurrentState($id_order_state, $id_employee);
            return true;
        }
        return false;
    }
}

