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

use PrestaShop\PrestaShop\Core\Product\ProductInterface;

include_once(dirname(__FILE__).'/Fs2psTools.php');
include_once(dirname(__FILE__).'/Fs2psUpdater.php');
include_once(dirname(__FILE__).'/Fs2psMatchers.php');
include_once(dirname(__FILE__).'/Fs2psObjectModels.php');

class Fs2psActivableContentUpdater extends Fs2psUpdater
{
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = array();
        
        if (isset($dto['active'])) $row['active'] = $dto['active']? 1 : 0;
        elseif ($this->enable) $row['active'] = 1;
        elseif (!$exists)  $row['active'] = 0;
            
        if (!$exists || !$this->noover_content)
        {
            $all_langs = !$exists;
            
            if (isset($dto['name']))
            {
                $name = $dto['name'];
                $row['name'] = Fs2psTools::multiLangField($name, $all_langs);
                if (!empty($name) && (!$exists || !$this->noover_url)) {
                    $row['link_rewrite'] = Fs2psTools::multiLangLinkRewrite($name, $all_langs);
                }
            }
        }
        return $row;
    }
}

class Fs2psCategoryUpdater extends Fs2psActivableContentUpdater
{
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'category');
    }
    
    protected function reloadCfg() {
        parent::reloadCfg();
        $cfg = $this->task->cfg;
        $this->noover_parent = $cfg->get('NOOVER_CATEGORIES_PARENT', false);
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = parent::dto2row($dto, $idx, $exists, $oldRowId);
        
        if (!$exists || !$this->noover_parent)
        {
            $id_parent = !empty($dto['parent'])? $this->matcher->rowIdFromDtoId($dto['parent']) : null;
            $row['id_parent'] =$id_parent? $id_parent : Configuration::get('PS_HOME_CATEGORY');
        }
        
        return $row;
    }
}

class Fs2psNoMultilangActivableContentUpdater extends Fs2psUpdater
{
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = array();
        
        if ($this->enable)
            $row['active'] = 1;
            elseif (!$exists)
            $row['active'] = 0;
            
            if (!$exists || !$this->noover_content)
            {
                if (isset($dto['name']))
                {
                    $name = $dto['name'];
                    $row['name'] =  $name;
                    if (!empty($name) && (!$exists || !$this->noover_url)) {
                        $row['link_rewrite'] = Fs2psTools::linkRewrite($name);
                    }
                }
            }
            return $row;
    }
}

class Fs2psManufacturerUpdater extends Fs2psNoMultilangActivableContentUpdater
{
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'manufacturer');
    }
}

class Fs2psSupplierUpdater extends Fs2psNoMultilangActivableContentUpdater
{
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'supplier');
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        $id = parent::insertOrUpdate($row, $exists, $oldRowId, $oldObj);
        
        $id_address = Address::getAddressIdBySupplierId($id);
        if ($id_address == false) {
            $address = new Address();
            $address->id_supplier = $id;
            $address->id_country = 6;
            $address->alias = 'supplier';
            $address->lastname = 'supplier';
            $address->firstname = 'supplier';
            $address->address1 = 'Prueba';
            $address->city = 'Pego';
            $address->dni = '123456789';
            $address->add();
        }

        return $id;
    }
}

class Fs2psGroupUpdater extends Fs2psUpdater
{
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = array();
        
        if (!$exists)
        {
            # Inicializamos sólo al crear
            $row['price_display_method'] = 1;
        }
        
        if (!$exists || !$this->noover_content)
        {
            $all_langs = !$exists;
            
            if (isset($dto['name']))
            {
                $name = $dto['name'];
                $row['name'] = Fs2psTools::multiLangField($name, $all_langs);
            }
        }
        return $row;
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        return parent::insertOrUpdate($row, $exists, $oldRowId, $oldObj);
    }
}

class Fs2psAttributeGroupUpdater extends Fs2psUpdater
{
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'attribute_group');
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = array(
            'is_color_group' => $dto['is_color_group'],
            'group_type' => $dto['is_color_group'] ? 'color' : 'select'
        );
        
        if (!$exists || !$this->noover_content)
        {
            $all_langs = !$exists;
            
            if (isset($dto['name'])) {
                $row['name'] = Fs2psTools::multiLangField($dto['name'], $all_langs);
            }
            if (isset($dto['public_name'])) {
                $row['public_name'] = Fs2psTools::multiLangField($dto['public_name'], $all_langs);
            }
                    
            $row['position'] = $idx;
        }
        return $row;
    }
}

class Fs2psAttributeUpdater extends Fs2psUpdater
{
    
    protected $group_row_id;
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'attribute');
    }
    
    public function getGroupRowId()
    {
        if (empty($this->group_row_id))
        {
            $dto_id = strtoupper($this->name);
            $attribute_group_upd = $this->task->getUpdater('attribute_groups');
            $this->group_row_id = $attribute_group_upd->matcher->rowIdFromDtoId($dto_id);
        }
        return $this->group_row_id;
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = array(
            'id_attribute_group' => $this->getGroupRowId(),
        );
        
        if (!$exists || !$this->noover_content)
        {
            $all_langs = !$exists;
            
            if (isset($dto['name']))
                $row['name'] = Fs2psTools::multiLangField($dto['name'], $all_langs);
                
                if (isset($dto['color'])) $row['color'] = !empty($dto['color']) ? $dto['color'] : null;
                if (!$exists) $row['position'] = $idx;
        }
        
        return $row;
    }
}


class Fs2psFeatureUpdater extends Fs2psUpdater
{
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name, 'feature');
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = array();
        
        if (!$exists || !$this->noover_content)
        {
            $all_langs = !$exists;
            
            if (isset($dto['name']))
                $row['name'] = Fs2psTools::multiLangField($dto['name'], $all_langs);
                
                if (!$exists) $row['position'] = $idx;
        }
        return $row;
    }
    
}


class Fs2psSKUUpdater extends Fs2psUpdater
{
    protected $price_rates_upd;
    protected $customer_upd;
    protected $default_shop_id;
    
    protected $shop_group_pairs = null;
    protected $affected_shops = null;
    protected $nogroup_specific_prices;
    protected $managing_price_by_group;
    protected $managing_price_by_customer;
    protected $managing_price_from_quantity;
    protected $same_group_price_rates;
    protected $price_only_in_product;
    protected $price_in_both;
    protected $update_stock_if_changed;
    protected $update_stockables_combis_can_be_products;
    protected $dont_force_combination_price_on_discount;
    protected $id_tax_rules_group_by_rate;
    
    protected $backout = array('deny'=> 0, 'allow'=> 1, 'notify'=> 1, 'config'=> 2); // notify añadido por compatibilidad con opciones woo
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        $this->price_rates_upd = $task->getUpdater('price_rates');
        $this->customer_upd = new Fs2psCustomerUpdater($task, 'customers');
        $this->default_shop_id = Shop::getContextShopID();
        
        $this->loadTaxRulesGroupByRate();
    }
    
    protected function reloadCfg() {
        parent::reloadCfg();
        $cfg = $this->task->cfg;
        $this->update_stock_if_changed = $cfg->get('UPDATE_STOCK_IF_CHANGED', FALSE);
        $this->nogroup_specific_prices = $cfg->get('NOGROUP_SPECIFIC_PRICES', False);
        $this->managing_price_by_group = $cfg->get('MANAGING_PRICE_BY_GROUP', True);
        $this->managing_price_by_customer = $cfg->get('MANAGING_PRICE_BY_CUSTOMER');
        $this->managing_price_from_quantity = $cfg->get('MANAGING_PRICE_FROM_QUANTITY');
        $this->same_group_price_rates = $cfg->get('SAME_GROUP_PRICE_RATES');
        $this->price_only_in_product = $cfg->get('PRICE_POLICY', '')=='onlyproduct';
        $this->price_in_both = $cfg->get('PRICE_POLICY', '')=='both';
        $this->update_stockables_combis_can_be_products = $cfg->get('UPDATE_STOCKABLES_COMBIS_CAN_BE_PRODUCTS', False);
        $this->dont_force_combination_price_on_discount = $cfg->get('DONT_FORCE_COMBINATION_PRICE_ON_DISCOUNT', False);
    }
    
    protected function loadTaxRulesGroupByRate()
    {
        $id_tax_rules = Fs2psTools::dbSelect('
			SELECT distinct tr.id_tax_rules_group, tr.id_tax, t.rate
			FROM @DB_tax_rule tr, @DB_tax_rules_group trg, @DB_tax t
			where
			    tr.id_tax=t.id_tax and
                trg.id_tax_rules_group=tr.id_tax_rules_group and
                trg.active=1 and tr.behavior=0
				'.(version_compare(_PS_VERSION_, '1.6.1.0') >= 0? ' and trg.deleted<>1 ' : '').'
		');
        $id_tax_rules_group_by_rate = array();
        foreach ($id_tax_rules as $rule)
        {
            $rate = (string)((float)$rule['rate']);
            $id_tax_rules_group_by_rate[$rate] = $rule['id_tax_rules_group'];
        }
        $this->id_tax_rules_group_by_rate = $id_tax_rules_group_by_rate;
    }
    
    protected function dto_price_id_group($price)
    {
        return isset($price['group'])? $this->price_rates_upd->matcher->rowIdFromDtoId($price['group']) : 0;
    }
    
    protected function dto_price_id_customer($price)
    {
        return isset($price['customer'])? $this->customer_upd->matcher->rowIdFromDtoId($price['customer']) : 0;
    }
    
    protected function pricesByShop($prices)
    {
        $prices_by_shop = array();
        foreach($prices as $price)
        {
            $id_shop = empty($price['shop'])? $this->default_shop_id : $price['shop'];
            if (!array_key_exists($id_shop, $prices_by_shop)) {
                $prices_by_shop[$id_shop] = array();
            }
            array_push($prices_by_shop[$id_shop], $price);
        }
        return $prices_by_shop;
    }

    protected function has_stock_changed($stock, $id_product, $id_product_attribute){
        $fs_stock = $stock;
        $web_stock = null;

        if($id_product_attribute){
            $web_stock = Fs2psTools::dbValue('select quantity from @DB_stock_available where id_product='.$id_product.' and id_product_attribute='.$id_product_attribute);
        }else{
            $web_stock = Fs2psTools::dbValue('select quantity from @DB_stock_available where id_product='.$id_product);
        }
        if ($fs_stock != $web_stock) return true;
        return false;
    }
    
    protected function setQuantity($id_product, $id_product_attribute, $quantity) {
        if ($this->update_stock_if_changed) {
            #Si la propiedad es true comprobamos si cambia el stock antes de actualizar
            if ($this->has_stock_changed($quantity, $id_product, $id_product_attribute)) {
                StockAvailable::setQuantity($id_product, $id_product_attribute, $quantity);
                return true;
            }
        } else {
            StockAvailable::setQuantity($id_product, $id_product_attribute, $quantity);
            return true;
        }
        return false;
    }
    protected function removeUnusedFromQuantityPriceRates($prices_by_shop, $id_product, $id_product_attribute=0) {
        $from_quantities = array();
        foreach($prices_by_shop as $prices) {
            foreach($prices as $price) {
                if (!empty($price['fromqty']) && empty($price['customer']) && empty($price['group'])) {
                    $from_quantities[] = $price['fromqty'];
                }
            }
        }
        Fs2psSpecificPrice::resetProductManagedFromQuantityPriceRates($from_quantities, $this->affected_shops, $id_product, $id_product_attribute);
    }
    
    protected function removeUnusedGroupPriceRates($prices_by_shop, $id_product, $id_product_attribute=0) {
        $conserve_shop_group_pairs = array();
        $shop_idx = 0;
        foreach($prices_by_shop as $id_shop=>$prices) {
            foreach($prices as $idx=>$price) {
                $es_precio_por_defecto_global = $shop_idx==0 && $idx==0;
                $reduction_field = isset($price['disp'])? 'disp' : 'dis';
                $reduction = empty($price[$reduction_field])? 0.0 : $price[$reduction_field]; // Siempre sin IVA
                $fromqty = !$this->managing_price_from_quantity || empty($price['fromqty'])? 1 : (int)$price['fromqty'];
                if (!empty($price['group']) && (!$es_precio_por_defecto_global || $reduction || $fromqty>1)) {
                    // Excluimos de $conserve_shop_group_pairs el precio por defecto global para eliminarlo como precio específico
                    $id_group = $this->dto_price_id_group($price);
                    if ($id_group) {
                        if ($this->managing_price_from_quantity) {
                            $conserve_shop_group_pairs[] = array($id_shop, $id_group, $fromqty);
                        } else {
                            $conserve_shop_group_pairs[] = array($id_shop, $id_group);
                        }
                    }
                }
            }
            $shop_idx++;
        }
        Fs2psSpecificPrice::resetProductManagedGroupPriceRates($conserve_shop_group_pairs, $this->affected_shops, $this->nogroup_specific_prices, $id_product, $id_product_attribute);
    }
    
    protected function removeUnusedCustomerPriceRates($prices_by_shop, $id_product, $id_product_attribute=0) {
        $conserve_customers = array();
        foreach($prices_by_shop as $prices) {
            foreach($prices as $price) {
                if (!empty($price['customer'])) {
                    $id_customer = $this->dto_price_id_customer($price);
                    if ($id_customer) {
                        if ($this->managing_price_from_quantity) {
                            $conserve_customers[] = array($id_customer, empty($price['fromqty'])? 1 : (int)$price['fromqty']);
                        } else {
                            $conserve_customers[] = $id_customer;
                        }
                    }
                }
            }
        }
        Fs2psSpecificPrice::resetProductManagedCustomerPriceRates($conserve_customers, $this->affected_shops, $id_product, $id_product_attribute);
    }
    
    protected function insertOrUpdatePrice($row, $id_product, $id_product_attribute=0)
    {
        if (!$this->price_only_in_product || !$id_product_attribute) { 
            
            if ($this->price_only_in_product) { // && !$id_product_attribute
                // Si somos un producto y sólo se indican precios a nivel de producto, eliminamos precios específicos de las combinaciones
                Fs2psSpecificPrice::resetProductManagedPriceRates($id_product, Fs2psSpecificPrice::$ID_PRODUCT_ATTRIBUTE_ONLY_COMBIS, null, null, null, null);
            }
            
            if ($this->nogroup_specific_prices) { // Nuevas versiones con soporte de tarifas por grupo y multitienda
                if (empty($row['_prices']))
                {
                    // No se indicaron precios específicos. El precio será el indicado en prod/combi. Eliminamos reglas.
                    // No es necesario eliminarlo aquí. Se eliminará en el onProcessEnd
                }
                else
                {
                    $prices_by_shop = $this->pricesByShop($row['_prices']);
                    if ($this->managing_price_from_quantity) {
                        $this->removeUnusedFromQuantityPriceRates($prices_by_shop, $id_product, $id_product_attribute);
                    }
                    if ($this->managing_price_by_customer) {
                        $this->removeUnusedCustomerPriceRates($prices_by_shop, $id_product, $id_product_attribute);
                    }
                    if ($this->managing_price_by_group && (!$this->same_group_price_rates || $this->managing_price_from_quantity)) {
                        $this->removeUnusedGroupPriceRates($prices_by_shop, $id_product, $id_product_attribute);
                    }
                    
                    $shop_idx = 0;
                    $es_multitienda = count($prices_by_shop)>1;
                    foreach($prices_by_shop as $id_shop=>$prices)
                    {
                        $es_tienda_por_defecto = $shop_idx==0;
                        foreach($prices as $idx=>$price)
                        {
                            if (!$this->managing_price_by_customer && !empty($price['customer'])) continue;
                            
                            if (!empty($price['group']) && !empty($price['customer'])) {
                                $msg = 'WARN: No puede indicar una tarifa que afecte a un grupo ('.$price['group'].') y a un cliente ('.$price['customer'].') a la vez (prod. "'.$row['_dto_id'].'")';
                                $this->task->log($msg);
                                continue;
                            }
                            
                            $id_group = $this->dto_price_id_group($price);
                            if (!empty($price['group']) && empty($id_group)) {
                                $msg = 'WARN: El grupo "'.$price['group'].'" referenciado por "'.$row['_dto_id'].'" no existe';
                                $this->task->log($msg);
                                continue;
                            }
                            
                            $id_customer = $this->dto_price_id_customer($price);
                            if (!empty($price['customer']) && empty($id_customer)) {
                                $msg = 'WARN: El cliente "'.$price['customer'].'" referenciado por "'.$row['_dto_id'].'" no existe';
                                $this->task->log($msg);
                                continue;
                            }
                            
                            $reduction_field = isset($price['disp'])? 'disp' : 'dis';
                            $reduction = empty($price[$reduction_field])? 0.0 : $price[$reduction_field]; // Siempre sin IVA
                            if ($reduction_field=='disp') $reduction = $reduction/100.0;
                            $saleprice = $price['price']; // Sin IVA
                            
                            $fromqty = !$this->managing_price_from_quantity || empty($price['fromqty'])? 1 : (int)$price['fromqty'];
                            
                            $es_precio_por_defecto_tienda = $idx==0 && ($shop_idx==0 || $id_shop>0) && empty($id_customer);
                            $es_precio_por_defecto_global = $shop_idx==0 && $idx==0 && empty($id_customer);
                            
                            // Si es la primera tarifa y no tiene descuento, no se indica, en caso contrario, sí
                            // Si no es primera tarifa, hay que crear o actualizar tenga o no descuento
                            if (!$es_precio_por_defecto_tienda || $reduction || $fromqty>1) {
                                // La primera tarifa asociada a la tienda será el PVP para esa tienda (id_group==0).
                                Fs2psSpecificPrice::addOrUpdateProductPriceRate(
                                    $reduction && ($es_precio_por_defecto_global || $this->dont_force_combination_price_on_discount)? -1 : $saleprice, 	// XXX: // Evitamos bug http://forge.prestashop.com/browse/PSCSX-8512
                                    $id_product,
                                    $id_product_attribute, 											// Con $id_product_attribute>0 evitamos q aparezca precio 0 en prod
                                    $es_multitienda || !$es_tienda_por_defecto? $id_shop : 0,
                                    $es_precio_por_defecto_tienda? 0 : $id_group,
                                    $id_customer,
                                    $reduction,
                                    $reduction_field=='disp'? 'percentage' : 'amount',
                                    $fromqty
                                );
                            } else if ($es_precio_por_defecto_tienda) { // && !$reduction && $fromqty<=1
                                // Si es la primera tarifa y no tiene descuento, hay que borrar el descuento que pudiera haber anteriormente
                                // Sólo lo hacemos en el caso del precio por defecto por temas de rendimiento. El resto lo haremos globalmente en el onProcessStart/onProcessEnd
                                Fs2psSpecificPrice::resetProductManagedPriceRates($id_product, $id_product_attribute, $es_multitienda? $id_shop : null, 0);
                            }
                        }
                        $shop_idx++;
                    }
                }
            } else { // Comportamiento por defecto, el de versiones antiguas < 2.2.0. Multitarifa sin multitienda ni rebajas ni precio por cliente ni fromqty
                if (!empty($row['_prices'])) {
                    foreach($row['_prices'] as $price)
                    {
                        $reduction_field = isset($price['disp'])? 'disp' : 'dis';
                        $reduction = empty($price[$reduction_field])? 0.0 : $price[$reduction_field]; // Siempre sin IVA
                        if ($reduction_field=='disp') $reduction = $reduction/100.0;
                        $saleprice = $price['price']; // Sin IVA
                        $id_group = $this->dto_price_id_group($price);
                        Fs2psSpecificPrice::addOrUpdateProductPriceRate(
                            $saleprice, $id_product, $id_product_attribute, 0, $id_group, 0, $reduction, $reduction_field=='disp'? 'percentage' : 'amount');
                    }
                } // else // Se eliminaran precios específicos del prod./combi. en el onProcessEnd
            }
        }
        
        if (isset($row['_backorders'])) {
            StockAvailable::setProductOutOfStock($id_product, $this->backout[$row['_backorders']], null, $id_product_attribute);
        }
    }
    
    protected function onProcessStart($dtos) {
        if (empty($dtos)) return;
        
        $nogroup_specific_prices = $this->nogroup_specific_prices; // Si true trabajaremos con grupo==0
        $prices = empty($dtos[0]['prices'])? array(array()) : $dtos[0]['prices'];
        $shop_group_pairs = array();
        $affected_shops = array(0=>1);
        $prices_by_shop = $this->pricesByShop($prices);
        $shop_idx = 0;
        foreach($prices_by_shop as $id_shop=>$prices) {
            $group_idx = 0;
            $affected_shops[$id_shop] = 1;
            foreach($prices as $price) {
                // Apuntamos las tarifas que se usan para después eliminar los precios específicos de las tarifas que ya no se usan
                // Los precios específicos para precios globales (tienda, grupo)==(0, 0) se eliminan individualmente para cada producto/combinación
                array_push($shop_group_pairs, array(
                    $shop_idx==0? 0 : $id_shop,
                    $nogroup_specific_prices && $group_idx==0? 0 : $this->dto_price_id_group($price)
                ));
                $group_idx++;
            }
            $shop_idx++;
        }
        $this->affected_shops = array_keys($affected_shops);
        $this->shop_group_pairs = $shop_group_pairs;
    }
    
    protected function onProcessEnd($dtos) {
        if ($this->managing_price_by_group && $this->affected_shops!==null && $this->same_group_price_rates && !$this->managing_price_from_quantity) {
            // Optimización para borrar las que ya no se usan de una tacada.
            // Sólo es posible si todos los artículos comparten los mismos  grupos de tarifas
            Fs2psSpecificPrice::resetProductManagedGroupPriceRates($this->shop_group_pairs, $this->affected_shops, $this->nogroup_specific_prices);
        }
    }
    
}


class Fs2psProductUpdater extends Fs2psSKUUpdater
{
    protected $categories_upd;
    protected $manufacturers_upd;
    protected $suppliers_upd;
    protected $features_upd;
    
    protected $noover_categories;
    protected $noover_manufacturer;
    protected $noover_suppliers;
    
    protected $noover_name;
    protected $noover_descrip;
    protected $noover_metadescrip;
    protected $noover_longdescrip;
    protected $noover_products_tags;
    
    protected $dims = array('width', 'height', 'depth', 'weight');

    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        
        $this->categories_upd = $task->getUpdater('categories');
        $this->manufacturers_upd = $task->getUpdater('manufacturers');
        $this->suppliers_upd = $task->getUpdater('suppliers');
        $this->features_upd = $task->getUpdater('features');
        $this->valid_condition_values = array('new', 'used', 'refurbished');
    }
    
    protected function reloadCfg() {
        parent::reloadCfg();
        $cfg = $this->task->cfg;
        $this->noover_categories = $cfg->get('NOOVER_PRODUCTS_CATEGORIES', false);
        $this->noover_manufacturer = $cfg->get('NOOVER_PRODUCTS_MANUFACTURER', false);
        $this->noover_suppliers = $cfg->get('NOOVER_PRODUCTS_SUPPLIERS', false);
        
        $this->noover_name = $this->noover_content || $cfg->get('NOOVER_PRODUCTS_NAME', false);
        $this->noover_descrip = $this->noover_content || $cfg->get('NOOVER_PRODUCTS_DESCRIP', false);
        $this->noover_longdescrip = $this->noover_content || $cfg->get('NOOVER_PRODUCTS_LONGDESCRIP', false);
        $this->noover_metadescrip = $cfg->get('NOOVER_PRODUCTS_METADESCRIP', false);
        $this->noover_products_tags = $cfg->get('NOOVER_PRODUCTS_TAGS', false);
        $this->noover_products_visibility = $cfg->get('NOOVER_PRODUCTS_VISIBILITY', false);
        $this->delete_prodascombi_combinations = $cfg->get('DELETE_PRODASCOMBI_COMBINATIONS', true);
        
        $this->set_seo_redirect = $cfg->get('PRODUCTS_SET_SEO_REDIRECT', false);
    }
    
    public static function deleteFeatures($id_product)
    {
        // Si fv.custom=1, distintos id_feature_value para distintos id_product.
        //   - Es seguro borrar fv y fvl por que no se va a interferir en otros productos.
        //   - fp lo borraremos en la siguiente sentencia
        // Si fv.custom=0, id_feature_value coincide para los distintos productos cuando se trata del mismo valor
        //   - Sólo debemos borrar feature_product (en la siguiente sentencia)
        Fs2psTools::dbExec('
			delete fv, fvl
            from @DB_fs2ps_match m
            inner join @DB_feature_product fp on fp.id_feature=m.row_id
            left join @DB_feature_value fv on fv.id_feature_value=fp.id_feature_value and fv.custom=1
            left join @DB_feature_value_lang fvl on fvl.id_feature_value=fv.id_feature_value
            where m.`table`=\'feature\' and fp.id_product='.(int)$id_product.'
		');
        Fs2psTools::dbExec('
			delete fp
            from @DB_fs2ps_match m
            inner join @DB_feature_product fp on fp.id_feature=m.row_id
            left join @DB_feature_value fv on fv.id_feature_value=fp.id_feature_value
            left join @DB_feature_value_lang fvl on fvl.id_feature_value=fv.id_feature_value
            where m.`table`=\'feature\' and fp.id_product='.(int)$id_product.'
		');
        
        /* XXX cfillol: Extraído de Product.deleteFeatures, pero es realmente necesario?
         SpecificPriceRule::applyAllRules(array((int)$this->id));
         */
    }
    
    public function createFeatureValue($id_feature, $custom, $value) {
        $id_feature_value = Fs2psTools::dbInsert('feature_value', array( 'id_feature' => $id_feature, 'custom' => $custom));
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            Fs2psTools::dbInsert('feature_value_lang', array('id_feature_value' => $id_feature_value, 'id_lang' => $language['id_lang'], 'value' => $value));
        }
        return $id_feature_value;
    }
    
    public function addFeatures($id_product, $features)
    {
        foreach ($features as $feature) {
            $values = is_array($feature['value'])? $feature['value'] : array($feature['value']);
            if ($feature['custom']) {
                foreach ($values as $value) {
                    $id_feature_value = $this->createFeatureValue($feature['id'], 1, $value);
                    Product::addFeatureProductImport($id_product, $feature['id'], $id_feature_value);
                }
            } else {
                foreach ($values as $value) {
                    $id_feature_value = Fs2psTools::dbValue('
                        select fvl.id_feature_value
                        from @DB_feature_value_lang fvl
                            inner join @DB_feature_value fv on fv.id_feature_value = fvl.id_feature_value
                        where fvl.id_lang='.(int)$this->id_default_lang.'
                            and fvl.value=\''.Fs2psTools::dbEscape($value).'\'
                            and fv.id_feature='.(int)$feature['id'].'
            		');
                    if (empty($id_feature_value)) {
                        $id_feature_value = $this->createFeatureValue($feature['id'], 0, $value);
                    }
                    Product::addFeatureProductImport($id_product, $feature['id'], $id_feature_value);
                }
            }
        }
    }
    
    protected function overrideValues($target, $values)
    {
        if (empty($target->link_rewrite) && empty($values['link_rewrite']) && !empty($values['name'])) {
            $values['link_rewrite'] = Fs2psTools::multiLangLinkRewrite($values['name'][$this->id_default_lang], true);
        }
        parent::overrideValues($target,$values);
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $rate = (string)($dto['iva'] * 100);
        if (empty($this->id_tax_rules_group_by_rate[$rate])) {
            throw new Fs2psException(
                'No se definió en la tienda un IVA del '.$rate.'% (prod. "'.$this->matcher->dtoIdToStrFromDto($dto).'")'
            );
        }
            
        $row = array(
            'wholesale_price' => isset($dto['cost']) ? $dto['cost'] : 0,
            'id_tax_rules_group' => ($this->id_tax_rules_group_by_rate[$rate]),
            '_dto_id' => $this->matcher->dtoId($dto),
            '_tax_rate' => $dto['iva']
        );

        if (isset($dto['stock'])) $row['_quantity'] = $dto['stock'];
        
        $reference = $this->matcher->referenceFromDto($dto);
        if ($reference!==null) $row['reference'] = $reference;
        
        // Prices. Multiple price rates or simple price.
        if (empty($dto['prices'])) {
            $row['price'] = isset($dto['price']) ? $dto['price'] : 0;
        } else {
            $row['_prices'] = $dto['prices'];
            $row['price'] = $dto['prices'][0]['price'];
        }
        
        if (!$exists || !$this->noover_products_visibility) {
            if ($this->disable === 'fast_hide') $row['visibility'] = 'both';
            // Si viene una visibilidad en el dto, la priorizamos sobre el comportamiento general.
            if (isset($dto['visibility'])) $row['visibility'] = $dto['visibility'];
        }
        
        if (isset($dto['active'])) $row['active'] = $dto['active']? 1 : 0;
        elseif ($this->enable) $row['active'] = 1;
        elseif (!$exists) $row['active'] = 0;
            
        if (!$exists || !$this->noover_content)
        {
            $all_langs = !$exists;
            
            if (isset($dto['name']) && (!$exists || !$this->noover_name))
            {
                $name = $dto['name'];
                $row['name'] = Fs2psTools::multiLangField($name, $all_langs, array('replace'=>array("/\r\n|\r|\n/", '')));
                if (!empty($name) && empty($dto['slug']) && (!$exists || !$this->noover_url)) {
                    $row['link_rewrite'] = Fs2psTools::multiLangLinkRewrite($name, $all_langs);
                }
                //'meta_title' => '', // name by default
            }
            
            if (isset($dto['descrip']) && (!$exists || !$this->noover_descrip))
            {
                $descrip = $dto['descrip'];
                $row['description_short'] = Fs2psTools::multiLangField($descrip, $all_langs);
                if (!isset($dto['metadescrip']))
                    $row['meta_description'] = Fs2psTools::multiLangField($descrip, $all_langs, array('max_length'=>255));  // TODO estos params no estaban bien: , true, 200)
            }
            
            if (isset($dto['longdescrip']) && (!$exists || !$this->noover_longdescrip)) {
                $row['description'] = Fs2psTools::multiLangField($dto['longdescrip'], $all_langs);
            }
            if (isset($dto['metatitle'])) {
                $row['meta_title'] = Fs2psTools::multiLangField($dto['metatitle'], $all_langs);
            }
            if (isset($dto['metadescrip']) && (!$exists || !$this->noover_metadescrip)) {
                $row['meta_description'] = Fs2psTools::multiLangField($dto['metadescrip'], $all_langs);
            }
            if (isset($dto['metakeys'])) {
                    $row['meta_keywords'] = Fs2psTools::multiLangField($dto['metakeys'], $all_langs);
            }
            if (!empty($dto['slug']) && (!$exists || !$this->noover_url)) {
                $row['link_rewrite'] = Fs2psTools::multiLangLinkRewrite($dto['slug'], $all_langs);;
            }
        }
        
        if (isset($dto['minqty'])) $row['minimal_quantity'] = $dto['minqty'];
        if (isset($dto['lowqty'])) $row['low_stock_threshold'] = $dto['lowqty'];
        if (isset($dto['on_sale'])) $row['on_sale'] = $dto['on_sale'];
        if (array_key_exists('available_date', $dto)) $row['available_date'] = $dto['available_date'];
        if (isset($dto['condition']) && in_array($dto['condition'], $this->valid_condition_values)) {
            $row['show_condition'] = 1;
            $row['condition'] = $dto['condition'];
        }
            
        
        foreach ($this->dims as $dim) {
            if (isset($dto[$dim])) $row[$dim] = $dto[$dim];
        }
        
        if (isset($dto['ean'])) $row['ean13'] = $dto['ean'];
        if (isset($dto['upc'])) $row['upc'] = $dto['upc'];
        if (isset($dto['mpn'])) $row['mpn'] = $dto['mpn'];
        if (isset($dto['ecotax'])) $row['ecotax'] = $dto['ecotax'];
        
        if (isset($dto['categories']))
        {
            if (sizeof($dto['categories'])>0) {
                $dto['categories'] = array_unique($dto['categories']);
            }
            if (!$exists || !$this->noover_categories) {
                $row['_categories'] =$this->categories_upd->refsToIds($dto['categories']);
            }
        }
        
        if (isset($dto['manufacturer']))
        {
            if (!$exists || !$this->noover_manufacturer)
                $row['id_manufacturer'] = $this->manufacturers_upd->matcher->rowIdFromDtoId($dto['manufacturer']);
        }
        
        if (isset($dto['suppliers']))
        {
            if (!$exists || !$this->noover_suppliers)
                $row['_suppliers'] = $this->suppliers_upd->refsToIds($dto['suppliers']);
        }
        
        
        if (isset($dto['features']))
        {
            $features = array();
            foreach ($dto['features'] as $ref => $vals)
            {
                $id_feature = $this->features_upd->matcher->rowIdFromDtoId($ref);
                if(empty($id_feature)) continue;
                
                $feature = array('id' => $id_feature);
                if (is_array($vals)) {
                    $feature['value'] = $vals['value'];
                    $feature['custom'] = isset($vals['custom'])? ($vals['custom']? 1 : 0) : 1;
                } else {
                    $feature['value'] = $vals;
                    $feature['custom'] = 1;
                }
                $features[] = $feature;
            }
            $row['_features'] = $features;
        }
        
        if (isset($dto['combinations'])) $row['_combinations'] = $dto['combinations'];
        if (isset($dto['sref'])) $row['supplier_reference'] = $dto['sref'];
        if (isset($dto['tags']) && (!$exists || !$this->noover_products_tags)) $row['_tags'] = $dto['tags'];
        if (isset($dto['favourite'])) $row['_favourite'] = $dto['favourite'];
        if (isset($dto['available_later'])) $row['available_later'] = Fs2psTools::multiLangField($dto['available_later'], true);
        if (isset($dto['available_now'])) $row['available_now'] = Fs2psTools::multiLangField($dto['available_now'], true);
        if (isset($dto['available_for_order'])) $row['available_for_order'] = $dto['available_for_order']? true : false;
        if (isset($dto['show_price'])) $row['show_price'] = $dto['show_price']? true : false;
        if (isset($dto['carriers'])) $row['_carriers'] = $dto['carriers'];
        if (isset($dto['is_pack'])) $row['cache_is_pack'] = $dto['is_pack'];
        if (isset($dto['backorders'])) $row['_backorders'] = $dto['backorders'];
        if (isset($dto['custom_fields'])) $row['_custom_fields'] = $dto['custom_fields'];
        if (isset($dto['unit_price'])) $row['unit_price'] = $dto['unit_price'];
        if (isset($dto['unity'])) $row['unity'] = $dto['unity'];
        
        
        if (((isset($dto['categories'])) && (sizeof($dto['categories'])>0))) {
            $family = $this->categories_upd->matcher->rowIdFromDtoId($dto['categories'][0]);
            if (!empty($family) && strpos($this->set_seo_redirect, 'family') !== false) {
                $row['id_type_redirected'] = $family;
                $row['redirect_type'] = strpos($this->set_seo_redirect, 'temporal') !== false ? ProductInterface::REDIRECT_TYPE_CATEGORY_FOUND : ProductInterface::REDIRECT_TYPE_CATEGORY_MOVED_PERMANENTLY;
            }
        }

        if (isset($dto['location'])) {
            $row['location'] = $dto['location'];
        }

        return $row;
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        $oldObj = new $this->object_model_cls($oldRowId);

        if (isset($row['unit_price']) && $row['unit_price']>0) {
            $row['unit_price_ratio'] = $row['price']/$row['unit_price'];
            $oldObj->unit_price_ratio = $row['unit_price_ratio'];
        }

        # Calculated fields
        if(property_exists($oldObj, 'unit_price_ratio')) 
            $oldObj->unit_price = ($oldObj->unit_price_ratio != 0 ? $row['price'] / $oldObj->unit_price_ratio : 0);

        if (isset($row['cache_is_pack']) ) {
            if (!$row['cache_is_pack'] && $oldObj->cache_is_pack) Pack::deleteItems($oldRowId);
        }
        
        $id = parent::insertOrUpdate($row, $exists, $oldRowId, $oldObj);
        
        if (isset($row['_quantity'])) 
            $this->setQuantity($id, null, $row['_quantity']);
        
        $this->insertOrUpdatePrice($row, $id);
        
        $product = null;
        if (isset($row['_categories'])) {
            $product = $product? $product : new Fs2psProduct($id);
            $old_categories = $product->getCategories();
            $categories = $this->categories_upd->keepNotManagedIds($row['_categories'], $old_categories);
            
            if (!empty($categories)) {
                $product->updateCategories($categories);
                #if (!in_array($product->id_category_default, $categories))
                if (!empty($row['_categories'])) {
                    if ($product->id_category_default!=$row['_categories'][0]) {
                        $product->id_category_default = $row['_categories'][0];
                        $product->update();
                    }
                }
            }
        }
        if (empty($categories) && !$this->noover_categories && !$exists)
        {
            $msg = 'WARN: El producto "'.$row['_dto_id'].'" no se asoció con ninguna categoría';
            $this->task->log($msg);
        }
        
        if (!empty($row['_suppliers']))
        {
            $product = $product? $product : new Fs2psProduct($id);
            $product->deleteFromSupplier();
            foreach ($row['_suppliers'] as $id_supplier)
                $product->addSupplierReference($id_supplier, 0, null, $row['wholesale_price']);
        }
        
        if (isset($row['_features']))
        {
            $this->deleteFeatures($id);
            if (!empty($row['_features'])) $this->addFeatures($id, $row['_features']);
        }
        
        if (isset($row['_tags']))
        {
            Tag::deleteTagsForProduct($id);
            if (!empty($row['_tags'])) Tag::addTags($this->id_default_lang, $id, $row['_tags']);
        }
        
        $index_search = $this->task->cfg->get('PRODUCTS_UPDATE_SEARCH_INDEX');
        if ($index_search && ($index_search=='all' || ($index_search=='onlynews' and !$exists))) {
            ObjectModel::updateMultishopTable('Product', array('indexed' => 0), 'product_shop.id_product='.$id);
            Search::indexation(false, $id);
        }
        
        if (isset($row['_favourite']))
        {
            $product = $product? $product : new Fs2psProduct($id);
            $rootCategory = Fs2psProduct::getRootCategoryByProduct($id);
            $row['_favourite']? $product->addToCategories($rootCategory) : $product->deleteCategory($rootCategory, false);
        }
        
        if (isset($row['_carriers']))
        {
            $product = $product? $product : new Fs2psProduct($id);
            $product->setCarriers($row['_carriers']);
        }

        if (isset($row['_custom_fields']))
        {
            Fs2psTools::dbUpdate('product', $row['_custom_fields'], array('id_product'=>$id));
        }

        if (isset($row['location'])) {
            StockAvailable::setLocation($id, $row['location']);
        }
        
        return $id;
    }
    
    protected function onInsertedOrUpdated($dto, $row, $inserted)
    {
        parent::onInsertedOrUpdated($dto, $row, $inserted);
        if (isset($row['_combinations'])) {
            $combinations_upd = $this->task->getUpdater('combinations');
            if (empty($row['_combinations']) && $this->delete_prodascombi_combinations == true) {
                $combinations_upd->removeNotPresentCombis($this->matcher->rowId($row), $row['_combinations'], true);
            } else {
                $combinations_upd->process($row['_combinations']);
            }
        }
    }
    
}


class Fs2psSizeColourCombinationUpdater extends Fs2psSKUUpdater
{
    
    protected $products_upd;
    protected $sizes_upd;
    protected $colours_upd;
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        
        $this->object_model_cls = 'Fs2psCombination';
        $this->products_upd = $task->getUpdater('products');
        $this->sizes_upd = $task->getUpdater('sizes');
        $this->colours_upd = $task->getUpdater('colours');
    }

    protected function reloadCfg() {
        parent::reloadCfg();
        $cfg = $this->task->cfg;
        $this->delete_prodascombi_combinations = $cfg->get('DELETE_PRODASCOMBI_COMBINATIONS', true);
    }
    
    protected function getSizeColDesc($dto)
    {
        $attr_types = array('size', 'colour');
        $sizecoldesc = 'REF: '.$dto['ref'].' - ';
        foreach ($attr_types as $attr_type)
        {
            if (!empty($dto[$attr_type]))
                $sizecoldesc = $sizecoldesc.$dto[$attr_type].'/';
        }
        $sizecoldesc = rtrim($sizecoldesc, "/");
        return $sizecoldesc;
    }
    
    protected function getIdsAttributeFromDto($dto)
    {
        $ids_attribute = array();
        $attr_types = array('size', 'colour');
        foreach ($attr_types as $attr_type)
        {
            if (isset($dto[$attr_type]) && (!empty($dto[$attr_type]) || $dto[$attr_type]==='0'))
            {
                $upd = $attr_type.'s_upd';
                $attr_id = (int)$this->$upd->matcher->rowIdFromDtoId($dto[$attr_type]);
                if ($attr_id) $ids_attribute[] = $attr_id;
                else throw new Fs2psException('Referencia a \''.$dto[$attr_type].'\' no valida: '.$attr_id);
            }
        }
        return $ids_attribute;
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $id_product = $this->products_upd->matcher->rowIdFromDtoId($dto['ref']);
        if (empty($id_product)) {
            throw new Fs2psException(
                'No existe el producto referenciado por la combinación: '.$this->getSizeColDesc($dto)
            );
        }
            
        $ids_attribute = $this->getIdsAttributeFromDto($dto);
        if (empty($ids_attribute)) {
            throw new Fs2psException(
                'La combinación no enlaza con ningún/a talla/color existente: '.$this->getSizeColDesc($dto)
            );
        }
            
        $row = array(
            'id_product' => $id_product,
            
            // Extra info to be used in insertOrUpdate
            '_ids_attribute' => $ids_attribute,
            //'_quantity' => isset($dto['stock']) ? $dto['stock'] : 0,
            '_tax_rate' => isset($dto['iva']) ? $dto['iva'] : 0,
        );

        if (isset($dto['stock'])) $row['_quantity'] = $dto['stock'];
        
        $reference = $this->matcher->referenceFromDto($dto);
        if ($reference!==null) $row['reference'] = $reference;
        
        if (isset($dto['ean'])) $row['ean13'] = $dto['ean'];
        if (isset($dto['minqty'])) $row['minimal_quantity'] = $dto['minqty'];
        if (isset($dto['iva'])) $row['_tax_rate'] = $dto['iva'];
        if (isset($dto['weight'])) $row['weight'] = $dto['weight'];
        if (isset($dto['mpn'])) $row['mpn'] = $dto['mpn'];
        if (isset($dto['ecotax'])) $row['ecotax'] = $dto['ecotax'];
        if (array_key_exists('cost', $dto)) $row['wholesale_price'] = $dto['cost'];
        
        if ($this->price_only_in_product) {
            $row['price'] = 0; // A 0 si el precio se gestiona a nivel de producto
        } else {
            // Prices. Multiple price rates or simple price.
            if (empty($dto['prices'])) {
                $row['price'] = isset($dto['price']) ? $dto['price'] : 0;
            } else {
                $row['_prices'] = $dto['prices'];
                $row['price'] = $dto['prices'][0]['price'];
            }
        }
        
        // Forzado de la combinación por defecto.
        // Inicialmente guardaremos default_on=0 para evitar errores del tipo 'Duplicate entry for key product_default' al actualizar.
        // Después estableceremos la combinacion por defecto en onGroupUpdated.
        if (isset($dto['default'])) {
            $row['default_on'] = 0; // Problems with Prestashop 1.5?
            $row['_default'] = $dto['default']? 1 : 0;
        }
        
        if (isset($dto['backorders'])) $row['_backorders'] = $dto['backorders'];

        if (isset($dto['available_later'])) $row['available_later'] = Fs2psTools::multiLangField($dto['available_later'], true);
        if (isset($dto['available_now'])) $row['available_now'] = Fs2psTools::multiLangField($dto['available_now'], true);
        if (array_key_exists('available_date', $dto)) $row['available_date'] = $dto['available_date'];

        if (isset($dto['location'])) {
            $row['location'] = $dto['location'];
        }
        
        return $row;
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        
        $id_product = $row['id_product'];
        
        if($this->price_in_both) {
            $product_price = Fs2psTools::dbValue('select price from @DB_product_shop where id_product='.$id_product.' and id_shop=1');
            $row['price'] = round($row['price'] - $product_price,6);
            if(!empty( $row['_prices'])){
                $row['_prices'][0]['price'] =  $row['price'];
            }
        }
        
        $id = parent::insertOrUpdate($row, $exists, $oldRowId);
        
        $combination = new Fs2psCombination($id);
        
        $ids_images = $combination->getWsImages();
        $combination->setAttributes($row['_ids_attribute']);
        if ($ids_images) {
            $ids_images = array_map(function($e) { return $e['id']; }, $ids_images);
            $combination->setImages($ids_images);
        }
        
        if (isset($row['_quantity'])) {
            //$this->setQuantity($id_product, null, null);
            $this->setQuantity($id_product, $id, $row['_quantity']);
        }

        if (isset($row['location'])) {
            StockAvailable::setLocation($id_product, $row['location'], null, $id);
        }
        
        $this->insertOrUpdatePrice($row, $id_product, $id);
        
        return $id;
    }
    
    public function removeNotPresentCombis($id_product, $combinations, $simulate_full_process=false)
    {
        
        // Remove not uploaded combinations
        $all_combis = Fs2psTools::dbSelect('
			SELECT pa.id_product_attribute
			FROM `@DB_product_attribute` pa
			WHERE pa.`id_product` = '.(int)$id_product.'
		');
        if (!empty($all_combis))
        {
            if ($simulate_full_process) {
                $this->resetCounters($combinations);
            }
            
            $matcher = $this->matcher;
            $updated_combis_ids = array();
            foreach ($combinations as $combi) {
                $updated_combis_ids[] = $matcher->rowId($combi);
            }
            $all_combis_ids = array();
            foreach ($all_combis as $combi) {
                $all_combis_ids[] = $matcher->rowId($combi);
            }
            $delete_combis_ids = array_diff ($all_combis_ids , $updated_combis_ids);
            foreach ($delete_combis_ids as $id_combi)
            {
                $combination = new Fs2psCombination($id_combi);
                $combination->delete();
                $this->ndeleted++;
            }
            
            if ($simulate_full_process) {
                $this->logProcess();
            }
        }
        
    }
    
    protected function sameGroup($row_a, $row_b)
    {
        return $row_a['id_product'] == $row_b['id_product'];
    }
    
    protected function onGroupUpdated($group_rows)
    {
        if (!empty($group_rows))
        {
            // Set default combination if none
            // Necessary in Prestashop 1.5, but not in Prestashop 1.6
            $combi = $group_rows[0];
            $id_product = $combi['id_product'];
            
            $product = new Fs2psProduct($id_product);
            
            if (!$this->price_only_in_product && !$this->price_in_both){
                // El precio será el de la combinación a menos que sólo se usen precios a nivel de producto
                $product->price = 0;
                
                // Borramos precios específicos a nivel de producto
                $reset_customer = $this->managing_price_by_customer? null : 0;
                $reset_from_quantity = $this->managing_price_from_quantity? null : 1;
                if ($this->nogroup_specific_prices) {
                    # Grupo general por defecto (sólo lo borramos si lo gestionamos)
                    Fs2psSpecificPrice::resetProductManagedPriceRates($id_product, 0, null, 0, $reset_customer, $reset_from_quantity); 
                }
                Fs2psSpecificPrice::resetProductManagedPriceRates($id_product, 0, null, null, $reset_customer, $reset_from_quantity); # Grupos gestionados
            }
            
            // Weight will be combination height if is set
            if (isset($combi['weight'])) $product->weight = 0;
            
            // Set default combi
            $set_default_combi_id = null;
            foreach($group_rows as $r) {
                if (!empty($r['_default'])) {
                    $set_default_combi_id = $this->matcher->rowId($r);
                    break;
                }
            }
            if ($set_default_combi_id) {
                //$default = Product::getDefaultAttribute($id_product);
                // if ($set_default_combi_id!=$default) { // No podemos aplicar esta opti. Forzamos set porque se puso default_on=0 en todas las combis
                    $product->deleteDefaultAttributes();
                    $product->setDefaultAttribute($set_default_combi_id);
                //}
            } else {
                // Si no se especificó el campo _default, procuramos que haya combinación por defecto y que tenga stock si puede ser
                $default = Product::getDefaultAttribute($id_product, 1);
                $product->deleteDefaultAttributes();
                $product->setDefaultAttribute($default);
            }
            
            $product->update();
                        
            /* ACTUALMENTE FACTUSOL SÓLO CONTEMPLA PRECIO DE COSTE A NIVEL DE PRODUCTO
             // Set supplier references and cost unit price
             $suppliers = Fs2psTools::dbSelect('
             SELECT distinct id_supplier FROM @DB_product_supplier WHERE id_product='.$id_product.'
             ');
             if ($suppliers)
             {
             $product->deleteFromSupplier();
             foreach ($suppliers as $supplier)
             $product->addSupplierReference($id_supplier, $supplier);
             }
             */
             
        }
        
        // Remove not uploaded combinations
        if ($this->delete_prodascombi_combinations == true) {
            $this->removeNotPresentCombis($group_rows[0]['id_product'], $group_rows);
        }
        
        /*
        // TODO: Eliminar precios específicos a nivel de producto?
        Fs2psTools::dbExec('update @DB_specific_price sp set sp.price=-1');
        */
        
    }
    
}


class Fs2psPackItemUpdater extends Fs2psDtoAbstractProcessor
{
    protected $productMatcher;
    protected $combiMatcher;
    protected $useCombis;
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        $this->productMatcher = Fs2psMatcherFactory::get($task, 'products');
        $this->combiMatcher = Fs2psMatcherFactory::get($task, 'combinations');
        
        // Combinaciones en packs disponible a partir de la ver. 1.7 ó 1.6.2.x
        $this->useCombis = version_compare(_PS_VERSION_, '1.6.2.0') >= 0;
    }
    
    protected function deleteRemoved($id_product_pack, $packs) {
        $ndeleted = 0;
        foreach ($packs as $id_product_item => $a ) {
            foreach ($a as $id_product_attribute_item => $q) {
                if ($q==-1) continue;
                Fs2psTools::dbExec('
                    delete from @DB_pack 
                    where id_product_pack='.$id_product_pack.'
                        and id_product_item='.$id_product_item.'
                        '.($this->useCombis? ' and id_product_attribute_item='.$id_product_attribute_item : '').' 
                ');
                $ndeleted++;
            }
        }
        return $ndeleted;
    }
    
    protected function getPacks($id_product_pack) {
        $packs = array();
        $rows = Fs2psTools::dbSelect('
            select id_product_item, '.($this->useCombis? 'id_product_attribute_item': '0 as id_product_attribute_item').'
            from @DB_pack where id_product_pack='.$id_product_pack.'
        ');
        foreach ($rows as $row) {
            $id_product_item = $row['id_product_item'];
            if (!isset($packs[$id_product_item])) $packs[$id_product_item] = array();
            $packs[$id_product_item][$row['id_product_attribute_item']] = $row['quantity'];
        }
        return $packs;
    }
    
    public function process($dtos)
    {
        $this->resetCounters($dtos);
        $this->reloadCfg();
        
        $ncreated = 0;
        $nupdated = 0;
        $ndeleted = 0;
        
        $packs = array();
        $last_id_product_pack = 0;
        
        if ($dtos) {
            
            foreach ($dtos as $dto)
            {
                $id_product_pack = $this->productMatcher->rowIdFromDtoId($dto['pack_ref']);
                if ($id_product_pack!=$last_id_product_pack) {
                    $ndeleted = $this->getDeleteRemoved($id_product_pack, $packs);
                    $this->task->log($this->name.': '.$ncreated.' creados, '.$nupdated.' actualizados, '.$ndeleted.' eliminados');
                    
                    $ncreated = 0;
                    $nupdated = 0;
                    $packs = $this->getPacks($id_product_pack);
                    $last_id_product_pack = $id_product_pack;
                }
                    
                $id_product_item = $this->productMatcher->rowIdFromDtoId($dto['item']);
                $quantity = $dto['quantity'];
                if ($this->useCombis && (!empty($dto['size']) || !empty($dto['colour']))) {
                    $id_product_attribute_item = $this->combiMatcher->rowIdFromDtoId(array('ref'=>$dto['item'], 'size'=>$dto['size'], 'colour'=>$dto['colour']));
                } else $id_product_attribute_item = 0;
                
                $exist = false;
                $modified = false;
                if (isset($packs[$id_product_item])) {
                    $packs2 = $packs[$id_product_item];
                    if (isset($packs2[$id_product_attribute_item])) {
                        $exist = true;
                        $modified = $packs2[$id_product_attribute_item]!=$quantity;
                        $packs2[$id_product_attribute_item] = -1; // Mark as not removed
                    }
                }
                if (!$exist || $modified) {
                    $values = array('quantity' => $quantity);
                    $where = array('id_product_pack'=>$id_product_pack, 'id_product_item'=>$id_product_item);
                    if ($this->useCombis) $where['id_product_attribute_item'] = $id_product_attribute_item;
                    if ($modified) {
                        Fs2psTools::dbUpdate('pack', $values, $where);
                        $nupdated++;
                    } else {
                        Fs2psTools::dbInsert('pack', array_merge($values, $where));
                        $ncreated++;
                    }
                }
                
                $this->nprocessed++;
            }
            
            if ($last_id_product_pack) {
                $ndeleted = $this->getDeleteRemoved($last_id_product_pack, $packs);
                $this->task->log($this->name.': '.$ncreated.' creados, '.$nupdated.' actualizados, '.$ndeleted.' eliminados');
            }
        }
    }
    
}

class Fs2psUpdateProductUpdater extends Fs2psProductUpdater
{
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        if (!$exists) return null;
        if (isset($dto['stock'])) $row['_quantity'] = $dto['stock'];
        if (isset($dto['combinations']))  {
            $row['_combinations'] = $dto['combinations'];
        }
        return $row;
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        $this->setQuantity($oldRowId, null, $row['_quantity']);
        if ($this->enable) {
            Fs2psTools::dbUpdate('product', array('active'=>1), array('id_product'=>$oldRowId));
            Fs2psTools::dbUpdate('product_shop', array('active'=>1), array('id_product'=>$oldRowId));
        }
        return $oldRowId;
    }
    
    protected function onProcessStart($dtos) { }
    protected function onProcessEnd($dtos) { }
    
}


class Fs2psUpdateSizeColourCombinationUpdater extends Fs2psSizeColourCombinationUpdater
{
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        if (!$exists) return null;
        
        $id_product = $this->products_upd->matcher->rowIdFromDtoId($dto['ref']);
        if (empty($id_product)) {
            throw new Fs2psException(
                'No existe el producto referenciado por la combinación: '.$this->getSizeColDesc($dto)
                );
        }
        
        $row = array(
            'id_product' => $id_product,
            //'_quantity' => isset($dto['stock']) ? $dto['stock'] : 0,
        );
        if (isset($dto['stock'])) $row['_quantity'] = $dto['stock'];
        return $row;
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        $id_product = $row['id_product'];
        if (isset($row['_quantity'])) {
            $this->setQuantity($id_product, $oldRowId, $row['_quantity']);
        }
        return $oldRowId;
    }
    
    protected function onGroupUpdated($group_rows) {
        // Sólo actulizamos, así que no crearemos nada ni borraremos nada
    }
    
    protected function onProcessStart($dtos) { }
    protected function onProcessEnd($dtos) { }
}

/**
 * Clase abstracta conveniente para la actualización de stockables (elementos identificables que pueden 
 * ser productos o combinaciones o aparecer repetidos en ambos formatos). 
 */
 class Fs2psAbstractStockablesUpdater extends Fs2psSKUUpdater
 {
    protected $productMatcher;
    protected $combiMatcher;
    protected $parentsData = array();
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        $this->productMatcher = Fs2psMatcherFactory::get($task, 'products');
        $this->combiMatcher = Fs2psMatcherFactory::get($task, 'combinations');
        $this->loadTaxRulesGroupByRate();
    }
       
    protected function dto2row($dto, $idx, $exists, $oldRowId, $es_combi=FALSE)
    {
        $matcher = $es_combi? $this->combiMatcher : $this->productMatcher;
        $ref = $matcher->dtoIdToStrFromDto($dto);
        $row = array( '_dto_id' => $matcher->dtoId($dto) );
        
        if (isset($dto['price']) || isset($dto['prices'])) {
            $rate = (string)($dto['iva'] * 100);
            if (empty($this->id_tax_rules_group_by_rate[$rate])) {
                throw new Fs2psException(
                    'No se definió en la tienda un IVA del '.$rate.'% (prod. "'.$ref.'")'
                );
            }
            $row ['id_tax_rules_group'] = $this->id_tax_rules_group_by_rate[$rate];
            $row ['_tax_rate'] = $dto['iva'];
        }
        
        return $row;
    }
    
    protected function processOne($dto, $id, $dto_id, $es_combi)
    {
        if (empty($id)) {
            $this->task->log('WARN: No existe '.($es_combi? 'la combinación': 'el producto').' '.$dto_id);
            return FALSE;
        }

        $parent_id = null;
        if ($es_combi) {
            $parent_id = Fs2psTools::dbValue('select id_product from @DB_product_attribute where id_product_attribute='.(int)$id);
            if (empty($parent_id)) {
                $this->task->log('WARN: No existe el producto padre de la combinación ('.$id.')');
                return FALSE;
            }
        }
        
        $row = $this->dto2row($dto, 0, TRUE, $id, $es_combi); // TODO $idx as second param
        $matcher = $es_combi? $this->combiMatcher : $this->productMatcher;
        $row[$matcher->row_id_field] = $id;
        
        $id_product = $es_combi? $parent_id : $id;
        $id_product_attribute = $es_combi? $id : 0;
        $row['_id_product'] = $id_product;
        $row['_id_product_attribute'] = $id_product_attribute;

        return $this->updateOne($id_product, $id_product_attribute, $dto, $row);
    
    }
    
    public function process($dtos, $force_combi=false)
    {
        $this->reloadCfg();
		
		$this->onProcessStart($dtos);
        
        $this->ntotal += $dtos==null? 0 : count($dtos);
        $nupdated_products = 0;
        $nupdated_products_repe = 0;
        $nupdated_combis = 0;
        $nupdated_combis_repe = 0;
        $parentsData = &$this->parentsData;
        
        if ($dtos) {
            $combiMatcher = $this->combiMatcher;
            $productMatcher = $this->productMatcher;
            foreach ($dtos as $dto)
            {
                
                if (empty($dto['combinations']) || $this->price_in_both) {
                
                    // Hace match con combinaciones?
                    $combiDtoId = $combiMatcher->dtoId($dto);
                    $combiDtoIdStr = $combiDtoId? $combiMatcher->dtoIdToStr($combiDtoId) : '';

                    $combiPostIds = null;
                    if (!empty($combiDtoIdStr)) {
                        try {
                            $combiPostIds = $combiMatcher->rowIdsFromDto($dto);
                        } catch (Exception $e) { }
                    }

                    if (empty($combiPostIds)) {
                        if ($force_combi) {
                            // No existe combinación para esa ref y debería existir
                            $this->task->log('WARN: No existe la combinación ('.$combiDtoIdStr.')');
                            continue;
                        }
                    } else {
                        $any_repe_updated = FALSE;
                        foreach ($combiPostIds as $combiPostId) {
                            if ($this->processOne($dto, $combiPostId, $combiDtoIdStr, TRUE)) {
                                $any_repe_updated = TRUE;
                                $nupdated_combis_repe++;
                            }
                        }
                        if ($any_repe_updated) $nupdated_combis++;
                    }
                    
                    // Hace match con productos?
                    if (!$force_combi) { // Evitamos falsos matchs por ref cuando son dtos de combis. Puede que sea prod en FS y combi en PS pero consideramos que nunca pasará que es combi en FS y product en PS.
                        $productDtoId = $productMatcher->dtoId($dto);
                        $productDtoIdStr = $productDtoId? $productMatcher->dtoIdToStr($productDtoId) : '';
                        
                        $productPostIds = null;
                        if (!empty($productDtoIdStr)) {
                            try {
                                $productPostIds = $productMatcher->rowIdsFromDto($dto);
                            } catch (Exception $e) { }
                        }
                        
                        if (empty($productPostIds)) {
                            if (empty($combiPostIds)) {
                                // No existe producto ni combinación para esa ref
                                $combiDtoIdStr = $combiDtoId? $combiMatcher->dtoIdToStr($combiDtoId) : '';
                                $this->task->log('WARN: No existe producto ('.$productDtoIdStr.') ni combinación ('.$combiDtoIdStr.')');
                                continue;
                            }
                        } else {
                            $any_repe_products = FALSE;
                            foreach ($productPostIds as $productPostId) {
                                if ($this->processOne($dto, $productPostId, $productDtoIdStr, FALSE)) {
                                    $any_repe_products = TRUE;
                                    $nupdated_products_repe++;
                                }
                            }
                            if ($any_repe_products) $nupdated_products++;
                        }
                    }
                }
                
                if (!empty($dto['combinations'])) {
                    $this->process($dto['combinations'], !$this->update_stockables_combis_can_be_products);
                    continue;
                }
                
                $this->nprocessed++;
            }
        }
        
        if (!$force_combi) {
            foreach ($parentsData as $id_product=>$pdata) {
                $this->processParent($id_product, $pdata);
            }
        }
        
        if ($nupdated_products) $this->task->log('products: '.$nupdated_products.' actualizados'.($nupdated_products_repe>$nupdated_products? ' (+'.($nupdated_products_repe-$nupdated_products).' reps)' : ''));
        if ($nupdated_combis) $this->task->log('combinations: '.$nupdated_combis.' actualizados'.($nupdated_combis_repe>$nupdated_combis? ' (+'.($nupdated_combis_repe-$nupdated_combis).' reps)' : ''));
    }
    
    protected function updateOne($id_product, $id_product_attribute, $dto, $row) { }

    protected function processParent($id_product, $pdata) { }

}

class Fs2psStockablesUpdater extends Fs2psAbstractStockablesUpdater 
{    
    protected $update_cost;
    protected $update_ean;
    
    protected function reloadCfg() 
    {
        parent::reloadCfg();
        $cfg = $this->task->cfg;
        $this->update_cost = $cfg->get('UPDATE_STOCKABLES_COST', FALSE);
        $this->update_ean = $cfg->get('UPDATE_STOCKABLES_EAN', FALSE);
        $this->update_stockables_tax = $cfg->get('UPDATE_STOCKABLES_TAX', FALSE);
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId, $es_combi=FALSE) 
    {
        $row = parent::dto2row($dto, $idx, $exists, $oldRowId, $es_combi);
        
        if (isset($dto['price']) || isset($dto['prices'])) {        
            // Prices. Multiple price rates or simple price.
            if (empty($dto['prices'])) {
                $row['price'] = isset($dto['price']) ? $dto['price'] : 0;
            } else {
                $row['_prices'] = $dto['prices'];
                $row['price'] = $dto['prices'][0]['price'];
            }
        }
        
        if (isset($dto['backorders'])) $row['_backorders'] = $dto['backorders'];
        
        return $row;
    }
    
    protected function updateOne($id_product, $id_product_attribute, $dto, $row) 
    {    
        $updated = FALSE;
        $es_combi = !empty($id_product_attribute);

        if (isset($dto['stock'])) {
            $updated = $this->setQuantity($id_product, $id_product_attribute, $dto['stock']);
        }
        
        if(isset($dto['price']) && !isset($dto['prices'])) {
            $dto['prices'] = array();
            $dto['prices'][0]['price'] = $dto['price'];
        }
        if (isset($dto['prices'])) {
            // SÓLO ACTUALIZAMOS PRECIOS POR DEFECTO en este punto.
            // Y si se trata de actualizar varias tiendas de la multitienda, sólo actualizaremos el precio por defecto de cada tienda (el primero).

            $previous_id_shop = null;
            foreach($dto['prices'] as $idx => $price_arr) {

                // Si $id_shop es que estamos actualizando precios de varias tiendas de la multitienda
                $id_shop = array_key_exists('shop',  $price_arr) && $price_arr['shop']? $price_arr['shop'] : null;
                
                // Sólo actualizamos el precio por defecto de cada tienda (el primero)
                if ($previous_id_shop && $id_shop == $previous_id_shop) continue;

                $where_shop = $id_shop? array('id_shop'=>$id_shop) : array();
                $where_product = array('id_product'=>$id_product);
                $where_combi = array('id_product_attribute'=>$id_product_attribute);
                $where_product_and_combi = array_merge($where_product, $where_combi);
                $where_product_and_shop = array_merge($where_product, $where_shop);

                $price = $price_arr['price'];
                $update_tax = $this->update_stockables_tax? array('id_tax_rules_group'=>$row['id_tax_rules_group']) : array();

                if ($es_combi) {

                    $cprice = $this->price_only_in_product? 0 : $price;
                    $pprice = $this->price_only_in_product? $price : 0;
                    if($this->price_in_both) {
                        if ($id_shop) $pprice = floatval(Fs2psTools::dbValue('select price from @DB_product_shop where id_product='.$id_product.' and id_shop='.$id_shop));
                        else $pprice = floatval(Fs2psTools::dbValue('select price from @DB_product where id_product='.$id_product));
                        $cprice = round($cprice - $pprice,6);
                        $dto['prices'][$idx]['price'] =  $cprice;
                    }
                
                    $update_pprice_and_tax = array_merge(array('price'=>$pprice), $update_tax);
                    if ($idx==0) Fs2psTools::dbUpdate('product', $update_pprice_and_tax, $where_product);
                    Fs2psTools::dbUpdate('product_shop', $update_pprice_and_tax, $where_product_and_shop);

                    $update_cprice = array('price'=>$cprice);
                    if ($idx==0) Fs2psTools::dbUpdate('product_attribute', $update_cprice, $where_product_and_combi);
                    if (version_compare(_PS_VERSION_, '1.6.0.9') <= 0) {
                        Fs2psTools::dbUpdate('product_attribute_shop', $update_cprice, array_merge($where_combi, $where_shop));
                    } else {
                        Fs2psTools::dbUpdate('product_attribute_shop', $update_cprice, array_merge($where_product_and_combi, $where_shop));
                    }
                    
                    $parentsData = &$this->parentsData;
                    if (!isset($parentsData[$id_product])) $parentsData[$id_product] = array();
                    $pdata = &$parentsData[$id_product];
                    $pdata['price_in_combis'] = 1;

                } else {

                    $update_price_and_tax = array_merge(array('price'=>$price), $update_tax);
                    if ($idx==0) Fs2psTools::dbUpdate('product', $update_price_and_tax, $where_product);
                    Fs2psTools::dbUpdate('product_shop', $update_price_and_tax, $where_product_and_shop);

                }

                // Sólo actualizamos el precio por defecto de la tienda por defecto si no estamos actualizando varias tiendas de la multitienda
                if (!$id_shop) break;

                $previous_id_shop = $id_shop;
            }
            
            $this->insertOrUpdatePrice($row, $id_product, $id_product_attribute);
            $updated = TRUE;

        }
        
        if($this->update_ean && isset($dto['ean']) && !($this->update_ean ==='ifnotempty' && empty($dto['ean']))) {
            $ean = $dto['ean'];

            if ($es_combi) {
                Fs2psTools::dbUpdate('product_attribute', array('ean13'=>$ean), array('id_product'=>$id_product, 'id_product_attribute'=>$id_product_attribute));
            } else {
                Fs2psTools::dbUpdate('product', array('ean13'=>$ean), array('id_product'=>$id_product));
            }
            $updated = TRUE;
        }
        
        if ($this->update_cost && isset($dto['cost'])) {
            $cost = $dto['cost'];
            
            if ($es_combi) {
                Fs2psTools::dbUpdate('product_attribute', array('wholesale_price'=>$cost), array('id_product'=>$id_product, 'id_product_attribute'=>$id_product_attribute));
                if (version_compare(_PS_VERSION_, '1.6.0.9') <= 0) {
                    Fs2psTools::dbUpdate('product_attribute_shop', array('wholesale_price'=>$cost), array('id_product_attribute'=>$id_product_attribute));
                } else {
                    Fs2psTools::dbUpdate('product_attribute_shop', array('wholesale_price'=>$cost), array('id_product'=>$id_product, 'id_product_attribute'=>$id_product_attribute));
                }
            } else {
                Fs2psTools::dbUpdate('product', array('wholesale_price'=>$cost), array('id_product'=>$id_product));
                Fs2psTools::dbUpdate('product_shop', array('wholesale_price'=>$cost), array('id_product'=>$id_product));
            }
            $updated = TRUE;
        }
        
        return $updated;
    }

    protected function processParent($id_product, $pdata)
    {
        if (isset($pdata['stock'])) {
            // TODO: El stock será la suma del stock de las combinaciones?
        }
        if (isset($pdata['price_in_combis'])) {
            // Poner a 0 precio en padres?
            $product = new Fs2psProduct($id_product);
            if (!$this->price_only_in_product && !$this->price_in_both) {
                // El precio será el de la combinación a menos que sólo se usen precios a nivel de producto
                $product->price = 0;
                /*
                    * TODO
                // Borramos precios específicos a nivel de producto
                $reset_customer = $this->managing_price_by_customer? null : 0;
                $reset_from_quantity = $this->managing_price_from_quantity? null : 1;
                if ($this->nogroup_specific_prices) {
                    # Grupo general por defecto (sólo lo borramos si lo gestionamos)
                    Fs2psSpecificPrice::resetProductManagedPriceRates($id_product, 0, null, 0, $reset_customer, $reset_from_quantity);
                }
                Fs2psSpecificPrice::resetProductManagedPriceRates($id_product, 0, null, null, $reset_customer, $reset_from_quantity); # Grupos gestionados
                */
            }
        }
    }
    
}

class Fs2psStockablesCombisUpdater extends Fs2psStockablesUpdater {
    public function process($dtos, $force_combi=false)
    {
        parent::process($dtos, true);
    }
}

class Fs2psSpecialOffersUpdater extends Fs2psAbstractStockablesUpdater
{
    
    protected function dto2row($dto, $idx, $exists, $oldRowId, $es_combi=FALSE) 
    {
        $row = parent::dto2row($dto, $idx, $exists, $oldRowId, $es_combi);
        $row['_prices'] = empty($dto['prices'])? [] : $dto['prices'];
        return $row;
    }

    protected function updateOne($id_product, $id_product_attribute, $dto, $row) 
    {
        $this->insertOrUpdatePrice($row, $id_product, $id_product_attribute);
        
        $parentsData = &$this->parentsData;
        if (!isset($parentsData[$id_product])) $parentsData[$id_product] = array();
        $pdata = &$parentsData[$id_product];
        if (!empty($id_product_attribute)) $pdata['price_in_combis'] = 1;
        else {
            if (!isset($pdata['prices'])) $pdata['prices'] = [];
            $pdata['prices'] = array_merge($pdata['prices'], $row['_prices']);
        }

        return TRUE;
    }
 
    protected function processParent($id_product, $pdata)
    {
        if (isset($pdata['price_in_combis'])) {
            // Si las ofertas especiales se ponen en combinaciones quitamos las que se pudieran haber asignado al producto simple
            $this->removeUnusedCustomerPriceRates(array(), $id_product, 0);
        } else {
            // Si las ofertas especiales se ponen en el producto, quitamos todas las no gestionadas, tanto del producto como de las combinaciones
            $this->removeUnusedCustomerPriceRates($this->pricesByShop($pdata['prices']), $id_product, null);
        }
    }

}


// XXX: En pedidos sólo soportamos actualizaciones, al menos de momento. No existe Fs2psOrderUpdater.
class Fs2psUpdateOrderUpdater extends Fs2psUpdater
{
    protected $updatable_status;

    protected function getFailSafeStatuses($statuses, $filter='/[0-9]+/') {
		if (empty($statuses)) return null;
		$matches = null;
		preg_match_all($filter, $statuses, $matches);
		return $matches[0];
	}

    protected function reloadCfg() {
		parent::reloadCfg();
		$cfg = $this->task->cfg;
		$updatable_status = strtolower($cfg->get(strtoupper($this->name).'_UPDATABLE_STATUS', ''));
        $this->updatable_status = $this->getFailSafeStatuses($updatable_status, '/[a-z0-9\-]+/i');
	}

    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        if (!$exists) return null;
        if (empty($dto['status'])) return null;
        
		$statuses = $this->getFailSafeStatuses($dto['status']);
		if (!$statuses || !$statuses[0]) return null;
		
		return array('_status' => $statuses[0]);
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        $order = new Order($oldRowId);
        $status = $row['_status'];

        if ($this->updatable_status && !in_array($order->getCurrentState(), $this->updatable_status)) {
            $this->task->log('INFO: El pedido '.$order->id.' no tiene un estado actualizable');
            return;
        }

        if (!Fs2psOrder::setCurrentStateIfChanged($order, $status)) {
            $this->task->log('INFO: El pedido '.$order->id.' ya está en estado '.$status);
        }
    }
}

class Fs2psUpdateOrderTrack extends Fs2psUpdater
{
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        if (!$exists) return null;
        if (empty($dto['track_number'])) return null;
        
		return array('_shipping_number' => $dto['track_number']);
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        $order = new Order($oldRowId);
        $shippingNumber = $order->getWsShippingNumber();
        if (empty($shippingNumber)) {
            // Sólo cambiamos el numero de seguimiento si es necesario
            $order->setWsShippingNumber($row['_shipping_number']);
        } else {
            $this->task->log('INFO: El pedido '.$order->id.' ya tiene un numero de seguimiento ');
        }
    }
}

class Fs2psCustomerUpdater extends Fs2psUpdater
{

    protected $address_upd;
    protected $default_groups;
    
    protected $ifnotexist;
    protected $dontsetaddress;
    protected $dontadddefaultgroups;
    protected $dontresetdefaultgroups;
    
    
    protected $dont_reset_groups;
    
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        $this->address_upd = new Fs2psAddressUpdater($task, 'customer_addresses', false);
        $this->default_groups = array(
            Configuration::get('PS_UNIDENTIFIED_GROUP'),
            Configuration::get('PS_GUEST_GROUP'),
            Configuration::get('PS_CUSTOMER_GROUP')
        );
    }
    
    protected function reloadCfg() {
        parent::reloadCfg();
        $cfg = $this->task->cfg;
        $updpart = strtoupper($this->name);
        $options = $cfg->get('UPDATE_'.$updpart, '');
        $this->ifnotexist = strpos($options, 'ifnotexist')!==false;
        $this->dontsetaddress = strpos($options, 'dontsetaddress')!==false;
        $this->dontadddefaultgroups = strpos($options, 'dontadddefaultgroups')!==false;
        $this->dontresetdefaultgroups = strpos($options, 'dontresetdefaultgroups')!==false;
        $this->forcepass = strpos($options, 'forcepass')!==false;
        
        $default_birthday = $cfg->get('CUSTOMERS_DEFAULT_BIRTHDAY');
        $this->default_birthday = $default_birthday? Fs2psTools::date2tdb(Fs2psTools::dto2date($default_birthday)) : null;
        
        $this->dont_reset_groups = $this->dontresetdefaultgroups? $this->default_groups : array();
    }
    
    public function getPriceRateRowId($dto_id)
    {
        $group_upd = $this->task->getUpdater('price_rates');
        return $group_upd->matcher->rowIdFromDtoId($dto_id);
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        $row = array(
            'lastname' => $dto['lastname'],
            'firstname' => $dto['firstname'],
            'email' => $dto['email'],
        );
        
        if (isset($dto['company']) && !$exists) {
            // Este campo es ambiguo: Nombre fiscal? Nombre comercial?
            // Sólo establecemos su valor si se trata de un nuevo cliente,
            // en caso contrario respetamos lo que haya y no lo machacamos.
            $row['company'] = $dto['company'];
        }
        
        if (isset($dto['group'])) {
            $group_row_id = $this->getPriceRateRowId($dto['group']);
            $row['id_default_group'] = $group_row_id;
            $row['_group'] = $group_row_id;
        }
        
        if ( !$exists || ($this->forcepass && isset($dto['passwd'])) )
        {
            $row['passwd'] = Tools::encrypt(isset($dto['passwd'])? $dto['passwd'] : Fs2psTools::randomPassword());
        }
        
        if ($this->enable) {
            $row['active'] = 1;
        } elseif (!$exists) {
            $row['active'] = 0;
        }
        
        if (isset($dto['newsletter'])) $row['newsletter'] = !empty($dto['newsletter']);

        if (!empty($dto['birthday'])) $row['birthday'] = Fs2psTools::date2tdb(Fs2psTools::dto2date($dto['birthday']));
        else if (!$exists && $this->default_birthday) $row['birthday'] = $this->default_birthday;
        
        return $row;
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        // Si ifnotexist sólo creamos si no existe, no actualizamos
        $id = $this->ifnotexist && $exists? $oldRowId : parent::insertOrUpdate($row, $exists, $oldRowId, $oldObj);
        
        $customer = new Fs2psCustomer($id);
        
        if (!$exists && !$this->dontadddefaultgroups) {
            // Si es nuevo y no nos lo impide la configuración
            $customer->addGroups($this->default_groups);
        }
        
        if (isset($row['_group'])) {
            $group_id = $row['_group'];
            $groups = Fs2psCustomer::getFs2psGroups($id);
            if (count($groups)!=1 || count($groups)==1 && $groups[0]['id_group']!=$group_id) {
                // Si se gestiona tambien un grupo estandar como PRICE_RATE, se debería de quitar/asignar
                if (count($groups)>0) Fs2psCustomer::resetFs2psGroups($id, $this->dont_reset_groups);
                $customer->addGroups(array($group_id));
            }
            if ($this->ifnotexist && $exists) {
                // Forzamos actualización de grupo por defecto si ifnotexist activado
                Fs2psTools::dbUpdate('customer', array('id_default_group'=>$group_id), array('id_customer'=>$id));
            }
        }
        
        return $id;
    }
    
    protected function onInsertedOrUpdated($dto, $row, $inserted)
    {
        parent::onInsertedOrUpdated($dto, $row, $inserted);
        
        // Si ifnotexist sólo creamos si no existe, no actualizamos
        if (!$this->dontsetaddress && (!$this->ifnotexist || $inserted)) {
            $address_row = $dto;
            $address_row['alias'] = empty($dto['address_alias'])? 'Fiscal' : $dto['address_alias'];
            $address_row['ref'] = $dto['ref'];
            $address_row['customer'] = $dto['ref'];
            $this->address_upd->process(array($address_row));
        }
    }
    
}

class Fs2psAddressUpdater extends Fs2psUpdater
{
    protected $logEnabled;
    
    public function __construct($task, $name, $logEnabled=true)
    {
        parent::__construct($task, $name);
        $this->logEnabled = $logEnabled;
    }
    
    public function getCustomerRowId($dto_id)
    {
        $customer_upd = $this->task->getUpdater('customers');
        return $customer_upd->matcher->rowIdFromDtoId($dto_id);
    }
    
    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        
        $row = array(
            'id_customer' => $this->getCustomerRowId($dto['customer']),
            'dni' => isset($dto['nif'])? $dto['nif'] : '',
            
            'alias' => $dto['alias'],
            'lastname' => $dto['lastname'],
            'firstname' => $dto['firstname'],
            'postcode' => $dto['postcode'],
            'city' => $dto['city'],
            
            'phone' => $dto['phone'],
            'phone_mobile' => isset($dto['mobile'])? $dto['mobile'] : '',
            
            'address1' => $dto['address1'],
            'address2' => isset($dto['address2'])? $dto['address2'] : '',
        );
        
        if ($this->enable) $row['deleted'] = 0;
        elseif (!$exists)  $row['deleted'] = 1;
        
        if (isset($dto['company']))  $row['company'] = $dto['company'];
        
        $id_country = null;
        $id_state = null;
        if (isset($dto['country'])) {
            $id_country = Country::getByIso($dto['country']);
            if (!empty($id_country) && isset($dto['state'])) {
                $id_state = State::getIdByIso($dto['state'], $id_country);
                if (empty($id_state)) {
                    $id_state = State::getIdByIso($dto['country'].'-'.$dto['state'], $id_country);
                }
            }
        }
        if (!empty($id_country)) $row['id_country'] = $id_country;
        if (!empty($id_state)) $row['id_state'] = $id_state;
        
        return $row;
    }
    
    public function logProcess() {
        if ($this->logEnabled) parent::logProcess();
    }
    
}

class Fs2psDisableProductUpdater extends Fs2psUpdater
{
    protected function reloadCfg() {
		parent::reloadCfg();
		$this->matcher->persist = false; // No persistimos lo matchs de productos al desactivar. Sólo desactivamos existentes.
	}

    protected function dto2row($dto, $idx, $exists, $oldRowId)
    {
        if (!$exists) return null;
		return array('_active' => 0); // Por poner algo en el array y que se evalúe como true
    }
    
    protected function insertOrUpdate($row, $exists, $oldRowId, $oldObj=null)
    {
        $product = new Fs2psProduct($oldRowId);
        if ($product->active) {
            $product->active = 0;
            $product->update();
        }
        return $oldRowId;
    }
}
