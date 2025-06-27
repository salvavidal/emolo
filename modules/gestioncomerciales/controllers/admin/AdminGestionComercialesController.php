<?php
/**
 * Controlador administrativo para el módulo Gestión Comerciales
 */

class AdminGestionComercialesController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->meta_title = $this->l('Gestión de Comerciales');
    }

    public function initContent()
    {
        // Redirigir directamente a la configuración del módulo
        $configure_url = $this->context->link->getAdminLink('AdminModules') . '&configure=gestioncomerciales';
        Tools::redirectAdmin($configure_url);
    }
}