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
        parent::initContent();

        // Obtener el módulo
        $module = Module::getInstanceByName('gestioncomerciales');

        // Mostrar el contenido de configuración del módulo
        $content = $module->getContent();

        $this->context->smarty->assign(array(
            'content' => $content,
            'module_name' => $module->displayName,
            'module_version' => $module->version
        ));

        $this->setTemplate('module_content.tpl');
    }

    public function postProcess()
    {
        // Procesar formularios si es necesario
        if (Tools::isSubmit('submit'.$this->module->name)) {
            $module = Module::getInstanceByName('gestioncomerciales');
            return $module->getContent();
        }

        return parent::postProcess();
    }
}