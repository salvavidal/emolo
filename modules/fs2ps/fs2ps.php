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

if (!defined('_CAN_LOAD_FILES_'))
	exit;

include_once(dirname(__FILE__).'/Fs2psTools.php');

class fs2ps extends Module
{
	const MIN_VER = '1.5.0.0';
	const MAX_VER = '8.7.999.999';
	
	public function __construct()
	{
		$this->name = 'fs2ps';
		$this->tab = 'migration_tools';
		$this->version = '2.19.1.2504141106';
		$this->author = 'Fes-te web!';
		$this->module_key = '75905fcfeb1cd03551ba95aca2d8ef97';
		$this->need_instance = 0;
		$this->bootstrap = true;
		
		$this->ps_versions_compliancy = array('min' => self::MIN_VER, 'max' => self::MAX_VER);
		if (version_compare(_PS_VERSION_, self::MIN_VER) < 0 ||
			version_compare(_PS_VERSION_, self::MAX_VER) > 0)
		{
			// Avoid problems with PS before 1.5
			$this->_errors[] = $this->l('The version of your module is not compliant with your PrestaShop version.');
			return false;
		}
		
		parent::__construct();

		$this->displayName = 'fs2ps';
		$this->description = 'Módulo para sincronizar en un clic el catálogo y el stock desde FactuSOL hacia Prestashop.';
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

		if (!Configuration::get('FS2PS_KEY'))
			$this->warning = '
				Descarga el extractor pulsando la opción correspondiente y configura una KEY para que
				el extractor pueda enviar datos desde FactuSOL a Prestashop.
			';
	}

	public function install()
	{
		if (!parent::install())
			return false;

		$upgrate_versions = array('1.0.0', '1.1.0', '1.1.5', '2.0.1');
		foreach ($upgrate_versions as $ver)
		{
			include(dirname(__FILE__).'/upgrade/install-'.$ver.'.php');
			$upgrade_func = 'upgrade_module_'.str_replace('.', '_', $ver);
			
			if (!$upgrade_func($this))
				return false;
		}

		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall() || !$this->unregisterHook('displayBackOfficeHeader'))
			return false;

		Fs2psTools::dbExec('DROP TABLE IF EXISTS `@DB_fs2ps_task_log`');
		Fs2psTools::dbExec('DROP TABLE IF EXISTS `@DB_fs2ps_task`');

		return true;
	}

	public function hookDisplayBackOfficeHeader($params)
	{
		if (!(Tools::getValue('controller') == 'AdminModules' && Tools::getValue('configure') == 'fs2ps'))
			return;
	}

	/***
	 * CONFIGURATION
	 */
	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit'.$this->name))
		{
			$my_module_name = (string)Tools::getValue('FS2PS_KEY');
			if (!$my_module_name || empty($my_module_name) || !Validate::isGenericName($my_module_name))
				$output .= $this->displayError($this->l('Invalid Configuration value'));
			else
			{
				Configuration::updateValue('FS2PS_KEY', $my_module_name);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			}
		}
		return $output.$this->displayForm();
	}

	public function displayForm()
	{
		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		// Init fields form array
		$fields_form = array(array('form' => array(
			'legend' => array('title' => $this->l('Settings'),),
			'input' => array(array(
				'type' => 'text',
				'name' => 'FS2PS_KEY',
				'label' => 'KEY',
				'size' => 32,
				'required' => true,
				'desc' => 'Clave secreta'
			)),
			'submit' => array('title' => $this->l('Save'), 'class' => 'button')
		)));

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = 'FS2PS - Migrate data from FactuSOL to Prestashop';
		$helper->show_toolbar = true;
		$helper->toolbar_scroll = true;
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
				'save' => array('desc' => $this->l('Save'),
						'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),),
				'back' => array('href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'), 'desc' => $this->l('Back to list')));

		// Load current key value
		$helper->fields_value['FS2PS_KEY'] = Configuration::get('FS2PS_KEY');

		return join('',
			array(
			'<div class="warn" style="margin-bottom: 20px">
				<h2>INSTRUCCIONES DE USO DE FS2PS</h2>
				<ol style="list-style-type:decimal;">
					<li>
                        Indica un valor en el campo KEY. 
                        La KEY es una clave secreta necesaria para asegurar que sólo tú puedes sincronizar tu Factusol con tu Prestashop. 
                        Puedes indicar la KEY que quieras pero después deberás poner exactamente el mismo valor en la configuración del extractor.
                    </li>
					<li>No olvides guardar los cambios para que quede almacenada la nueva KEY.</li>
					<li>Descarga <a href="http://www.festeweb.com/static/downloads/fs2ps-client_'.$this->version.'-64b.exe">el extractor de 64 bits</a> o <a href="http://www.festeweb.com/static/downloads/fs2ps-client_'.$this->version.'.exe">el antiguo de 32 bits</a>  e instálalo en la máquina donde tengas FactuSOL.</a></li>
					<li>
						Ejecuta el extractor de FactuSOL y pulsa el botón "CONFIGURAR" para establecer la ruta a la BD de FactuSOL (<b>MDB_PATH</b>), 
                        la <b>URL</b> de tu Prestashop (<b><script>document.write((function(l) { 
                            var a = l.protocol+"//"+l.hostname+(l.port? ":"+l.port : "");
                            var b = l.pathname.split("/").filter(function (e) { return e; }).slice(0, -2); 
                            return a + (b.length>0? "/"+b.join("/"): "");
                        })(window.location))</script></b>) y la <b>KEY</b> indicada aquí.
					</li>
					<li>No olvides guardar los cambios realizados en el fichero de configuración del extractor (Archivo > Guardar).</li>
					<li>Una vez establecida la configuración, pulsa el botón "MIGRAR" para poner a prueba el extractor.</li>
					<li>Puede que el extractor muestre algún error que haya que resolver.</li>
					<li>Si todo fué bien el extractor mostrará la barra de progreso completa y el mensaje correspondiente.</li>
					<li>Comprueba que se han cargado en la tienda online los productos marcados para publicar en Internet en tu FactuSOL y las secciones y familias que los contienen.</li>
					<li>Repite la migración siempre que necesites subir cambios de FactuSOL a Prestashop.</li>
					<li>Por razones de seguridad se recomienda usar un certificado SSL y cambiar la KEY periódicamente.</li>
				</ol>
			</div>', $helper->generateForm($fields_form)));
	}

}
