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

class Fs2psServerFatalException extends Exception
{
    public function __construct($msg, $previous = null)
    {
        parent::__construct($msg, null, $previous);
    }
}

class Fs2psNotImplemented extends Fs2psServerFatalException
{
    public function __construct()
    {
        parent::__construct("Not implemented");
    }
}

class Fs2psContinueException extends Exception
{
    public function __construct()
    {
        parent::__construct(null, null, null);
    }
}

class Fs2psException extends Exception
{
	public function __construct($msg, $previous = null)
	{
		parent::__construct($msg, null, $previous);
	}
}

class Fs2psDbException extends Fs2psServerFatalException
{
	public function __construct($sql=null, $cause=null)
	{
		$db = Db::getInstance();
		if ($cause && $cause->getMessage()) {
		    $msg = $cause->getMessage();
		} else {
		    $msg = 'Error '.$db->getNumberError().': '.$db->getMsgError();
		}
		if ($sql!==null) $msg = $msg."\n\n".$sql."\n\n";
		parent::__construct($msg, null);
	}
}

class Fs2psRunningTaskException extends Fs2psException
{
	public function __construct()
	{
		$msg = 'Debes esperar a que terminen las tareas en ejecución';
		parent::__construct($msg, null);
	}
}

class Fs2psCannotGetDtoIFromRowId extends Fs2psException {}
