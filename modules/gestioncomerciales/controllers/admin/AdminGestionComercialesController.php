<?php

class AdminGestionComercialesController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
        $this->name = 'AdminGestionComerciales';
    }

    public function initContent()
    {
        // Llama a la función getContent del módulo para cargar la configuración
        $this->content = $this->module->getContent();

        // Asignar token y currentIndex para el formulario
        $this->context->smarty->assign([
            'content' => $this->content,
            'currentIndex' => AdminController::$currentIndex . '&configure=' . $this->module->name,
            'token' => $this->token // Usamos el token del controlador para evitar inconsistencia
        ]);

        parent::initContent();
    }
}
