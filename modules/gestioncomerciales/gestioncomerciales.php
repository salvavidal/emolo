<?php
/**
 * Gestión Comerciales Module
 *
 * @author    Tu Nombre
 * @copyright 2024
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Gestioncomerciales extends Module
{
    public function __construct()
    {
        $this->name = 'gestioncomerciales';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Tu Nombre';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Gestión Comerciales');
        $this->description = $this->l('Módulo para gestión de comerciales con funcionalidad de login como cliente.');
        $this->confirmUninstall = $this->l('¿Estás seguro de que quieres desinstalar este módulo?');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionCustomerGridDefinitionModifier') &&
            $this->registerHook('displayAdminCustomersList') &&
            $this->registerHook('displayAdminCustomers') &&
            $this->createAdminTab();
    }

    public function uninstall()
    {
        return $this->removeAdminTab() && parent::uninstall();
    }

    private function createAdminTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminLoginAsCustomer';
        $tab->name = array();
        foreach (Language::getLanguages() as $lang) {
            $tab->name[$lang['id_lang']] = 'Login como Cliente';
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminCustomers');
        $tab->module = $this->name;

        return $tab->add();
    }

    private function removeAdminTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminLoginAsCustomer');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    /**
     * Hook para modificar la definición del grid de clientes
     */
    public function hookActionCustomerGridDefinitionModifier(array $params)
    {
        /** @var \PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface $definition */
        $definition = $params['definition'];

        // Añadir columna de acciones personalizada si no existe
        $columns = $definition->getColumns();

        // Buscar si ya existe una columna de acciones
        $hasActionsColumn = false;
        foreach ($columns as $column) {
            if ($column instanceof \PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn) {
                $hasActionsColumn = true;
                break;
            }
        }

        // Si no hay columna de acciones, la creamos
        if (!$hasActionsColumn) {
            $actionsColumn = new \PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn('actions');
            $actionsColumn->setName($this->l('Acciones'));
            $actionsColumn->setOptions([
                'actions' => []
            ]);
            $definition->getColumns()->add($actionsColumn);
        }
    }

    /**
     * Hook para añadir JavaScript personalizado en la lista de clientes
     */
    public function hookDisplayAdminCustomersList($params)
    {
        $this->context->controller->addJS($this->_path . 'views/js/admin-customers.js');
        $this->context->controller->addCSS($this->_path . 'views/css/admin-customers.css');

        return $this->display(__FILE__, 'views/templates/admin/customers_list_script.tpl');
    }

    /**
     * Hook para añadir contenido en la página de clientes
     */
    public function hookDisplayAdminCustomers($params)
    {
        // Añadir JavaScript y CSS
        $this->context->controller->addJS($this->_path . 'views/js/login-as-customer.js');
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');

        // Manejar acción de login
        if (Tools::isSubmit('loginAsCustomer')) {
            $this->processLoginAsCustomer();
        }

        return '';
    }

    public function processLoginAsCustomer()
    {
        $id_customer = (int)Tools::getValue('id_customer');

        // Verificar permisos
        if (!$this->context->employee || !$this->context->employee->id) {
            $this->context->controller->errors[] = $this->l('Acceso denegado');
            return;
        }

        $customer = new Customer($id_customer);
        if (!Validate::isLoadedObject($customer) || !$customer->active || $customer->deleted) {
            $this->context->controller->errors[] = $this->l('Cliente no válido');
            return;
        }

        // Generar token de seguridad
        $secure_key = md5($customer->id . $customer->email . time() . $this->context->employee->id);

        // Guardar token en cookie temporal
        $this->context->cookie->__set('customer_login_token_' . $customer->id, $secure_key);
        $this->context->cookie->write();

        // Registrar en logs
        PrestaShopLogger::addLog(
            sprintf('Empleado %s (%d) iniciando sesión como cliente %s (%d)', 
                $this->context->employee->firstname . ' ' . $this->context->employee->lastname,
                $this->context->employee->id,
                $customer->firstname . ' ' . $customer->lastname,
                $customer->id
            ),
            1,
            null,
            'Customer',
            $customer->id
        );

        // Crear URL de login
        $login_url = $this->context->link->getModuleLink(
            'gestioncomerciales', 
            'loginascustomer', 
            [
                'id_customer' => $customer->id,
                'secure_key' => $secure_key
            ]
        );

        // Abrir en nueva ventana usando JavaScript
        echo '<script>window.open("' . $login_url . '", "_blank");</script>';
    }

    /**
     * Método para obtener la URL de login como cliente
     */
    public function getLoginAsCustomerUrl($id_customer)
    {
        $customer = new Customer($id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return false;
        }

        $admin_token = Tools::getAdminTokenLite('AdminCustomers');
        return $this->context->link->getAdminLink('AdminCustomers', true, [], [
            'action' => 'loginAsCustomer',
            'id_customer' => $id_customer,
            'loginAsCustomer' => 1,
            'token' => $admin_token
        ]);
    }
}