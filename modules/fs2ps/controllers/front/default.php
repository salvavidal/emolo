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

include_once(dirname(__FILE__).'/../../Fs2psException.php');
include_once(dirname(__FILE__).'/../../Fs2psTools.php');
include_once(dirname(__FILE__).'/../../Fs2psTaskManager.php');


class Fs2psDefaultModuleFrontController extends ModuleFrontController
{
    protected $task_mng;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->context = Context::getContext();
        $this->task_mng = new Fs2psTaskManager();
    }
    
    private function error($msg, $exception=null)
    {
        $json = array(
            'status' => 'error',
            'msgs' => array($msg)
        );
        if ($exception) {
            $json['exception'] = get_class($exception);
            $json['traceback'] = ((string)$exception);
            
            // TODO: Obtener traza completa considerando excepciones anidadas
            // while $e->previous $json['traceback'] .=  $e->getPrevious()->getTraceAsString()
        }
        return Fs2psTools::jsonEncode($json);
    }
    
    private function logException($e)
    {
        $error_msg = 'fs2py: '.((string)$e);
        $error_msg = preg_replace('/[<>{}]/i', '', $error_msg);
        Logger::addLog(Fs2psTools::dbEscape($error_msg), 4);
    }
    
    /**
     * Take control over all kind of errors to mark task as 'error'
     * if one error is produced.
     */
    public function catchingErrorHandler($errno, $errstr, $errfile, $errline, $errcontext=null)
    {
        $task = $this->task_mng->task;
        $ignore_warns = $task? $task->cfg->get('IGNORE_ERRORS', false): false;
        
        if (isset($errstr) && (
            
            // Ignore Smarty errors like "filemtime(): stat failed for ..." or "unable to write file ..."
            (stripos($errstr, 'marty')!==false || stripos($errstr, 'filemtime')!==false) ||
            
            // Ignore RIJNDAEL errors
            // Notice:  Use of undefined constant _RIJNDAEL_IV_ - assumed '_RIJNDAEL_IV_' in /srv/www/rmhobby.es/www/classes/Cookie.php on line 79
            // Fatal:  Uncaught ErrorException: openssl_encrypt(): IV passed is only N bytes long, cipher expects an IV of precisely 16 bytes, padding with \0 in /srv/www/rmhobby.es/www/classes/Rijndael.php:52
            (stripos($errstr, 'RIJNDAEL')!==false || stripos($errstr, 'encrypt')!==false) ||
            
            // Ignore deprecated errors
            (stripos($errstr, 'deprecated')!==false) ||
            
            // Ignore "set_time_limit() has been disabled for security reasons, for example when calling" and others ...
            (stripos($errstr, 'has been disabled')!==false) ||
            
            // Ignore "set_time_limit() has been disabled for security reasons, for example when calling" and others ...
            (stripos($errstr, 'modify header information')!==false) ||
            
            // Ignore non fatal errno types
            ($ignore_warns == 'WARN' && (!in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR])))
            
            )) return false;
            
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    
    public function initContent()
    {
        $error = null;
        $response = null;
        
        try
        {
            // Take control over all kind of errors but ignore Smarty ones.
            // Explanation in http://www.smarty.net/forums/viewtopic.php?p=79512
            Configuration::set('PS_SMARTY_CACHE', 0);
            set_error_handler(array($this, 'catchingErrorHandler'));
            //Smarty::muteExpectedErrors();
            
            //parent::initContent(); // cfillol: Parece que no aporta nada y empeora en rendimiento
            header('Content-type: application/json; charset=utf-8');
            
            if (empty($_POST['cmd']) && empty($_POST['c'])){
                $cmd_txt = file_get_contents('php://input');
                if(empty($cmd_txt)) {
                    throw new Fs2psException('Llamada inválida. No se indicó "cmd"');
                }
            } else {
                $cmd_txt = empty($_POST['cmd'])? $_POST['c'] : $_POST['cmd'];
            }
            
            if (substr($cmd_txt, 0, 1)!=="{")
                $cmd_txt = base64_decode($cmd_txt);
                
                // get_magic_quotes_gpc() ?
                if (substr($cmd_txt, 0, 2)==="{\\")
                    $cmd_txt = stripslashes($cmd_txt);
                    
                    $cmd = Fs2psTools::jsonDecode($cmd_txt);
                    
                    if (empty($cmd))
                        throw new Fs2psException('Formato JSON incorrecto');
                        
                        $response = Fs2psTools::jsonEncode($this->task_mng->attendCmd($cmd));
                        
        }
        catch (Fs2psException $e)
        {
            $error = $this->error($e->getMessage(), $e);
        }
        catch (Exception $e)
        {
            //$this->logException($e); // cfillol: Mejor no usarlo porque a veces genera error y enmascara excepción original
            $error = $this->error('Excepción inesperada. Consulte el log para ver detalles.', $e);
        }
        
        // Finally
        if ($error)
            die($error);
            
            if ($response)
                die($response);
                
    }
    
}
