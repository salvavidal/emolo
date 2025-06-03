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
include_once(dirname(__FILE__).'/Fs2psExtractor.php');
include_once(dirname(__FILE__).'/Fs2psMatchers.php');
include_once(dirname(__FILE__).'/Fs2psObjectModels.php');
include_once(dirname(__FILE__).'/Fs2psUpdaters.php');


class Fs2psOrderDependentExtractor extends Fs2psExtractor
{
	protected $ifvalid;
	protected $ifinvoice;
	protected $orders_valid_states;
	protected $orders_nonvalid_states;
	protected $download_orders_ids;
	protected $customerMatcher;
	

	public function __construct($task, $name)
	{
	    parent::__construct($task, $name);
	    $this->customerMatcher = Fs2psMatcherFactory::get($task, 'customers');
	}
	
	protected function reloadCfg() {
		parent::reloadCfg();
		$cfg = $this->task->cfg;
		$download_orders = $cfg->get('DOWNLOAD_ORDERS', '');
        $download_cancelled_orders = $cfg->get('DOWNLOAD_CANCELLED_ORDERS', '');
		$this->ifvalid = strpos($download_orders, 'ifvalid') !== false;
		$this->ifinvoice = strpos($download_orders, 'ifinvoice') !== false;
		
        $valid_array = [ ];
        if ($download_cancelled_orders) $valid_array[] = '6';

        $non_valid_array = [ ];
        if (!$download_cancelled_orders) $non_valid_array[] = '6';
        
        $val_nonval_defaults = [
            'VALID' => implode(',', $valid_array),
            'NONVALID' => implode(',', $non_valid_array)
        ];

        foreach ($val_nonval_defaults as $k => $v) {
            $states = $cfg->get('DOWNLOAD_ORDERS_'.$k.'_STATES', $v);
            if (!empty($states)) {
                $matches = null;
                preg_match_all('/[0-9]+/', strval($states), $matches);
                $this->{'orders_'.strtolower($k).'_states'} = $matches[0];
            }
        }

		if (is_bool($download_orders)) {
		    $this->download_orders_ids = array();
		} else {
    		$matches = null;
    		preg_match_all('/[0-9]+/', strval($download_orders), $matches);
    		$this->download_orders_ids = $matches[0];
		}
		
		$this->getcustref = strpos($download_orders, 'getcustref') !== false;
	}

	protected function getAfterDateWhereCondition()
	{
		$where = array();

		if ($this->download_orders_ids) {
		    $where[] = 'o.id_order in ('.join(',', $this->download_orders_ids).')';
		} else {		    
		    if (!empty($this->task->cmd['after'])) {
		        $after_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['after']));
		        $where[] = 'o.date_upd>\''.$after_str.'\'';
		    }
		    
		    if (!empty($this->task->cmd['created_after'])) {
		        $created_after_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['created_after']));
		        $where[] = 'o.date_add>\''.$created_after_str.'\'';
		    }
		    
    		if (!empty($this->task->cmd['until'])) {
    			$until_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['until']));
    			$where[] = 'o.date_upd<=\''.$until_str.'\'';
    		}
    
    		if ($this->ifvalid) $where[] = 'o.valid=1';
    		if ($this->ifinvoice) $where[] = 'o.invoice_number>0';
    		
    		if ($this->orders_valid_states) {
    		    $where[] = 'o.current_state in ('.join(',', $this->orders_valid_states).')';
    		}
    		if ($this->orders_nonvalid_states) {
    		    $where[] = 'o.current_state not in ('.join(',', $this->orders_nonvalid_states).')';
    		}
		}
		
		return join(" and ", $where);
	}
}


class Fs2psCustomerExtractor extends Fs2psOrderDependentExtractor
{
	
	protected $download_customers;
	protected $noorder;
	protected $noaddress;
	protected $optional_fields = array('address2', 'extra1', 'other');
	
	protected $groupMatcher;
	
	public function __construct($task, $name)
	{
	    parent::__construct($task, $name);
	    $this->groupMatcher = Fs2psMatcherFactory::get($task, 'price_rates');
	}
	
	protected function reloadCfg() {
		parent::reloadCfg();
		$cfg = $this->task->cfg;
		$this->download_customers = $cfg->get('DOWNLOAD_CUSTOMERS', '');
		$this->noaddress = strpos($this->download_customers, 'noaddress') !== false;
		$this->noorder = $this->noaddress || strpos($this->download_customers, 'noorder') !== false;
	}
	
	protected function getAfterDateWhereCondition()
	{
	    
	    $where = array();
	    
	    if ($this->download_orders_ids) {
	        $where[] = 'o.id_order in ('.join(',', $this->download_orders_ids).')'; 
	    } else {
	        if (!empty($this->task->cmd['after'])) {
	            $after_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['after']));
	            $upd_where = array('c.date_upd>\''.$after_str.'\'');
	            $upd_where[] = 'a.date_upd>\''.$after_str.'\''; // Se debe indicar aunque !$this->noaddress
	            $upd_where[] = 'o.date_upd>\''.$after_str.'\''; // Se debe indicar aunque !$this->noorder
	            $where[] = '( '.join(' or ', $upd_where).' )';
	        }
	        
	        if (!empty($this->task->cmd['orders_created_after']) && !$this->noorder) {
	            // XXX: Para filtrar customers por fecha de creación de pedido
	            $orders_created_after_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['orders_created_after']));
	            $where[] = 'o.date_add>\''.$orders_created_after_str.'\'';
	        }
	        
	        if (!empty($this->task->cmd['until'])) {
                $until_str =  Fs2psTools::date2db(Fs2psTools::dto2date($this->task->cmd['until']));
                $where[] = 'c.date_upd<=\''.$until_str.'\'';
	        }
	        
	        if (!$this->noorder) {
    	        if ($this->ifvalid) $where[] = 'o.valid=1';
    	        if ($this->ifinvoice) $where[] = 'o.invoice_number>0';
    	        if ($this->orders_valid_states) {
    	            $where[] = 'o.current_state in ('.join(',', $this->orders_valid_states).')';
    	        }
    	        if ($this->orders_nonvalid_states) {
    	            $where[] = 'o.current_state not in ('.join(',', $this->orders_nonvalid_states).')';
    	        }
	        }
	    }
	    
	    return join(" and ", $where);
	}

	protected function buildSql()
	{
	    $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
	    
		return '
			SELECT
			    c.id_customer, a.id_address,
			    greatest(c.date_add, coalesce(a.date_add, 0)) as date_add, 
			    greatest(c.date_upd, coalesce(a.date_upd, 0), coalesce(o.date_upd, 0)) as date_upd,
                c.email,
                o.id_order,
                c.newsletter as newsletter,
                a.alias,
			    IFNULL(a.firstname, c.firstname) as firstname,
                IFNULL(a.lastname, c.lastname) as lastname,
			    a.company,
				c.company as extra1,
                a.other as other,
			    a.address1, a.address2, a.postcode,
			    a.city, a.phone, a.phone_mobile,
			    a.vat_number, a.dni,
			    st.name as state, col.name as country,
                co.iso_code as country_iso2,
                st.iso_code as state_iso2,
                c.id_default_group,
                (
					select GROUP_CONCAT(DISTINCT id_group)
					FROM @DB_customer_group WHERE id_customer = c.id_customer
                    GROUP BY id_customer
                ) AS roles

			FROM
			    @DB_customer c
			    inner join (
                    SELECT
				        c.id_customer,
                        REPLACE(REPLACE(upper(CASE
							WHEN a.dni>\'\' THEN a.dni
							ELSE ifnull(a.vat_number,\'\')
						END),\' \',\'\'),\'-\',\'\') as nif,
                        substring_index(GROUP_CONCAT(DISTINCT a.id_address ORDER BY a.deleted, a.active desc, o.id_order desc, a.id_address desc), \',\', 1) AS id_address,
                        substring_index(GROUP_CONCAT(DISTINCT o.id_order ORDER BY a.deleted, a.active desc, o.id_order desc, a.id_address desc), \',\', 1) AS id_order			        
				    FROM
				        @DB_customer c
				        '.($this->noaddress? 'left' : 'inner').' join @DB_address a on a.id_customer=c.id_customer
				        '.($this->noorder? 'left' : 'inner').' join @DB_orders o on o.id_address_invoice=a.id_address
				    WHERE
                            '.$this->getAfterDateWhereCondition().'
				    GROUP BY c.id_customer, nif  
			    ) as ai on ai.id_customer = c.id_customer
			    left join @DB_address a on a.id_address = ai.id_address
			    left join @DB_orders o on o.id_order = ai.id_order
			    left join @DB_state st on st.id_state=a.id_state
                left join @DB_country co on co.id_country=a.id_country
			    left join @DB_country_lang col
			        on col.id_country=a.id_country and col.id_lang='.$id_default_lang.'
			WHERE
			    '.$this->getAfterDateWhereCondition().'
			ORDER BY date_upd
		';
	}

	protected function row2dto($row)
	{
		$dto = array(
			'user' => intval($row['id_customer']),
			'created' => $row['date_add'],
			'updated' => $row['date_upd'],
			'company' => $row['company'],
			'nif' => $row['dni'],
			'vat_number' => $row['vat_number'],
            'address_alias' => $row['alias'],
			'firstname' => $row['firstname'],
			'lastname' => $row['lastname'],
			'email' => $row['email'],
            'id_order' => $row['id_order'],
			'address' => $row['address1'],
			'postcode' => $row['postcode'],
			'city' => $row['city'],
			'state' => $row['state'],
			'country' => $row['country'],
			'phone' => $row['phone'],
            'newsletter' => $row['newsletter'],
			'mobile' => $row['phone_mobile'],
		    'country_iso2' =>$row['country_iso2'],
            'state_iso2' => $row['state_iso2'],
            'roles' => isset($row['roles']) ? explode(",", $row['roles']): [],

		);
		
		foreach ($this->optional_fields as $of) {
		    if (!empty($row[$of])) $dto[$of] = $row[$of];
		}
		
		if ($row['id_default_group']) {
		    $groupDtoId = $this->groupMatcher->dtoIdStrFromRowId($row['id_default_group']);
		    if ($groupDtoId) $dto['group'] = $groupDtoId;
		}
		
		if ($this->getcustref) {
		    $dto_id_str = $this->customerMatcher->dtoIdStrFromRowId(intval($row['id_customer']));
    		if ($dto_id_str) $dto['customer_ref'] = $dto_id_str;
		}
		
		return $dto;
	}

}

class Fs2psCustomerAddressExtractor extends Fs2psCustomerExtractor
{
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        $this->matcher = Fs2psMatcherFactory::get($task, 'customer_addresses');
    }
    
    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        
        // XXX: Incompatible con noorder. Necesitamos pedidos para obtener la dirección fiscal, y por tanto el cliente
        return '
            SELECT distinct
                c.id_customer,

                ca.id_address as invoice_id_address,
                ca.vat_number as customer_vat_number,
                ca.dni as customer_nif,

                a.id_address as delivery_id_address,
                a.address1,
                a.address2,
                c.email,
                a.firstname,
                a.lastname,
                a.alias,
                a.company,
                a.phone,
                a.phone_mobile,
                a.vat_number,
                a.dni,
                a.city,
                a.postcode,
                st.name as state, 
                col.name as country,
                co.iso_code as country_iso2,
                a.date_add,
                a.date_upd,
                a.other,
                (
					select GROUP_CONCAT(DISTINCT id_group)
					FROM @DB_customer_group WHERE id_customer = c.id_customer
                    GROUP BY id_customer
                ) AS roles

            FROM
                @DB_customer c
                inner join @DB_address a on a.id_customer=c.id_customer
                inner join @DB_orders o on o.id_address_invoice=a.id_address or o.id_address_delivery=a.id_address
                left join @DB_address ca on ca.id_address=o.id_address_invoice
                left join @DB_state st on st.id_state=a.id_state
                left join @DB_country co on co.id_country=a.id_country
                left join @DB_country_lang col on col.id_country=a.id_country and col.id_lang='. $id_default_lang .'
            WHERE '.$this->getAfterDateWhereCondition().'    
            ORDER BY date_upd
        ';
    }
    
    protected function row2dto($row)
    {
        
        $dto = array(
            'ref' => $row['invoice_id_address'].'_'.$row['delivery_id_address'], // // TODO: Incluir metodo dtoIdStrFromRow en Fs2psAddressUpdateByNifMatcher
            'user' => intval($row['id_customer']),
            'created' => $row['date_add'],
            'updated' => $row['date_upd'],
            'company' => $row['company'],
            'nif' => $row['dni'],
            'vat_number' => $row['vat_number'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['email'],
            'address' => $row['address1'],
            'address2'=>$row['address2'],
            'postcode' => $row['postcode'],
            'city' => $row['city'],
            'country' => $row['country'],
            'country_iso2' => $row['country_iso2'],
            'phone' => $row['phone'],
            'mobile' => $row['phone_mobile'],
            'other'=> $row['other'],
            'customer_vat_number'=> $row['customer_vat_number'],
            'customer_nif'=> $row['customer_nif'],
            'roles' => isset($row['roles']) ? explode(",", $row['roles']): [],
        );
        
        if (!empty($row['state'])) $dto['state'] = $row['state'];

        if (!empty($row['alias'])) $dto['alias'] = $row['alias'];
        
        return $dto;
    }
    
}

class Fs2psOrderExtractor extends Fs2psOrderDependentExtractor
{
    protected $orders_have_payment_fee; // @Deprecated
	protected $payment_fee_field;
	protected $payment_fee_rate_field;
    protected $round_discount_price;
	
	protected $optional_fields = array('address2', 'saddress2', 'other', 'sother');
	
	protected $customerAddressMatcher;
	
	public function __construct($task, $name)
	{
	    parent::__construct($task, $name);
	    $this->customerAddressMatcher = Fs2psMatcherFactory::get($task, 'customer_addresses');
	}
	
	protected function reloadCfg() {
		parent::reloadCfg();
		$cfg = $this->task->cfg;
		
		$this->orders_have_payment_fee = $cfg->get('ORDERS_HAVE_PAYMENT_FEE', false); // @Deprecated
		$this->payment_fees = $cfg->get('ORDERS_PAYMENT_FEES', false);
        $this->round_discount_price = $cfg->get('ROUND_DISCOUNT_PRICE', 2);
	}
	
	protected function paymentFeeSelect() {
	    if ($this->orders_have_payment_fee && empty($this->payment_fees)) {
	        // Old deprecated behaviour
		    return 'o.payment_fee, o.payment_fee_rate,';
        }
        
        $select = array();
        if ($this->payment_fees) {
            foreach ($this->payment_fees as $pf_payment => $pf_cfg) {
                if (!empty($pf_cfg['field'])) $select[] = Fs2psTools::dbFieldName($pf_cfg['field']).' as fs2ps_'.$pf_payment.'_payfee';
                if (!empty($pf_cfg['tax_field'])) $select[] = Fs2psTools::dbFieldName($pf_cfg['tax_field']).' as fs2ps_'.$pf_payment.'_payfeerate';
            }
        }

        return join(', ', $select).($select? ', ' : '');
	}
	
	protected function buildSql()
	{
		
		$id_default_lang = Configuration::get('PS_LANG_DEFAULT');
		
		$sql =  '
			select 
				o.id_order, o.reference,
                o.id_shop, 
				o.invoice_number,
                o.invoice_date,  
				o.date_add, o.date_upd,
				o.total_products as total_products_tax_excl,
                o.total_products_wt as total_products_tax_incl,
				o.carrier_tax_rate,
				o.total_shipping_tax_incl, 
                o.total_shipping_tax_excl,
				o.total_discounts_tax_excl,
                o.total_discounts_tax_incl,
                o.total_paid_tax_incl,
				o.valid,
				o.current_state,

                IF(cad.id_customer is null, c.id_customer, cad.id_customer) as id_customer, -- Preferible id cliente de direccion fiscal para evitar problemas con pedidos de AMZ
                IF(cad.id_customer is null, c.email, cad.email) as email,
			    IFNULL(a.firstname, IF(cad.id_customer is null, c.firstname, cad.firstname)) as firstname,
                IFNULL(a.lastname, IF(cad.id_customer is null, c.lastname, cad.lastname)) as lastname,

				a.company, a.vat_number, a.dni,
				
                a.id_address as address_id,
			    a.address1, a.address2, a.postcode, 
			    a.city, a.phone, a.phone_mobile,
				st.name as state, col.name as country,
                co.iso_code as country_iso2,
                a.other as other,
				
                IFNULL(sa.firstname, IF(csad.id_customer is null, c.firstname, csad.firstname)) as sfirstname,
                IFNULL(sa.lastname, IF(csad.id_customer is null, c.lastname, csad.lastname)) as slastname,
                sa.id_address as saddress_id,
				sa.address1 as saddress1, sa.address2 as saddress2, sa.postcode as spostcode, 
			    sa.city as scity, sa.phone as sphone, sa.phone_mobile as sphone_mobile,
				sst.name as sstate, sco.name as scountry,
                sa.other as sother,
                sa.company as scompany,
				
				'.$this->paymentFeeSelect().'
				o.module as payment,
                o.payment as payment_descrip,

                (select min(id_message) from @DB_message msg where msg.id_order=o.id_order and msg.private=0) as id_message,
                
                e.id_employee,
                (
					select GROUP_CONCAT(DISTINCT id_group)
					FROM @DB_customer_group WHERE id_customer = c.id_customer
                    GROUP BY id_customer
                ) AS roles,

                ca.id_reference as carrier, 
                ca.name as carrier_descrip,

                (SELECT GROUP_CONCAT(name) FROM @DB_order_cart_rule where id_order=o.id_order group by id_order) as coupons,
                (SELECT sum(opay.amount) FROM @DB_order_payment opay WHERE opay.order_reference=o.reference) as total_paid
				
			FROM
				@DB_orders o
				left join @DB_customer c on c.id_customer = o.id_customer
				
			    left join @DB_address a on a.id_address = o.id_address_invoice
                left join @DB_customer cad on cad.id_customer = a.id_customer

				left join @DB_state st on st.id_state=a.id_state
                left join @DB_country co on co.id_country=a.id_country
			    left join @DB_country_lang col
			        on col.id_country=a.id_country and col.id_lang='.$id_default_lang.'
							
				left join @DB_address sa on sa.id_address = o.id_address_delivery
                left join @DB_customer csad on csad.id_customer = sa.id_customer

				left join @DB_state sst on sst.id_state=sa.id_state
				left join @DB_country_lang sco
					on sco.id_country=sa.id_country and sco.id_lang='.$id_default_lang.'

				left join (
                    SELECT 
                        oh.id_order, 
                        substring_index(GROUP_CONCAT(oh.id_employee ORDER BY oh.id_order_history),\',\', 1) AS id_employee
                    FROM 
                        `@DB_orders` o
                        inner join `@DB_order_history` oh on oh.id_order=o.id_order
                    WHERE '.$this->getAfterDateWhereCondition().'
                    GROUP by id_order
                ) e on e.id_order=o.id_order

                left join `@DB_carrier` ca on ca.id_carrier=o.id_carrier
							
			WHERE
                -- cfillol XXX: Evitamos descargar pedidos sin cliente (borrado)
                (c.id_customer is not null or cad.id_customer is not null) and 

				'.$this->getAfterDateWhereCondition().'

			ORDER BY o.date_upd, o.id_order
		';
		
		return $sql;
	}
	
	
	protected function buildTotalsSql($id, $id_order_slip=null)
	{
	    $sql =  '
            SELECT
                tax,
                sum(line_tax) as line_tax,
                sum(line_base) as line_base,
                sum(quantity) as quantity
            FROM (
            	SELECT
            		sum(t.rate) as tax,
					od.total_price_tax_incl - od.total_price_tax_excl as line_tax,
            		od.total_price_tax_excl as line_base,
            		od.product_quantity as quantity
            	FROM
            		@DB_order_detail od
            		left join @DB_order_detail_tax odt on odt.id_order_detail=od.id_order_detail
            		left join @DB_tax t on t.id_tax=odt.id_tax
            	WHERE od.id_order='.$id.'
            	GROUP BY od.id_order_detail
            ) tt
            GROUP BY tax
            ORDER BY tax
		';
	    
	    return $sql;
	}
	
	protected function getOrderTotals($row)
	{
	    
        $id_order_slip = empty($row['id_order_slip'])? null : $row['id_order_slip'];
        $totals_rows = Fs2psTools::dbSelect($this->buildTotalsSql($row['id_order'], $id_order_slip));       

		$dec = 1000; // decimales de precisión para impuestos
        
		$taxes_by_rate = array();
		$lines_tax_amounts_by_tax = array();
		$lines_bases_by_tax = array();
		$shipping_tax_amounts_by_tax = array();
		$shipping_bases_by_tax = array();
        
        $discount_methods = array('tax_excl', 'tax_incl');
        $discount_tax_amounts_by_tax = array();
		$discount_bases_by_tax = array();
        foreach($discount_methods as $dm) {
            $discount_tax_amounts_by_tax[$dm] = array();
		    $discount_bases_by_tax[$dm] = array();
        }
		
		$carrier_tax_rate = null;
		if ($row['total_shipping_tax_excl']>0) {
    		$carrier_tax_rate = floatval($row['carrier_tax_rate']);
    		$carrier_tax_rate_key = intval($carrier_tax_rate * $dec);
    		$taxes_by_rate[$carrier_tax_rate_key] = $carrier_tax_rate;
    		$lines_tax_amounts_by_tax[$carrier_tax_rate_key] = 0;
    		$lines_bases_by_tax[$carrier_tax_rate_key] = 0;
    		$shipping_tax_amounts_by_tax[$carrier_tax_rate_key] = 0;
    		$shipping_bases_by_tax[$carrier_tax_rate_key] = 0;
            foreach($discount_methods as $dm) {
                $discount_tax_amounts_by_tax[$dm][$carrier_tax_rate_key] = 0;
                $discount_bases_by_tax[$dm][$carrier_tax_rate_key] = 0;
            }
		}
		
        $discount_ratio = array();
        foreach($discount_methods as $dm) {
            $total_products = floatval($row['total_products_'.$dm]);
            $discount_ratio[$dm] = $total_products>0? ($total_products-floatval($row['total_discounts_'.$dm]))/$total_products : 1;
        }

		foreach ($totals_rows as $totals_row)
		{
			$rate = floatval($totals_row['tax']);
			$rate_key = intval($rate * $dec);
			$taxes_by_rate[$rate_key] = $rate;
			
			$lines_bases_by_tax[$rate_key] = floatval($totals_row['line_base']);
            $lines_tax_amounts_by_tax[$rate_key] = floatval($totals_row['line_tax']);

            foreach($discount_methods as $dm) {
			    $discount_bases_by_tax[$dm][$rate_key] = Tools::ps_round($lines_bases_by_tax[$rate_key] * (1 - $discount_ratio[$dm]), $this->round_discount_price);
                $discount_tax_amounts_by_tax[$dm][$rate_key] = Tools::ps_round($lines_tax_amounts_by_tax[$rate_key] * (1 - $discount_ratio[$dm]), $this->round_discount_price);
            }
			
			// Nos aseguramos de que estén inicializadas a 0 otras tasas distintas a la del transporte
			$shipping_tax_amounts_by_tax[$rate_key] = 0; 
			$shipping_bases_by_tax[$rate_key] = 0;
		}
		if (!is_null($carrier_tax_rate)) {
    		$shipping_tax_amounts_by_tax[$carrier_tax_rate_key] = floatval($row['total_shipping_tax_incl']) - floatval($row['total_shipping_tax_excl']);
    		$shipping_bases_by_tax[$carrier_tax_rate_key] = floatval($row['total_shipping_tax_excl']);
		}
	
		$taxes = array();
		$lines_tax_amounts = array();
		$lines_bases = array();
		$shipping_tax_amounts = array();
		$shipping_bases = array();
        $discount_tax_amounts = array();
        $discount_bases = array();
        foreach($discount_methods as $dm) {
            $discount_tax_amounts[$dm] = array();
		    $discount_bases[$dm] = array();
        }
		
		krsort($taxes_by_rate); // Bigger tax first in totals
		foreach ($taxes_by_rate as $rate_key => $v)
		{
		    $taxes[] = $rate_key/$dec;
			$lines_tax_amounts[] = $lines_tax_amounts_by_tax[$rate_key];
			$lines_bases[] = $lines_bases_by_tax[$rate_key];
			$shipping_tax_amounts[] = $shipping_tax_amounts_by_tax[$rate_key];
			$shipping_bases[] = $shipping_bases_by_tax[$rate_key];
            foreach($discount_methods as $dm) {
                $discount_tax_amounts[$dm][] = $discount_tax_amounts_by_tax[$dm][$rate_key];
			    $discount_bases[$dm][] = $discount_bases_by_tax[$dm][$rate_key];
            }
		}

        // El método de descuento aplicado será el que minimiza el error
		$total = floatval($row['total_paid_tax_incl']);
        $total_calc_nodis = (
		    array_sum($lines_bases) + array_sum($shipping_bases) +
		    array_sum($lines_tax_amounts) + array_sum($shipping_tax_amounts)
		);
        $error = array_fill_keys($discount_methods, 0);
        foreach($discount_methods as $dm) {
            $total_calc = $total_calc_nodis - array_sum($discount_bases[$dm]) - array_sum($discount_tax_amounts[$dm]);
            $error[$dm] = abs($total_calc - $total);
        }
        $best_dm = $error['tax_excl']<$error['tax_incl']? 'tax_excl' : 'tax_incl';
        $discount_bases = $discount_bases[$best_dm];
        $discount_tax_amounts = $discount_tax_amounts[$best_dm];
	
		return array(
			'taxes' => $taxes,
			'lines_tax_amounts' => $lines_tax_amounts,
			'lines_bases' => $lines_bases,
		    'discount_tax_amounts' => $discount_tax_amounts,
			'discount_bases' => $discount_bases,
			'shipping_tax_amounts' => $shipping_tax_amounts,
			'shipping_bases' => $shipping_bases,
			'total' => $total
		);
	}
	
	protected function row2dto($row)
	{
		$totals = $this->getOrderTotals($row);
					
		$dto = array(
			'id' => intval($row['id_order']),
            'id_shop' => intval($row['id_shop']),
			'ref' => $row['reference'],
			'created' => $row['date_add'],
			'updated' => $row['date_upd'],
			'taxes' => $totals['taxes'],
		    'discount_tax_amounts' => $totals['discount_tax_amounts'],
			'discount_bases' => $totals['discount_bases'],
			'lines_tax_amounts' => $totals['lines_tax_amounts'],
			'lines_bases' => $totals['lines_bases'],
			'shipping_tax_amounts' => $totals['shipping_tax_amounts'],
			'shipping_bases' => $totals['shipping_bases'],
			'total' => $totals['total'],
				
			'company' => empty($row['company'])? $row['firstname'].' '.$row['lastname'] : $row['company'],
		    'firstname' => $row['firstname'],
		    'lastname' => $row['lastname'],
			'user' => intval($row['id_customer']),
			'nif' => $row['dni'],
			'vat_number' => $row['vat_number'],
				
			'address' => $row['address1'],
			'postcode' => $row['postcode'],
			'city' => $row['city'],
			'state' => $row['state'],
			'country' => $row['country'],
		    'country_iso2'=>$row['country_iso2'],
			'phone' => $row['phone'],
			'mobile' => $row['phone_mobile'],
			'email' => $row['email'],
				
            'sfirstname' => $row['sfirstname'],
		    'slastname' => $row['slastname'],

			'saddress' => $row['saddress1'],
			'spostcode' => $row['spostcode'],
			'scity' => $row['scity'],
			'sstate' => $row['sstate'],
			'scountry' => $row['scountry'],
			'sphone' => $row['sphone'],
			'smobile' => $row['sphone_mobile'],
            'scompany'=> $row['scompany'],
				
			'valid' => intval($row['valid'])==1,
			'payment' => $row['payment'],
		    'payment_descrip' => $row['payment_descrip'],
            'total_paid' => floatval($row['total_paid']),
		    'agent' => intval($row['id_employee']),
		    'carrier' => intval($row['carrier']),
		    'carrier_descrip' => $row['carrier_descrip'],
			'current_state' => !empty($row['current_state']) ? strval($row['current_state']) : '',
            'other' => $row['other'],
            'roles'=> isset($row['roles']) ? explode(",", $row['roles']): [],
		);
		
		if ($row['invoice_number']) {
		  $dto['invoice'] = intval($row['invoice_number']);
		  $dto['invoice_date'] = $row['invoice_date'];
		}

        if (!empty($row['id_order_slip'])) {
            $dto['return_id'] = intval($row['id_order_slip']);
        }
		
		if ($row['saddress_id']) {
		    $saddress_dto_id = $this->customerAddressMatcher->dtoIdStrFromRowId($row['saddress_id']);
		    if ($saddress_dto_id) $dto['saddress_ref'] = $saddress_dto_id;
		}
		/* TODO cfillol: Revisar
		if (!empty($row['saddress_id'])) {
		    $saddress_dto_id = $this->customerAddressMatcher->dtoIdStrFromRowId($row['saddress_id']);
		    if ($saddress_dto_id) $dto['saddress_rref'] = $saddress_dto_id;
		    else if (!empty($row['address_id']))  {
		        $dto['saddress_ref'] = $row['address_id'].'_'.$row['saddress_id']; // // TODO: Incluir metodo dtoIdStrFromRow en Fs2psAddressUpdateByNifMatcher
		    }
		}
		*/
		
		foreach ($this->optional_fields as $of) {
            if (!empty($row[$of])) $dto[$of] = $row[$of];
		}
		
		$payment = $dto['payment'];
		if (!empty($this->payment_fees[$payment])) {
		    if (isset($row['fs2ps_'.$payment.'_payfee'])) {
    		    $dto['payment_fee'] = floatval($row['fs2ps_'.$payment.'_payfee']);
    		}
    		if (isset($row['fs2ps_'.$payment.'_payfeerate'])) {
    		    $dto['payment_fee_rate'] = floatval($row['fs2ps_'.$payment.'_payfeerate']);
    		}
		} else if ($this->orders_have_payment_fee) { // Deprecated
		    $dto['payment_fee'] = floatval($row['payment_fee']);
		    $dto['payment_fee_rate'] = floatval($row['payment_fee_rate']);
		}
		
		if (!empty($row['id_message'])) {
		    $dto['note'] = Fs2psTools::dbValue('select message from `@DB_message` where id_message='.$row['id_message'].'');
		}
		
		if ($this->getcustref) {
		    $dto_id_str = $this->customerMatcher->dtoIdStrFromRowId(intval($row['id_customer']));
		    if ($dto_id_str) $dto['customer_ref'] = $dto_id_str;
		}
		
		// Obtenemos array de cupones descartando vacíos si los hubiera
        if (isset($row['coupons'])){
            $coupons = array_filter(preg_split("/ *(, *)+/", $row['coupons']));
            if (!empty($coupons)) $dto['coupons'] = $coupons;
        }

		return $dto;
	}
}


class Fs2psOrderLineExtractor extends Fs2psOrderDependentExtractor
{
    
    protected $name_pattern;
    protected $name_pattern_langs;
    protected $name_pattern_attributes_langs;
    protected $name_pattern_attributes_fields;
    
    protected $productDto2RowMatcher;
    protected $combinationDto2RowMatcher;

    protected $download_product_customs;

    protected $order_lines_order_by;
    
    protected $orders_break_down_pack_lines;
    
    protected function reloadCfg() {
        parent::reloadCfg();
        $cfg = $this->task->cfg;
        
        $this->name_pattern = $cfg->get('ORDER_LINES_NAME_PATTERN');
        $this->download_product_customs = $cfg->get('DOWNLOAD_PRODUCT_CUSTOMS');
        
        $match = null;
        preg_match_all('/(?:name|descrip|attributes)_([a-z]{2,3})/', $this->name_pattern, $match);
        $this->name_pattern_langs = array_unique($match[1]);
        
        preg_match_all('/attributes_([a-z]{2,3})/', $this->name_pattern, $match);
        $this->name_pattern_attributes_fields = array_unique($match[0]);
        $this->name_pattern_attributes_langs = array_unique($match[1]);

        $this->order_lines_order_by = $cfg->get('ORDER_LINES_ORDER_BY');

        $this->orders_break_down_pack_lines = $cfg->get('ORDERS_BREAK_DOWN_PACK_LINES');

    }

	public function __construct($task, $name)
	{
		parent::__construct($task, $name);
		$this->productDto2RowMatcher = Fs2psMatcherFactory::get($task, 'products');
		$this->combinationDto2RowMatcher = Fs2psMatcherFactory::get($task, 'combinations');
	}
	
	protected function buildSqlMultilang($lcods, $tpl, $sep='')
	{
	    if (empty($lcods)) return '';
	    $text = array();
	    $vars = array('$lcod' => NULL);
	    foreach ($lcods as $lcod) {
	        if ($lcod=='ord') continue;
	        $vars['$lcod'] = $lcod;
	        $text[] = strtr($tpl, $vars);
	    }
	    return implode($sep, $text);;
	}
	
	protected function buildSql()
	{
        $sorting = version_compare(_PS_VERSION_, '1.7.7.1') >= 0 ? 'DESC': '';
        if ($this->order_lines_order_by) $sorting = $this->order_lines_order_by; 

	    $lcods = $this->name_pattern_langs;
	    
		$sql = '
			select
                od.id_order_detail,
				o.id_order,
				od.id_order_invoice,
                od.product_id,
				od.product_attribute_id,
				od.product_reference,
				od.product_name,
                '.($this->orders_break_down_pack_lines ? 'p.product_type' : 'null as product_type').',
           '.($this->download_product_customs ? 
                ((version_compare(_PS_VERSION_, '1.7.0.0') <0) 
                    ? // Version 1.6 o inferior
                        '
                            (
                                select group_concat(concat(cfl.name, \'==\', cd.value) SEPARATOR \'||\') customization
                                FROM @DB_customization cus
                                inner JOIN @DB_customized_data cd on cd.id_customization=cus.id_customization
                                inner join @DB_customization_field cf on cf.id_customization_field = cd.index and cf.type = 1
                                inner join @DB_customization_field_lang cfl on cfl.id_customization_field = cd.index and cfl.id_lang=o.id_lang
                                WHERE cus.id_cart=o.id_cart and cus.id_product = od.product_id  
                            ) as customization,
                        '
                    : // Version 1.7 o superior
                        '
                            (
                                select group_concat(concat(cfl_ord.name, \'==\', cd.value) SEPARATOR \'||\') customization 
                                from @DB_customized_data cd
                                left join @DB_customization_field_lang cfl_ord on cfl_ord.id_customization_field = cd.index
                                where cd.id_customization = od.id_customization and cfl_ord.id_lang=o.id_lang
                            ) as customization,
                        ' 
                )
            : '').'
                o.id_lang as lord,
				pl_ord.name as name_ord,
                pl_ord.description_short as descrip_ord,
           '.$this->buildSqlMultilang($lcods, '
				pl_$lcod.name as name_$lcod,
                pl_$lcod.description_short as descrip_$lcod,
           ').'
				od.product_quantity,
				sum(t.rate) as rate,
				od.original_product_price,
				od.unit_price_tax_incl,
				od.unit_price_tax_excl,
				od.reduction_percent,
				od.total_price_tax_incl,
				sum(odt.unit_amount) as unit_amount,
				sum(odt.total_amount) as total_amount
			FROM
				@DB_orders o
                inner join @DB_customer c on c.id_customer = o.id_customer  -- cfillol XXX: Evitamos descargar pedidos sin cliente (borrado)
				left join @DB_order_detail od on od.id_order=o.id_order
				left join @DB_order_detail_tax odt on odt.id_order_detail=od.id_order_detail
				left join @DB_tax t on t.id_tax=odt.id_tax
				left join @DB_product_lang pl_ord on pl_ord.id_product=od.product_id and pl_ord.id_lang=o.id_lang and pl_ord.id_shop=o.id_shop
                left join @DB_product p on p.id_product=od.product_id 
           '.$this->buildSqlMultilang($lcods, '
				left join @DB_lang l_$lcod on l_$lcod.iso_code=\'$lcod\'
				left join @DB_product_lang pl_$lcod on pl_$lcod.id_product=od.product_id and pl_$lcod.id_lang=l_$lcod.id_lang and pl_$lcod.id_shop=o.id_shop
           ').'
			WHERE
				'.$this->getAfterDateWhereCondition().'
            GROUP BY od.id_order_detail
			ORDER BY
				o.date_upd, o.id_order, od.id_order_detail '.$sorting;

		/*		NOTE cfillol: No lo usamos. Cogeremos la ref de attributos de la product_reference
		 		left join @DB_product_attribute pa on pa.id_product_attribute=od.product_attribute_id
				left join @DB_product_attribute_combination pac on pac.id_product_attribute=pa.id_product_attribute
				left join @DB_fs2ps_match am on am.row_id=pac.id_attribute and am.table=\'attribute\'
		 */
		
		return $sql;
	}
	
	
	protected function getAttributesMultilang($lcods, $lord, $product_attribute_id, $sep=' ')
	{
        $attributes_by_lang = array();
        if (!empty($this->name_pattern_attributes_fields)) {
            $rows = Fs2psTools::dbSelect('
    		    select
                  '.$this->buildSqlMultilang($lcods, '
                    al_$lcod.name as attribute_$lcod,
                  ').'
                    al_ord.name as attribute_ord
    		    from @DB_product_attribute_combination pac
    		    left join @DB_attribute a on a.id_attribute=pac.id_attribute
                left join @DB_attribute_lang al_ord on al_ord.id_attribute=a.id_attribute and al_ord.id_lang='.$lord.'
    		  '.$this->buildSqlMultilang($lcods, '
    		    left join @DB_lang l_$lcod on l_$lcod.iso_code=\'$lcod\'
                left join @DB_attribute_lang al_$lcod on al_$lcod.id_attribute=a.id_attribute and al_$lcod.id_lang=l_$lcod.id_lang
              ').'
                where pac.id_product_attribute='.$product_attribute_id.'
    		');
            foreach ($this->name_pattern_attributes_langs as $attribute_lang) {
                $attributes = array();
                foreach ($rows as $row) $attributes[] = $row['attribute_'.$attribute_lang];
                $attributes_by_lang['attributes_'.$attribute_lang] = implode($sep, $attributes);
            }
        }
        return $attributes_by_lang;
	}
	
	protected function getTextMultilang($row)
	{
	    if ($this->name_pattern) {
    	    $lcods = $this->name_pattern_langs;
    	    if (!empty($row['product_attribute_id'])) {
    	        $row = array_merge($row, $this->getAttributesMultilang($lcods, $row['lord'], $row['product_attribute_id']));
    	    } else {
    	        foreach ($this->name_pattern_attributes_fields as $field) {
    	            if (!isset($row[$field])) $row[$field] = '';
    	        }
    	    }
    	    return Fs2psTools::htmlToPlainText(strtr($this->name_pattern, $row));
	    } else {
	        return $row['product_name'];
	    }
	}
	
	protected function row2dto($row)
	{
	    // Cuidado! product_id o product_attribute_id pueden venir vacíos!! XQ?!!
	    if (intval($row['product_attribute_id'])) {
	        $m = $this->combinationDto2RowMatcher;
	        $dto_id_str = $m->dtoIdStrFromRowId($row['product_attribute_id']);
	        if (!$dto_id_str) $dto_id_str = $row['product_reference']; // Puede que queden por descargar pedidos anteriores al cambio de matcher
	        $dto_id = $m->strToDtoId($dto_id_str);
	    } else {
	        $m = $this->productDto2RowMatcher;
	        $dto_id = $m->dtoIdStrFromRowId($row['product_id']);
	        if (!$dto_id) $dto_id = $row['product_reference']; // Puede que queden por descargar pedidos anteriores al cambio de matcher
	    }
		
	    if ($dto_id===null) $dto_id = '';
		$is_array = is_array($dto_id);
		if ($is_array && sizeof($dto_id)!=3) {
		    $dto_id = $row['product_reference']; // Sólo combis pueden ser arrays y deben ser de 3 elemns.
		    $is_array = false;
		}
		
		///////
		// OJO!!! Puede haber un descuadre considerable la suma de las bases de las lineas y la base total de la factura.
        // Esto sucede cuando se calculan impuestos sobre cada unidad vendida y no sobre el total de la linea. 
		// Se suele hacer así cuando se vende incluyendo el IVA en el precio. Además, se pagan menos impuestos.
		// Cambiamos cálculos por valores en BD a partir de 2.5.2, 2.7.1, 2.6.2
		
		// product_price solía ser el precio unitario base real, con todos los decimales y sin descuento
		// A partir de la versión 1.5.3 se introdujo original_product_price justo para eso y product_price quedó obsoleto
		// tomando el valor de unit_price_tax_excl unas veces y otras el de original_product_price
		$product_price_tax_excl = floatval($row['original_product_price']);
		$qty = floatval($row['product_quantity']);
		$tax = floatval($row['rate']);
		
		// Incorporado a partir de 2.5.2, 2.7.1, 2.6.2
		$unit_base_orig = Tools::ps_round($product_price_tax_excl, 2); // cfillol XXX Redondeo: Quitamos redondeo o lo hacemos configurable? Hay usuarios que usan más de 2 decimales en FS
		$unit_base = floatval($row['unit_price_tax_excl']);
		if ($unit_base_orig<$unit_base) $unit_base_orig = $unit_base; // Ignoramos descuentos negativos
        $unit_base_disc = $unit_base_orig - $unit_base; // cfillol: XXX Redondeo: $unit_base_disc = Tools::ps_round($unit_base_orig - $unit_base, 2); // Quitamos redondeo o lo hacemos configurable? Si redondeamos la base, también debemos redondear el descuento sobre la base
		$unit_tax = floatval($row['unit_amount']); // floor($after_discount_price_tax_incl * (1 - 100/(100+$tax)) * 100) / 100 // No se redondea, se trunca para pagar menos impuestos = row['unit_price_tax_incl'] - row['unit_price_tax_excl'] = row['unit-amount']
		
		$unit_price_orig = Tools::ps_round($product_price_tax_excl*(1 + $tax/100), 2);
		$unit_price = floatval($row['unit_price_tax_incl']); // $unit_base + $unit_tax;
		if ($unit_price_orig<$unit_price) $unit_price_orig = $unit_price; // Ignoramos descuentos negativos
		$unit_price_disc = $unit_price_orig - $unit_price;
		$discount_perc = $unit_price_disc>0? floatval($row['reduction_percent']) : 0.0;
		
		$line_price = floatval($row['total_price_tax_incl']); // after_discount_total_tax_incl
		$line_tax = floatval($row['total_amount']); // $unit_tax * $qty; // = $row['total_amount']

        $customization = [];
        if ($this->download_product_customs && !empty($row['customization'])){
            foreach (explode('||', $row['customization']) as $c){
                $cs = explode("==", $c);
                $customization[$cs[0]] = $cs[1];
            }
        }

        $dtos = [];
        $parent_dto =  array(
			'order' => intval($row['id_order']),
			// invoice => intval($row['id_order_invoice']),
			'ref' => $is_array? $dto_id[0] : $dto_id,
		    'name' => $this->getTextMultilang($row), // $row['product_name'],
			'size' => $is_array? $dto_id[1] : '',
			'color' => $is_array? $dto_id[2] : '',

			'quantity' => $qty,
			'tax' => $tax,
				
			'unit_base_orig' => $unit_base_orig,
			'unit_base_disc' => $unit_base_disc,
			'discount_perc' => $discount_perc,
			// unit_base => unit_base_orig - unit_base_disc,
			// line_base = unit_base * quantity
			'unit_tax_imp' => $unit_tax, // unit_tax
				
			// Se usaban antes de 2.5.2, 2.7.1, 2.6.2 ¿Harán falta después? Los conservamos por compat con 2.X
			'price' => $unit_price_orig,
			'discount' => $unit_price_disc,
			'total' => $line_price, // line_price = unit_price * quantity
			'tax_imp' => $line_tax, // line_tax = unit_tax * quantity
		);
        if (!empty($row['product_type'])) {
            $parent_dto['product_type'] = $row['product_type'];
        }
        if (!empty($row['id_order_slip'])) {
            $parent_dto['return_id'] = intval($row['id_order_slip']);
        }
        if (!empty($customization)){
            $parent_dto['customization'] = $customization;
        }
        array_push($dtos, $parent_dto);

        if ($this->orders_break_down_pack_lines == true && $row['product_type'] == 'pack') {
            // Es un producto con packs, hay que desglosar las lineas

            $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
            $pack_sql = '
                select distinct pk.id_product_item as id_product, pk.quantity, p.name
                from @DB_pack pk
                left join @DB_product_lang p on p.id_product = pk.id_product_item and id_lang='.$id_default_lang.'
                where pk.id_product_pack ='.$row['product_id'];
            $rows_pk = Fs2psTools::dbSelect($pack_sql);

            // Añadimos el desgose
            $desglose = array(
                'order' => intval($row['id_order']),
                'ref' => $parent_dto['ref'],
                'name' => '        DESGLOSE PACK',
                'quantity' => - $qty,

                // unused values
                'size' => '',
                'color' => '',
                'tax' => 0,
                'unit_base_orig' => 0,
                'unit_base_disc' => 0,
                'discount_perc' => 0,
                'unit_tax_imp' => 0,
                'price' => 0,
                'discount' => 0,
                'total' => 0,
                'tax_imp' => 0,
                'product_type' => 'pack_desglose',
            );
            array_push($dtos, $desglose);

            // Sacamos los productos del pack
            foreach($rows_pk as $row_pk) {
                $pk_dto_id = $m->dtoIdStrFromRowId($row_pk['id_product']);
                $pk_dto = array(
                    'order' => intval($row['id_order']),
                    'ref' => $is_array? $pk_dto_id[0] : $pk_dto_id,
                    'name' => '        '.$row_pk['name'],
                    'quantity' => floatval($row_pk['quantity'])*$qty,
                    'tax' => $tax,

                    // unused values
                    'size' => '',
                    'color' => '',
                    'unit_base_orig' => 0,
                    'unit_base_disc' => 0,
                    'discount_perc' => 0,
                    'unit_tax_imp' => 0,
                    'price' => 0,
                    'discount' => 0,
                    'total' => 0,
                    'tax_imp' => 0,
                    'product_type' => 'pack_child',
                );
                
                array_push($dtos, $pk_dto);
            }
        }
        return $dtos;
	}
}

class Fs2psReturnExtractor extends Fs2psOrderExtractor
{

    protected function getAfterDateWhereCondition()
	{
        // Nos basamos en las fechas de las devoluciones no en las de los pedidos
        return preg_replace('/o.(date_[a-z]+ *)>/', 'ore.$1>', parent::getAfterDateWhereCondition());
	}

    protected function buildSql()
    {
        
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        
        $sql =  '
            select
                o.id_order, 
                ore.id_order_slip,
                o.reference,
                o.id_shop,
                o.invoice_number,
                o.invoice_date,
                ore.date_add, ore.date_upd,

                ore.total_products_tax_excl,
                ore.total_products_tax_incl,
                o.carrier_tax_rate,
                ore.total_shipping_tax_incl, 
                ore.total_shipping_tax_excl,
                0 as total_discounts_tax_excl,
                0 as total_discounts_tax_incl,
                ore.amount as total_paid_tax_incl,

                o.valid,
                o.current_state,
                IF(cad.id_customer is null, c.id_customer, cad.id_customer) as id_customer, -- Preferible id cliente de direccion fiscal para evitar problemas con pedidos de AMZ
                IF(cad.id_customer is null, c.email, cad.email) as email,
                IFNULL(a.firstname, IF(cad.id_customer is null, c.firstname, cad.firstname)) as firstname,
                IFNULL(a.lastname, IF(cad.id_customer is null, c.lastname, cad.lastname)) as lastname,
            
                a.company, a.vat_number, a.dni,
            
                a.id_address as address_id,
                a.address1, a.address2, a.postcode,
                a.city, a.phone, a.phone_mobile,
                st.name as state, col.name as country,
                co.iso_code as country_iso2,
                a.other as other,

                IFNULL(sa.firstname, IF(csad.id_customer is null, c.firstname, csad.firstname)) as sfirstname,
                IFNULL(sa.lastname, IF(csad.id_customer is null, c.lastname, csad.lastname)) as slastname,
                sa.id_address as saddress_id,
                sa.address1 as saddress1, sa.address2 as saddress2, sa.postcode as spostcode,
                sa.city as scity, sa.phone as sphone, sa.phone_mobile as sphone_mobile,
                sst.name as sstate, sco.name as scountry,
                sa.other as sother,
                sa.company as scompany,
            
                '.$this->paymentFeeSelect().'
                o.module as payment,
                o.payment as payment_descrip,
                    
                (select min(id_message) from @DB_message msg where msg.id_order=o.id_order and msg.private=0) as id_message,
                    
                e.id_employee,
                (
                    select GROUP_CONCAT(DISTINCT id_group)
                    FROM @DB_customer_group WHERE id_customer = c.id_customer
                    GROUP BY id_customer
                ) AS roles,
                ca.id_reference as carrier,
                ca.name as carrier_descrip,
                    
                (SELECT GROUP_CONCAT(name) FROM @DB_order_cart_rule where id_order=o.id_order group by id_order) as coupons,
                (SELECT sum(opay.amount) FROM @DB_order_payment opay WHERE opay.order_reference=o.reference) as total_paid
                
            FROM
                @DB_orders o
                inner join @DB_customer c on c.id_customer = o.id_customer
                    
                left join @DB_address a on a.id_address = o.id_address_invoice
                left join @DB_customer cad on cad.id_customer = a.id_customer
                    
                left join @DB_state st on st.id_state=a.id_state
                left join @DB_country co on co.id_country=a.id_country
                left join @DB_country_lang col
                    on col.id_country=a.id_country and col.id_lang='.$id_default_lang.'
                        
                left join @DB_address sa on sa.id_address = o.id_address_delivery
                left join @DB_customer csad on csad.id_customer = sa.id_customer

                left join @DB_state sst on sst.id_state=sa.id_state
                left join @DB_country_lang sco
                    on sco.id_country=sa.id_country and sco.id_lang='.$id_default_lang.'
                        
                left join (
                    SELECT
                        oh.id_order,
                        substring_index(GROUP_CONCAT(oh.id_employee ORDER BY oh.id_order_history),\',\', 1) AS id_employee
                    FROM
                        `@DB_orders` o
                        inner join `@DB_order_history` oh on oh.id_order=o.id_order
                    WHERE '.parent::getAfterDateWhereCondition().'
                    GROUP by id_order
                ) e on e.id_order=o.id_order
                        
                left join `@DB_carrier` ca on ca.id_carrier=o.id_carrier
                inner join @DB_order_slip ore on ore.id_order=o.id_order
                        
            WHERE
                -- cfillol XXX: Evitamos descargar pedidos sin cliente (borrado)
                (c.id_customer is not null or cad.id_customer is not null) and
                        
                '.$this->getAfterDateWhereCondition().'
            GROUP BY ore.id_order_slip
            ORDER BY ore.date_upd, ore.id_order_slip
        ';
        
        return $sql;
    }
    
    protected function buildTotalsSql($id, $id_order_slip=null)
    {
        $sql =  '
            SELECT
                tax,
                sum(line_tax) as line_tax,
                sum(line_base) as line_base,
                sum(quantity) as quantity
            FROM (
                SELECT distinct ore.id_order_slip,
                    t.rate as tax,
                    ore.total_products_tax_incl - ore.total_products_tax_excl as line_tax,
                    ore.total_products_tax_excl as line_base,
                    oredt.product_quantity as quantity,
                    od.id_order as id_order
                FROM
                    @DB_order_detail od
                    left join @DB_order_detail_tax odt on odt.id_order_detail=od.id_order_detail
                    left join @DB_tax t on t.id_tax=odt.id_tax
                    inner join @DB_order_slip ore on ore.id_order=od.id_order
                    inner join @DB_order_slip_detail oredt on oredt.id_order_slip = ore.id_order_slip
                WHERE ore.id_order='.$id.' and ore.id_order_slip='.$id_order_slip.'
                ORDER BY oredt.id_order_detail
            ) tt
            GROUP BY tax
            ORDER BY tax
        ';

        return $sql;
    }
    
}

class Fs2psReturnLineExtractor extends Fs2psOrderLineExtractor
{

    protected function getAfterDateWhereCondition()
	{
        // Nos basamos en las fechas de las devoluciones no en las de los pedidos
        return preg_replace('/o.(date_[a-z]+ *)>/', 'ore.$1>', parent::getAfterDateWhereCondition());
	}

    protected function buildSql()
    {
        $lcods = $this->name_pattern_langs;
        
        $sql = '
			select
                oredt.id_order_detail,
				min(o.id_order) as id_order,
                min(ore.id_order_slip) as id_order_slip,
				min(od.id_order_invoice) as id_order_invoice,
                min(od.product_id) as product_id,
				min(od.product_attribute_id) as product_attribute_id,
				min(od.product_reference) as product_reference,
				min(od.product_name) as product_name,
                min(o.id_lang) as lord,
				min(pl_ord.name) as name_ord,
                min(pl_ord.description_short) as descrip_ord,
           '.$this->buildSqlMultilang($lcods, '
                min(pl_$lcod.name) as name_$lcod,
                min(pl_$lcod.description_short) as descrip_$lcod,
           ').'
                min(oredt.product_quantity) as product_quantity,
                min(t.rate) as rate,
                min(od.original_product_price) as original_product_price,
                min(od.unit_price_tax_incl) as unit_price_tax_incl,
                min(od.unit_price_tax_excl) as unit_price_tax_excl,
                min(od.reduction_percent) as reduction_percent,
                min(od.total_price_tax_incl) as total_price_tax_incl,
                sum(oredt.unit_price_tax_incl) as unit_amount,
                sum(oredt.amount_tax_incl) as total_amount
			FROM
				@DB_orders o
                left join @DB_customer c on c.id_customer = o.id_customer  -- cfillol XXX: Evitamos descargar pedidos sin cliente (borrado)
                inner join @DB_order_slip ore on ore.id_order=o.id_order
                inner join @DB_order_slip_detail oredt on oredt.id_order_slip = ore.id_order_slip
				left join @DB_order_detail od on od.id_order_detail=oredt.id_order_detail
				left join @DB_order_detail_tax odt on odt.id_order_detail=od.id_order_detail
				left join @DB_tax t on t.id_tax=odt.id_tax
				left join @DB_product_lang pl_ord on pl_ord.id_product=od.product_id and pl_ord.id_lang=o.id_lang and pl_ord.id_shop=o.id_shop
           '.$this->buildSqlMultilang($lcods, '
				left join @DB_lang l_$lcod on l_$lcod.iso_code=\'$lcod\'
				left join @DB_product_lang pl_$lcod on pl_$lcod.id_product=od.product_id and pl_$lcod.id_lang=l_$lcod.id_lang and pl_$lcod.id_shop=o.id_shop
           ').'
			WHERE
				'.$this->getAfterDateWhereCondition().'
            GROUP BY ore.date_upd, ore.id_order_slip, oredt.id_order_detail
			ORDER BY
                ore.date_upd, ore.id_order_slip, oredt.id_order_detail
		';
        
        return $sql;
    }
}

class Fs2psCategoryExtractor extends Fs2psMatchedExtractor
{
    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        $dto['name'] = $row['name'];
        $dto['parent'] = null;
        return $dto;
    }
    
    public function getLevelFromDownloadCfg($download) {
        $matches = array();
        preg_match('/^level\_([0-9]+)$/i', strval($download), $matches);
        return $matches? intval($matches[1]) : null;
    }
    
    protected function buildSqlByLevel($level)
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        return '
            select distinct c.id_category as id, cl.name, c.id_parent as id_parent
            from @DB_category c
            left join @DB_category_lang cl on cl.id_category=c.id_category and cl.id_lang='.$id_default_lang.'
            where c.id_shop_default=1 and c.level_depth='.($level+2).'
            order by id_parent, id
        ';
    }
}

class Fs2psSectionExtractor extends Fs2psCategoryExtractor
{
    protected function buildSql()
    {
        $cfg = $this->task->cfg;
        $level = $this->getLevelFromDownloadCfg($cfg->get('DOWNLOAD_SECTIONS', true));
        if ($level!==null) return $this->buildSqlByLevel($level);
        
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        return '
            select distinct pc.id_category as id, pcl.name, null as id_parent
            from @DB_product p
            inner join @DB_category_product cp on cp.id_product=p.id_product
            inner join @DB_category c on c.id_category=cp.id_category
            left join @DB_category cc on cc.id_parent=c.id_category
            left join @DB_category pc on pc.id_category=c.id_parent and pc.is_root_category=0
            left join @DB_category_lang pcl on pcl.id_category=pc.id_category and pcl.id_lang='.$id_default_lang.'
            where cc.id_category is null and pc.id_category is not null
            '.($this->discard_disabled_products? 'and p.active=1' :'').'
            ORDER BY id_parent, id
        ';
    }
}

class Fs2psFamilyExtractor extends Fs2psCategoryExtractor
{
    protected $sectionMatcher;
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        $this->sectionMatcher = Fs2psMatcherFactory::get($task, 'sections');
    }
    
    protected function buildSql()
    {
        $cfg = $this->task->cfg;
        $level = $this->getLevelFromDownloadCfg($cfg->get('DOWNLOAD_FAMILIES', true));
        if ($level!==null) return $this->buildSqlByLevel($level);
        
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        return '
            select distinct c.id_category as id, cl.name, pc.id_category as id_parent
            from @DB_product p
            inner join @DB_category_product cp on cp.id_product=p.id_product
            inner join @DB_category c on c.id_category=cp.id_category
            inner join @DB_category_lang cl on cl.id_category=c.id_category and cl.id_lang='.$id_default_lang.'
            left join @DB_category cc on cc.id_parent=c.id_category
            left join @DB_category pc on pc.id_category=c.id_parent and pc.is_root_category=0
            where cc.id_category is null
            '.($this->discard_disabled_products? 'and p.active=1' :'').'
            ORDER BY id_parent, id
        ';
    }
    
    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        $dto['parent'] = empty($row['id_parent'])? '' : $this->sectionMatcher->dtoIdStrFromRowId($row['id_parent']);
        return $dto;
    }
}

class Fs2psAttributeGroupExtractor extends Fs2psMatchedExtractor
{
    protected static function matchSql($value) { 
        return '( select \''.$value.'\' as id from dual )';
        //return '( select FIRST(\''.$value.'\') as id from @DB_attribute_group ag where id_attribute_group in ('.$value.') )';
    }
    
    protected function buildSql()
    {
        $forced_matches = $this->matcher->forced_maches;
        if (empty($forced_matches)) {
            throw new Fs2psException("No se indicaron valores IMATCH_".strtoupper($this->name));
        }
        
        $filtered_keys = preg_grep("/^(SIZES)|(COLOURS)$/", array_keys($forced_matches));
        $filtered_values = preg_grep("/^([0-9]+,)*[0-9]+$/", array_values($forced_matches));
        if (sizeof($filtered_keys)<sizeof($forced_matches) || sizeof($filtered_values)<sizeof($forced_matches)) {
            throw new Fs2psException("Se indicaron elementos inválidos en IMATCH_".strtoupper($this->name));
        }
        
        return implode(' UNION ', array_map('self::matchSql', $filtered_values));
    }
}

/*
class Fs2psAttributeExtractor extends Fs2psExtractor
{
    protected $selection;
    protected $rMatcher;
    protected $rNextId;
    
    protected function reloadCfg() {
        parent::reloadCfg();
        
        $selection_cfg_name = strtoupper($this->name).'_REMOTE_SELECTION';
        $selection = $this->task->cfg->get($selection_cfg_name);
        if (!empty($selection) && is_array($selection)) {
            $selection = array_filter($selection, 'is_numeric');
        }
        if (empty($selection)) {
            throw new Fs2psServerFatalException('No se indicó '.$selection_cfg_name);
        }
        $this->selection = $selection;
    }
    
    public static function createRMatcher($task, $name) {
        return new Fs2psDto2RowMatcher('r'.$name, 'product_attribute', 'id_product_attribute', array('ref'), true);
    }
    
    public function __construct($task, $name)
    {
        parent::__construct($task, $name);
        
        $this->rMatcher = self::createRMatcher($task, $name);
        $this->rNextId = Fs2psTools::dbValue('
            select max(cast(dto_id as unsigned))
            from @DB_fs2ps_match
            where `table`=\'product_attribute\' and entity=\'r'.$name.'\'
        ') + 1;
    }
    
    public function getSelection() { return $this->selection; }
    public function getRMatcher() { return $this->rMatcher; }
    
    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        return '
            select
            distinct
                -- GROUP_CONCAT(a.id_attribute_group order by a.id_attribute_group),
                GROUP_CONCAT(a.id_attribute order by a.id_attribute_group) as id,
                GROUP_CONCAT(al.name order by a.id_attribute_group SEPARATOR \'x\' ) as name,
                GROUP_CONCAT(a.color order by a.id_attribute_group) as color
            from @DB_product p
            inner join @DB_product_attribute pa on pa.id_product=p.id_product
            inner join @DB_product_attribute_combination pac on pac.id_product_attribute=pa.id_product_attribute
            inner join @DB_attribute a on a.id_attribute=pac.id_attribute
            inner join @DB_attribute_lang al on al.id_attribute=a.id_attribute and id_lang='.$id_default_lang.'
            where p.active=1 and a.id_attribute_group in ('.join($this->selection, ',').')
            group by pa.id_product_attribute
		';
    }
    
    protected function row2dto($row)
    {
        $rMatcher = $this->rMatcher;
        $ref = $rMatcher->_rowIdFromDtoId($row['id']);
        if (empty($ref)) {
            $ref = $this->rNextId;
            $this->rNextId++;
        }
        $rMatcher->updateReverseMatch($row['id'], $ref);
        
        $dto = array( 'ref' => $ref, 'name' => $row['name'] );
        
        if (strlen($row['color'])>0 && $row['color'][0]=='#') {
            $dto['colour'] = substr(explode(',', $row['color'])[0], 1);
        }
        
        return $dto;
        
    }
}
*/

class Fs2psAttributeExtractor extends Fs2psMatchedExtractor
{

    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        $group_row_ids = $this->task->getExtractor('attribute_groups')->matcher->rowIdFromDtoId(strtoupper($this->name));
        $group_row_ids_str = empty($group_row_ids)? 0 : (is_array($group_row_ids)? implode(',', $group_row_ids) : $group_row_ids);
        
        return '
            select
                a.id_attribute as id,
                al.name,
                a.color
            from @DB_attribute a
            inner join @DB_attribute_lang al on al.id_attribute=a.id_attribute and id_lang='.$id_default_lang.'
            where a.id_attribute_group in ('.$group_row_ids_str.')
		';
    }
    
    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        $dto['name'] = $row['name'];
        if (strlen($row['color'])>0 && $row['color'][0]=='#') {
            $dto['colour'] = substr(explode(',', $row['color'])[0], 1);
        }
        return $dto;
    }
    
}


class Fs2psManufacturerExtractor extends Fs2psMatchedExtractor
{
    protected function buildSql()
    {
        return 'select id_manufacturer as id, name FROM @DB_manufacturer';
    }
    
    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        $dto['name'] = $row['name'];
        return $dto;
    }
}

class Fs2psSupplierExtractor extends Fs2psMatchedExtractor
{
    protected function buildSql()
    {
        return 'select id_supplier as id, name FROM @DB_supplier';
    }
    
    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        $dto['name'] = $row['name'];
        return $dto;
    }
}

class Fs2psProductExtractor extends Fs2psMatchedExtractor
{
    protected $familyMatcher;
    protected $manufacturerMatcher;
    protected $supplierMatcher;
    
    public function __construct($task, $name, $matcher=null)
    {
        parent::__construct($task, $name, $matcher);
        $this->familyMatcher = $task->getExtractor('families')? $task->getExtractor('families')->matcher : null;
        $this->manufacturerMatcher = $task->getExtractor('manufacturers')? $task->getExtractor('manufacturers')->matcher : null;
        $this->supplierMatcher = $task->getExtractor('suppliers')? $task->getExtractor('suppliers')->matcher : null;
    }
    
    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        
        $where = array();
        if (version_compare(_PS_VERSION_, '1.7.0.0') >= 0) $where[] = 'p.state>0';
        if ($this->discard_disabled_products) $where[] = 'p.active=1';

        return '
            select 
                distinct
                p.id_product as id,
                p.date_add,
                p.date_upd,
                pl.name,
                p.active,
                pl.description as longdescrip,
                pl.description_short as descrip,
                pl.meta_title as metatitle,
                pl.meta_description as metadescrip,
                pl.meta_keywords as metakeys,
                pl.link_rewrite as slug,
                p.price,
                ps.wholesale_price,
                p.reference as pref, -- pm.dto_id as pref, 
                sa.quantity as stock, -- s.usable_quantity as stock,
                tax.rate as tax_class,
                id_family,
                id_section,
                weight, 
                -- width, length, height
                ean13,
                id_manufacturer,
                id_supplier
            from @DB_product p
            -- inner join @DB_fs2ps_match pm on pm.row_id=p.id_product and pm.`table`=\'product\'
            inner join @DB_product_shop ps on ps.id_product=p.id_product and ps.id_shop=1 -- Evitamos problemas multitienda?
            inner join @DB_product_lang pl on pl.id_product=p.id_product and pl.id_lang='.$id_default_lang.'
            inner join @DB_stock_available sa on sa.id_product=p.id_product and sa.id_product_attribute=0
            left join (
                select max(t.rate) as rate, tr.id_tax_rules_group 
                from @DB_country c
                left join @DB_tax_rule tr on tr.id_country=c.id_country 
                left join @DB_tax t on t.id_tax=tr.id_tax
                where c.iso_code=\'ES\'
                group by tr.id_tax_rules_group 
            ) as tax on tax.id_tax_rules_group=p.id_tax_rules_group      
            left join (
                select cp.id_product, min(c.id_category) as id_family, min(pc.id_category) as id_section
                from @DB_category_product cp
                inner join @DB_category c on c.id_category=cp.id_category
                left join @DB_category cc on cc.id_parent=c.id_category
                left join @DB_category pc on pc.id_category=c.id_parent and pc.is_root_category=0
                where cc.id_category is null
                group by cp.id_product
            ) ca on ca.id_product=p.id_product
            '.(empty($where)? '' : 'where '.join(" and ", $where)).'
        ';
    }

    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        if(empty($dto)) return $dto;
        
        $dto = array_merge($dto, array(
            'name' => $row['name'],
            'created' => $row['date_add'],
            'updated' => $row['date_upd'],
            'enabled' => $row['active']? true : false,
            'price_base' => floatval($row['price']),
            'cost' => floatval($row['wholesale_price']),
            'stock' => floatval($row['stock']),
            'tax_class' => empty($row['tax_class'])? '0' : strval(floatval($row['tax_class'])),
            'descrip' => empty($row['descrip'])? '' : $row['descrip'],
            'longdescrip' => empty($row['longdescrip'])? '' : $row['longdescrip'],
            'pref' => $row['pref'],
            'ean' => $row['ean13'],
            'weight' => floatval($row['weight']),
        ));
        
        if (isset($row['slug'])) $dto['slug'] = $row['slug'];
        if (isset($row['metatitle'])) $dto['metatitle'] = $row['metatitle'];
        if (isset($row['metadescrip'])) $dto['metadescrip'] = $row['metadescrip'];
        if (isset($row['metakeys'])) $dto['metakeys'] = $row['metakeys'];
        
        if ($this->familyMatcher) {
            $dto['family'] = empty($row['id_family'])? '' : $this->familyMatcher->dtoIdStrFromRowId($row['id_family']);
        }
        if ($this->manufacturerMatcher) {
            $dto['manufacturer'] = empty($row['id_manufacturer'])? '' : $this->manufacturerMatcher->dtoIdStrFromRowId($row['id_manufacturer']);
        }
        if ($this->supplierMatcher) {
            $dto['supplier'] = empty($row['id_supplier'])? '' : $this->supplierMatcher->dtoIdStrFromRowId($row['id_supplier']);
        }

        if (isset($row['iscombi'])) $dto['iscombi'] = $row['iscombi'];
        
        return $dto;
    }
    
}

class Fs2psProductStockExtractor extends Fs2psProductExtractor
{
    protected function row2dto($row)
    {
        $dto = Fs2psMatchedExtractor::row2dto($row);
        if(empty($dto)) return $dto;
        
        $dto = array_merge($dto, array(
            'stock' => floatval($row['stock']),
        ));
        
        return $dto;
    }
}

class Fs2psSpecificPriceExtractor extends Fs2psExtractor {
    public function __construct($task, $name)
    {
        // $name = 'specific_prices';
        parent::__construct($task, $name);
        $this->productMatcher = Fs2psMatcherFactory::get($task, 'products');
    }
    protected function buildSql()
    {
        return  
            '
            SELECT 
                sp.id_product as product,
                sp.price as price,
                sp.reduction as reduction,
                p.price as original_price,
                sp.from_quantity as quantity,
                sp.reduction_type as reduction_type
            
            FROM @DB_specific_price sp 
            inner join @DB_product p on p.id_product=sp.id_product 

            ';
    }

    protected function row2dto($row)
    {
        $product = $this->productMatcher->dtoIdFromRowId(intval($row['product']));

        $dto = [
            'product'=> $product,
            #Cuando price=-1 significa que han marcado la casilla 'Mantener precio original'
            'price'=> $row['price']==-1 ? $row['original_price'] : $row['price'],
            'original_price'=> $row['original_price'],
            'quantity' => $row['quantity'],
        ];

        if($row['reduction_type'] == 'amount'){
            $dto['dis'] = $row['price'];
        }
        else{
            #$row['reduction_type'] == 'percentage';
            $dto['disp'] = $row['reduction'] * 100;
        }
        
        return $dto;
    }
}

class Fs2psProductImageExtractor extends Fs2psExtractor {

    protected $after;
    protected $after_timestamp;
    protected $cover;

    public function __construct($task, $name)
    {
        $this->after = (new DateTime())->setTimestamp(0);
        $this->after_timestamp = 0;
        $this->task = $task;
        parent::__construct($task, $name);
        $this->productMatcher = Fs2psMatcherFactory::get($task, 'products');
        $this->combiMatcher = Fs2psMatcherFactory::get($task, 'combinations');
    }
    
    protected function reloadCfg() {
        parent::reloadCfg();
        if(!empty($this->task->cmd['after'])) {
            $this->after = Fs2psTools::dto2date($this->task->cmd['after']);
            $this->after_timestamp = $this->after->getTimestamp();
        }

        $cfg = $this->task->cfg;
        $download_images = $cfg->get('DOWNLOAD_PRODUCTS_IMAGES', '');
        $this->cover = array_search('onlycover', $download_images) !== false;
    }

    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');

        $after_str = Fs2psTools::date2db($this->after);       
        $where = 'p.date_upd>\''.$after_str.'\'';
        if(!empty($this->cover)){
            return '
                select 
                    p.id_product,
                    pa.id_product_attribute,
                    min(pi.position) as position, 
                    i.id_image, 
                    p.date_upd as updated,
                    pl.link_rewrite
                    
                from (
                    select 
                        i.id_product, 
                        min(i.position) as position
                    from @DB_image i 
                    group by i.id_product
                ) pi
                inner join @DB_image i on i.position=pi.position and i.id_product=pi.id_product
                inner join @DB_product p on p.id_product=i.id_product
                inner join @DB_product_lang pl on pl.id_product=p.id_product and pl.id_lang = '.$id_default_lang.'
                left join @DB_product_attribute pa on pa.id_product = p.id_product
                where '.$where.'
                group by p.id_product, pa.id_product_attribute
            ';
        } 

        return  '
            select
                i.id_image, p.id_product, pl.link_rewrite, i.position, pa.id_product_attribute,
                p.date_upd as updated
            from @DB_image i
            inner join @DB_product p on p.id_product=i.id_product
            inner join @DB_product_lang pl on pl.id_product=p.id_product and pl.id_lang = '.$id_default_lang.'
            left join @DB_product_attribute pa on pa.id_product = p.id_product
            WHERE '.$where.'
            group by p.id_product, pa.id_product_attribute
            order by p.date_upd, p.id_product, i.id_image  
        ';
    }

    protected function row2dto($row)
    {
        $dto = [
            'id_product' => $row['id_product'],
            'link_rewrite' => $row['link_rewrite'],
            'position' => $row['position']
        ];

        //Aquesta part no funciona correctament, per al producte D-06-02 que te id 279 esta ficant al referencia 2790 quan es un prod simple
        //l'esta considerant un prod compost??
        if(!empty($row['id_product_attribute'])){
            $dto['product'] = $this->combiMatcher->dtoIdFromRowId(intval($row['id_product_attribute']));
        }else{
            $dto['product'] = $this->productMatcher->dtoIdFromRowId(intval($row['id_product']));
        }

        // Si es una combinacion, convertimos product a String para la descarga por referencia
        if (is_array($dto['product'])) {
            $dto['product'] = reset($dto['product']);
        }
        
        $this->context = Context::getContext();
        $dto['img_url'] = $this->context->link->getImageLink($row['link_rewrite'], $row['id_image']);

        $image = new Image($row['id_image']);
        $path = _PS_PROD_IMG_DIR_ . $image->getExistingImgPath() . '.' . $image->image_format;
        if (!file_exists($path)) return null;
        $dto['type'] = $image->image_format;

        if (filemtime($path)>=$this->after_timestamp) {
            $data = base64_encode(file_get_contents($path));
            $dto['data'] = $data;
        }
        $dto['updated'] = $row['updated'];

        return $dto;
    }
}

class Fs2psSizeColourCombinationExtractor extends Fs2psMatchedExtractor
{
    
    protected function buildSql()
    {
        return '
            select 
                min(pa.id_product_attribute) as id,
                min(pa.reference) as pref,
                min(pa.price + p.price) as price,
                min(sa.quantity) as stock,
                min(tax.rate) as tax_class,
                min(pa.ean13) as ean13
            from @DB_product p
            inner join @DB_product_shop ps on ps.id_product=p.id_product and ps.id_shop=1 -- Evitamos problemas multitienda?
            inner join @DB_product_attribute pa on pa.id_product=p.id_product
            inner join @DB_product_attribute_combination pac on pac.id_product_attribute=pa.id_product_attribute
            inner join @DB_stock_available sa on sa.id_product=p.id_product and sa.id_product_attribute=pa.id_product_attribute
            left join (
                select max(t.rate) as rate, tr.id_tax_rules_group 
                from @DB_country c
                left join @DB_tax_rule tr on tr.id_country=c.id_country 
                left join @DB_tax t on t.id_tax=tr.id_tax
                where c.iso_code=\'ES\'
                group by tr.id_tax_rules_group 
            ) as tax on tax.id_tax_rules_group=p.id_tax_rules_group    
            left join @DB_attribute at on at.id_attribute=pac.id_attribute and at.id_attribute_group in ('.$this->matcher->sizeAttributeGroupIdsInSql.')
            left join @DB_attribute ac on ac.id_attribute=pac.id_attribute and ac.id_attribute_group in ('.$this->matcher->colourAttributeGroupIdsInSql.')
            '.($this->discard_disabled_products? 'where p.active=1' :'').'
            group by pa.id_product_attribute
		';
    }
    
    protected function row2dto($row)
    {
        $dto = parent::row2dto($row);
        //if(empty($dto)) return $dto;
        
        $dto = array_merge($dto, array(
            'price_base' => floatval($row['price']),
            //'cost' => floatval($row['wholesale_price']),
            'stock' => floatval($row['stock']),
            'tax_class' => strval(floatval($row['tax_class'])),
            'pref' => $row['pref'],
            'ean' => $row['ean13'],
        ));
        
        return $dto;
    }

}

class Fs2psPackItemExtractor extends Fs2psExtractor
{
    protected $discard_disabled_products;
    
    protected $productMatcher;
    protected $combiMatcher;
    
    public function __construct($task, $name)
    {
        $this->discard_disabled_products = $task->cfg->get('DISCARD_DISABLED_PRODUCTS', false);
        parent::__construct($task, $name);
        $this->productMatcher = Fs2psMatcherFactory::get($task, 'products');
        $this->combiMatcher = Fs2psMatcherFactory::get($task, 'combinations');
    }
    
    protected function buildSql()
    {
        $id_default_lang = Configuration::get('PS_LANG_DEFAULT');
        
        // Combinaciones en packs disponible a partir de la ver. 1.6.2.x
        $product_attribute_in_pack = version_compare(_PS_VERSION_, '1.6.2.0') >= 0;
        
        return '
            select
                pk.id_product_pack,
                pk.id_product_item,
                '.($product_attribute_in_pack? 'pk.id_product_attribute_item' : '0').' as id_product_attribute_item, 
                pk.quantity,
                pl.name as item_descrip
            from @DB_product p
            inner join @DB_pack pk on pk.id_product_pack=p.id_product
            left join @DB_product_lang pl on pl.id_product=pk.id_product_item and pl.id_lang='.$id_default_lang.'
            '.($this->discard_disabled_products? 'where p.active=1' :'').'
        ';
    }
    
    protected function row2dto($row)
    {
        $dto = array(
            'pack' => $this->productMatcher->dtoIdFromRowId(intval($row['id_product_pack'])),
            'quantity' => intval($row['quantity']),
        );
        
        $item_descrip = empty($row['item_descrip'])? '' : $row['item_descrip'];
        if (intval($row['id_product_attribute_item'])) {
            $combi_dto_id = $this->combiMatcher->dtoIdFromRowId(intval($row['id_product_attribute_item']));
            $dto['item'] = $combi_dto_id[0];
            $dto['size'] = $combi_dto_id[1];
            $dto['colour'] = $combi_dto_id[2];
            $item_descrip = $item_descrip.(empty($combi_dto_id[1])? '' : ' '.$combi_dto_id[1]).(empty($combi_dto_id[2])? '' : ' '.$combi_dto_id[2]);
        } else {
            $dto['item'] = $this->productMatcher->dtoIdFromRowId(intval($row['id_product_item']));
        }
        $dto['item_descrip'] = $item_descrip;
        
        return $dto;
    }
    
}
