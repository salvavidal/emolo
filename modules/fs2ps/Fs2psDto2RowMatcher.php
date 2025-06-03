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

include_once(dirname(__FILE__).'/Fs2psObjectModels.php');
include_once(dirname(__FILE__).'/Fs2psMatcherFactory.php');
include_once(dirname(__FILE__).'/Fs2psTools.php');


class Fs2psDto2AbstractMatcher
{
    public $entity;
    public $dto_id_fields;
    public $row_id_field;
    public $forced_maches;
    public $forced_maches_reverse;
    public $direct_match;
    public $genidinc;
    
    
    public function __construct($entity, $row_id_field=null, $dto_id_fields=null)
    {
        $this->entity = $entity;
        $this->row_id_field = $row_id_field;
        $this->dto_id_fields = $dto_id_fields? $dto_id_fields : array('ref');
    }
    
    public function reloadCfg($cfg) {
        $updpart = strtoupper($this->entity);
        $this->forced_maches = $cfg->get('MATCH_'.$updpart, array());
        $this->forced_maches_reverse = array_flip($this->forced_maches);
        $this->direct_match = $cfg->get('DIRECT_MATCH_'.$updpart, false);
        $this->genidinc = $cfg->get($updpart.'_GENIDINC', 0);
    }
    
    
    public function dtoIdToStr($dto_id)
    {
        if (is_array($dto_id))
        {
            $dto_id_array = array();
            foreach ($dto_id as $id_part)
            {
                $id_part = empty($id_part) && $id_part!=='0' ? '' : $id_part; // empty de string '0' se evalúa a true!!!??
                
                // Escape id_part separator in each id_part: '_' -> '#_'
                $id_part = str_replace('#', '##', $id_part);
                $id_part = str_replace('_', '#_', $id_part);
                
                $dto_id_array[] = $id_part;
            }
            return implode('_', $dto_id_array);
        }
        return (string)$dto_id;
    }
    
    public function strToDtoId($str)
    {
        if (empty($str) && $str!=='0') return null; // empty de string '0' se evalúa a true!!!??
        
        $str = $str.'_';
        $dto_id_array = array();
        
        $matches = null;
        preg_match('/^(?:((?:(?:#_)|(?:##)|[^_])*)_)/', $str, $matches);
        while (!empty($matches))
        {
            $id_part = $matches[1];
            
            // Go to next match
            $str = Tools::substr($str, Tools::strlen($id_part) + 1);
            preg_match('/^(?:((?:(?:#_)|(?:##)|[^_])*)_)/', $str, $matches);
            
            // Reverse _ and # escaping
            $id_part = str_replace('#_', '_', $id_part);
            $id_part = str_replace('##', '#', $id_part);
            
            $dto_id_array[] = $id_part;
        }
        
        if (empty($dto_id_array)) return null;
        return count($dto_id_array) > 1? $dto_id_array : $dto_id_array[0];
    }
    
    public function dtoIdToStrFromDto($dto)
    {
        return $this->dtoIdToStr($this->dtoId($dto));
    }
    
    public function idFromFields($dto, $fields)
    {
        if (!$fields) return null;
        
        $id_as_array = array();
        foreach ($fields as $f)
        {
            $value = isset($dto[$f])? $dto[$f] : null;
            $value = empty($value) && $value!=='0' ? null : $value; // empty de string '0' se evalúa a true!!!??
            array_push($id_as_array, $value);
        }
        return count($id_as_array) > 1 ? $id_as_array : $id_as_array[0];
    }
    
    public function dtoId($dto) {
        if (isset($dto['_id'])) return $dto['_id'];
        $id = $this->idFromFields($dto, $this->dto_id_fields);
        $dto['_id'] = $id;
        return $id;
    }
    
    /**
     * return null: no sobreescribe
     * return '': sobreescribe a vacío
     */
    public function referenceFromDto($dto)
    {
        // Si pref devolvemos pref. Sino return:
        // ref: $this->dtoIdToStrFromDto($dto)
        // TODO: basic o pref: null
        return isset($dto['pref'])? $dto['pref'] : $this->dtoIdToStrFromDto($dto);
    }
    
    public function rowId($row)
    {
        if (!empty($this->row_id_field) && isset($row[$this->row_id_field])) {
            return $row[$this->row_id_field];
        }
        return null;
    }
    
    
    protected function _getMatch($dto_id_str, $table, $entity)
    {
        $row_id = Fs2psTools::dbValue('
			select row_id
			from `@DB_fs2ps_match`
			where
				`table`=\''.$table.'\' and
				(`entity`=\''.$entity.'\' or `entity` is null) and
				dto_id=\''.$dto_id_str.'\'
		');
        if ($row_id===FALSE) return null;
        return $row_id;
    }
    
    protected function getMatch($dto_id, $nocache=FALSE) {
        throw new Fs2psNotImplemented();
    }
    
    protected function _getReverseMatch($row_id, $table, $entity)
    {
        $dto_id_str = Fs2psTools::dbValue('
			select dto_id
			from `@DB_fs2ps_match`
			where
				`table`=\''.$table.'\' and
				(`entity`=\''.$entity.'\' or `entity` is null) and
				row_id=\''.$row_id.'\'
		');
        if ($dto_id_str===FALSE) return null;
        return $dto_id_str;
    }
    
    protected function getReverseMatch($row_id, $nocache=FALSE)
    {
        throw new Fs2psNotImplemented();
    }
    
    public function existsRowInDatabase($id)
    {
        throw new Fs2psNotImplemented();
    }
    
    
    public function rowIdFromDtoIdForcedMatches($dto_id)
    {
        if ($this->direct_match) return $dto_id;
        if (!empty($this->forced_maches))
        {
            $dto_id_str = $this->dtoIdToStr($dto_id);
            if (isset($this->forced_maches[$dto_id_str]))
                return $this->forced_maches[$dto_id_str];
        }
        return null;
    }
    
    public function rowIdFromDtoId($dto_id)
    {
        $row_id = $this->rowIdFromDtoIdForcedMatches($dto_id);
        if ($row_id) return $row_id;
        return $this->_rowIdFromDtoId($dto_id);
    }
    
    /**
     * Esta es la función que debe sobreescribirse para modificar el comportamiento del
     * matcher.
     */
    public function _rowIdFromDtoId($dto_id)
    {
        throw new Fs2psNotImplemented();
    }
    
    /**
     * Sólo debe usarse esta función en caso de que dto tenga suficiente información
     * como en el momento de la creación/actualización.
     */
    public function rowIdFromDto($dto)
    {
        return $this->rowIdFromDtoId($this->dtoId($dto));
    }
    
    
    /**
     * Soporte multimatch para multiples actualizaciones por ejemplo, para varios productos con la misma referencia.
     * XXX: Variantes rowIdFrom requeriran algo de información extra para discriminar, por ejemplo a la hora
     * de establecer relaciones entre elementos por dtoId (categorias padres e hijas, productos con combinaciones ...)
     */
    public function rowIdsFromDtoId($dto_id) { $rid = $this->rowIdFromDtoId($dto_id); return $rid? array($rid) : $rid; }
    //public function _rowIdsFromDtoId($dto_id) { $rid = $this->_rowIdFromDtoId($dto_id); return $rid? array($rid) : $rid; } // No se usa
    public function rowIdsFromDto($dto) { $rid = $this->rowIdFromDto($dto); return $rid? array($rid) : $rid; }
    
    public function dtoIdStrFromRowIdForcedMatches($row_id)
    {
        if ($this->direct_match) return (empty($row_id)? null : strval($row_id));
        if (!empty($this->forced_maches_reverse))
        {
            if (isset($this->forced_maches_reverse[$row_id]))
                return $this->dtoIdToStr($this->forced_maches_reverse[$row_id]);
        }
        return null;
    }
    
    public function dtoIdStrFromRowId($row_id)
    {
        if(empty($row_id)) return null;
        $dto_id_str = $this->dtoIdStrFromRowIdForcedMatches($row_id);
        if ($dto_id_str) return $dto_id_str;
        return $this->_dtoIdStrFromRowId($row_id);
    }
    
    /**
     * Esta es la función que debe sobreescribirse para modificar el comportamiento del
     * matcher.
     */
    public function _dtoIdStrFromRowId($row_id)
    {
        throw new Fs2psNotImplemented();
    }
    
    public function dtoIdFromRowId($row_id)
    {
        return $this->strToDtoId($this->dtoIdStrFromRowId($row_id));
    }
    
    /**
     * Genera un dto_id_str a partir del row_id. Útil para la generación de dto_id durante la extracción.
     */
    public function generateDtoIdStrFromRowId($row_id)
    {
        if ($this->genidinc==-1) return Fs2psTools::num2alpha($row_id);
        return strval($this->genidinc + $row_id);
    }
    
    protected function _updateMatch($dto_id_str, $row_id, $table, $entity)
    {
        if ($this->_getMatch($dto_id_str, $table, $entity))
        {
            return Fs2psTools::dbExec('
				update `@DB_fs2ps_match`
				set row_id=\''.$row_id.'\', entity=\''.$entity.'\', uploaded=1
				where
					`table`=\''.$table.'\' and
					(`entity`=\''.$entity.'\' or `entity` is null) and
					dto_id=\''.$dto_id_str.'\'
			');
        }
        else
        {
            return Fs2psTools::dbExec('
				insert into `@DB_fs2ps_match` (`table`, `entity`, dto_id, row_id, uploaded)
				values (\''.$table.'\', \''.$entity.'\', \''.$dto_id_str.'\', \''.$row_id.'\', 1)
			');
        }
    }
    
    public function updateMatch($dto_id, $row_id)
    {
        throw new Fs2psNotImplemented();
    }
    
    
    protected function _updateReverseMatch($dto_id_str, $row_id, $table, $entity)
    {
        if ($this->_getReverseMatch($row_id, $table, $entity)!==null)
        {
            return Fs2psTools::dbExec('
        		update `@DB_fs2ps_match`
        		set dto_id=\''.$dto_id_str.'\', entity=\''.$entity.'\', uploaded=1
        		where
        			`table`=\''.$table.'\' and
        			(`entity`=\''.$entity.'\' or `entity` is null) and
        			row_id=\''.$row_id.'\'
        	');
        }
        else
        {
            // Evitamos errors cuando ya existe match con mismo dto_id pero distintoo row_id
            if ($this->_getMatch($dto_id_str, $table, $entity)!==null) return TRUE;
            
            return Fs2psTools::dbExec('
        		insert into `@DB_fs2ps_match` (`table`, `entity`, dto_id, row_id, uploaded)
        		values (\''.$table.'\', \''.$entity.'\', \''.$dto_id_str.'\', \''.$row_id.'\', 1)
        	');
        }
    }
    
    public function updateReverseMatch($dto_id, $row_id)
    {
        throw new Fs2psNotImplemented();
    }
    
}

class Fs2psDto2RowMatcher extends Fs2psDto2AbstractMatcher
{
    public $table;
    public $persist;
    public $cache = FALSE;

    protected $ignorepersist;
    
    
    public function __construct($entity, $table, $row_id_field=null, $dto_id_fields=null, $persist = true, $cache=false)
    {
        if (empty($row_id_field)) {
            $row_id_field = empty($table)? null : Fs2psObjectModel::idForTable($table);
        }
        
        parent::__construct($entity, $row_id_field, $dto_id_fields);
        
        $this->table = empty($table)? '' : $table;
        $this->persist = $persist;
        $this->cache = $cache;
    }
    
    public function reloadCfg($cfg)
    {
        $updpart = strtoupper($this->entity);
        parent::reloadCfg($cfg);
        
        $match_preference = $cfg->get($updpart.'_MATCH_PREFERENCE');
        $this->ignorepersist = $match_preference=='ignorepersist';
    }

    protected function getMatch($dto_id, $nocache=FALSE)
    {
        $dto_id_str = Fs2psTools::dbEscape($this->dtoIdToStr($dto_id));
        if (!$nocache && $this->cache && isset($this->cache_rowid_by_dtoidstr[$dto_id_str])) return $this->cache_rowid_by_dtoidstr[$dto_id_str];
        
        $row_id = Fs2psTools::dbValue('
			select row_id
			from `@DB_fs2ps_match`
			where
				`table`=\''.$this->table.'\' and
				(`entity`=\''.$this->entity.'\' or `entity` is null) and
				dto_id=\''.$dto_id_str.'\'
		');
        
        if ($row_id) {
            if ($this->cache) $this->cache_rowid_by_dtoidstr[$dto_id_str] = $row_id;
            return $row_id;
        }
        
        return null;
    }
    
    protected function getReverseMatch($row_id, $nocache=FALSE)
    {
        if (!$nocache && $this->cache && isset($this->cache_dtoid_by_rowid[$row_id])) return $this->cache_dtoid_by_rowid[$row_id];
        
        $dto_id_str = Fs2psTools::dbValue('
			select dto_id
			from `@DB_fs2ps_match`
			where
				`table`=\''.$this->table.'\' and
				(`entity`=\''.$this->entity.'\' or `entity` is null) and
				row_id=\''.$row_id.'\'
		');
        
        if ($dto_id_str!==FALSE) {
            if ($this->cache) $this->cache_dtoid_by_rowid[$row_id] = $dto_id_str;
            return $dto_id_str;
        }
        return null;
    }
    
    public function existsRowInDatabase($id)
    {
        $exists = !empty($id) && Fs2psTools::dbValue('
			SELECT 1
			FROM `@DB_'.$this->table.'`
			WHERE '.$this->row_id_field .' = '.(int)$id
            );
        return $exists? TRUE : FALSE;
    }
    
    /**
     * Esta es la función que debe sobreescribirse para modificar el comportamiento del
     * matcher.
     */
    public function _rowIdFromDtoId($dto_id)
    {
        if (!$this->persist || $this->ignorepersist) return null;
        return $this->getMatch($dto_id);
    }
    
    /**
     * Esta es la función que debe sobreescribirse para modificar el comportamiento del
     * matcher.
     */
    public function _dtoIdStrFromRowId($row_id)
    {
        if (!$this->persist || $this->ignorepersist) return null;
        return $this->getReverseMatch($row_id);
    }
    
    public function updateMatch($dto_id, $row_id)
    {
        if (!$this->persist) return;
        
        $dto_id_str = Fs2psTools::dbEscape($this->dtoIdToStr($dto_id));
        if ($this->cache) {
            if (isset($this->cache_rowid_by_dtoidstr[$dto_id_str])) {
                $old_row_id = $this->cache_rowid_by_dtoidstr[$dto_id_str];
                if ($old_row_id!=$row_id) unset($this->cache_dtoid_by_rowid[$old_row_id]);
            }
            $this->cache_rowid_by_dtoidstr[$dto_id_str] = $row_id;
            $this->cache_dtoid_by_rowid[$row_id] = $dto_id_str;
        }
        if ($this->getMatch($dto_id, TRUE))
        {
            return Fs2psTools::dbExec('
				update `@DB_fs2ps_match`
				set row_id=\''.$row_id.'\', entity=\''.$this->entity.'\', uploaded=1
				where
					`table`=\''.$this->table.'\' and
					(`entity`=\''.$this->entity.'\' or `entity` is null) and
					dto_id=\''.$dto_id_str.'\'
			');
        }
        else
        {
            return Fs2psTools::dbExec('
				insert into `@DB_fs2ps_match` (`table`, `entity`, dto_id, row_id, uploaded)
				values (\''.$this->table.'\', \''.$this->entity.'\', \''.$dto_id_str.'\', \''.$row_id.'\', 1)
			');
        }
        
    }
    
    public function updateReverseMatch($dto_id, $row_id)
    {
        if (!$this->persist) return;
        
        $dto_id_str = Fs2psTools::dbEscape($this->dtoIdToStr($dto_id));
        if ($this->cache) {
            if (isset($this->cache_dtoid_by_rowid[$row_id])) {
                $old_dto_id_str = $this->cache_dtoid_by_rowid[$row_id];
                if ($old_dto_id_str!=$dto_id_str) unset($this->cache_rowid_by_dtoidstr[$old_dto_id_str]);
            }
            $this->cache_dtoid_by_rowid[$row_id] = $dto_id_str;
            $this->cache_rowid_by_dtoidstr[$dto_id_str] = $row_id;
        }
        
        if ($this->getReverseMatch($row_id, TRUE)!==null)
        {
            return Fs2psTools::dbExec('
				update `@DB_fs2ps_match`
				set dto_id=\''.$dto_id_str.'\', entity=\''.$this->entity.'\', uploaded=1
				where
					`table`=\''.$this->table.'\' and
					(`entity`=\''.$this->entity.'\' or `entity` is null) and
					row_id=\''.$row_id.'\'
			');
        }
        else
        {
            // Evitamos errors cuando ya existe match con mismo dto_id pero distintoo row_id
            if ($this->getMatch($dto_id_str, TRUE)!==null) return TRUE;
            
            return Fs2psTools::dbExec('
				insert into `@DB_fs2ps_match` (`table`, `entity`, dto_id, row_id, uploaded)
				values (\''.$this->table.'\', \''.$this->entity.'\', \''.$dto_id_str.'\', \''.$row_id.'\', 1)
			');
        }
        
    }
    
    public static function clearUploadedMarks($task)
    {
        // Eliminamos refs. de match si se eliminan datos en PS
        $rows = Fs2psTools::dbSelect(
            'select distinct `table`, `entity` from `@DB_fs2ps_match` where `table`<>\'\''
        );
        foreach ($rows as $row)
        {
            $table = $row['table'];
            $entity = $row['entity'];
            $id_field = Fs2psObjectModel::idForTable($table);
            $where = '';
            
            // Evitamos eliminar atributos porque pueden
            // haberse mapeado aunque aún no existan durante la extracción.
            if ($entity=='colours' || $entity=='sizes')
            {
                $attrGrpMatcher = Fs2psMatcherFactory::get($task, 'attribute_groups');
                $id_attribute_group = $attrGrpMatcher->rowIdFromDtoId(strtoupper($entity));
                $where = 'where id_attribute_group=\''.$id_attribute_group.'\'';
            }
            
            // Purga de matches que han dejado de existir
            Fs2psTools::dbExec('
				delete from @DB_fs2ps_match
				where
					`table`=\''.$table.'\' and
					(`entity`=\''.$entity.'\' or `entity`is null) and
					row_id not in (
						select t.'.$id_field.' from `@DB_'.$table.'` t '.$where.'
					)
			');
        }
        
        // Reseteamos marca uploaded
        Fs2psTools::dbExec('
			update `@DB_fs2ps_match`
			set uploaded = 0
		');
    }
    
    
    /**
     *  Eliminamos refs. de match duplicadas de las cuales sólo una tiene uploaded=1
     *  Esto puede pasar si cambia el valor de la referencia en Prestashop o la política de matching.
     *  Ej:   dto_id      row_id       uploaded
     *           001          11              0  <- Esta se debe borrar
     *           003          11              1  <- Esta se debe conservar
     *           005          11              0  <- Esta se debe borrar
     */
    public static function deleteRepeatedZeroMarks()
    {
        Fs2psTools::dbExec('
			delete  r0
			from
			    @DB_fs2ps_match r1
			    inner join @DB_fs2ps_match r0 on r0.`table`=r1.`table` and r0.entity=r1.entity and r0.row_id=r1.row_id and r0.uploaded=0
			where r1.uploaded=1
		');
    }
    
}

class Fs2psDto2RowOidMatcher extends Fs2psDto2RowMatcher
{
    protected $dto_oid_fields;
    protected $row_oid_field;
    
    protected $oidfirst;
    
    protected $multimatch;
    
    protected $oid_extra_where;
    
    public function __construct($entity, $table, $dto_id_fields, $dto_oid_fields, $row_oid_field, $persist=false, $cache=false, $multimatch=false)
    {
        $this->dto_oid_fields = $dto_oid_fields;
        $this->row_oid_field = $row_oid_field;
        $this->multimatch = $multimatch;
        parent::__construct($entity, $table, null, $dto_id_fields, $persist, $cache);
    }
    
    public function reloadCfg($cfg)
    {
        $updpart = strtoupper($this->entity);
        parent::reloadCfg($cfg);
        
        $match_preference = $cfg->get($updpart.'_MATCH_PREFERENCE');
        $this->oidfirst = $match_preference=='oidfirst';
    }
    
    public function setOidExtraWhere($extra_where) {
        $this->oid_extra_where = empty($extra_where)? '' : ' and ('.$extra_where.')';
    }

    public function _rowIdFromOid($oid)
    {
        if (!$this->row_oid_field) return null;
        
        $oid_str = Fs2psTools::dbEscape($this->dtoIdToStr($oid));
        
        // Avoid matchings if oid is empty
        if (empty($oid_str)) return null;
        
        $row_id = Fs2psTools::dbValue('
			SELECT '.$this->row_id_field.'
			FROM `@DB_'.$this->table.'`
			WHERE `'.$this->row_oid_field.'` = \''.$oid_str.'\'
            '.$this->oid_extra_where.'
		');

        if (empty($row_id)) return null;

        return $row_id;
    }
    
    public function _oidStrFromRowId($row_id)
    {
        if (!$this->row_oid_field) return null;
        
        return Fs2psTools::dbValue('
			SELECT `'.$this->row_oid_field.'`
			FROM `@DB_'.$this->table.'`
			WHERE '.$this->row_id_field.' = '.$row_id.'
		');
    }
    
    public function dtoOid($dto) {
        if (isset($dto['_oid'])) return $dto['_oid'];
        $oid = $this->idFromFields($dto, $this->dto_oid_fields);
        $dto['_oid'] = $oid;
        return $oid;
    }
    
    /**
     * Reemplazo de rowIdFromDto/rowIdFromDtoId teniendo en cuenta valor de oid
     */
    public function rowIdFromDtoOid($dto_id, $oid)
    {
        $row_id = $this->rowIdFromDtoIdForcedMatches($dto_id);
        if ($row_id) return $row_id;
        
        if (!$this->oidfirst) { //  && !$this->ignorepersist // Se gestiona en el padre
            $row_id = parent::_rowIdFromDtoId($dto_id);
            if ($row_id) return $row_id;
        }
        
        if ($oid!==null)
        {
            $row_id = $this->_rowIdFromOid($oid);
            if ($row_id) return $row_id;
        }
        
        if ($this->oidfirst) {
            $row_id = parent::_rowIdFromDtoId($dto_id);
            if ($row_id) return $row_id;
        }
        
        return $row_id;
    }
    
    /**
     * Sólo debe usarse esta función en caso de que dto tenga suficiente información
     * como en el momento de la creación/actualización.
     */
    public function rowIdFromDto($dto)
    {
        return $this->rowIdFromDtoOid($this->dtoId($dto), $this->dtoOid($dto));
    }
    
    /**
     * Cuando se genera dto_id (en modo extracción, por ejemplo), tomamos el valor de oid
     */
    public function _dtoIdStrFromRowId($row_id)
    {
        if (!$this->oidfirst) {  // && !$this->ignorepersist) { // Se gestiona en el padre
            $dto_id = parent::_dtoIdStrFromRowId($row_id);
            if (!empty($dto_id)) return $dto_id;
        }
        
        // Generamos dto_id a partir de oid
        $oid_str = $this->_oidStrFromRowId($row_id);
        if (!empty($oid_str)) return $oid_str;
        
        if ($this->oidfirst) {
            $dto_id = parent::_dtoIdStrFromRowId($row_id);
            if (!empty($dto_id)) return $dto_id;
        }
        
        return null;
    }
    
    
    //////////
    // Multimatch por oid
    //
    // Reemplazo de rowIdsFromDto teniendo en cuenta sólo el valor de oid.
    // No reemplazamos rowIdsFromDtoId porque generalmente no sabremos el oid a partir del dto_id.
    // Actualmente sólo usamos rowIdsFromDto en Fs2psStockablesUpdater.
    
    public function _rowIdsFromOid($oid)
    {
        if (!$this->row_oid_field) return null;
        
        $oid_str = Fs2psTools::dbEscape($this->dtoIdToStr($oid));
        
        // Avoid matchings if oid is empty
        if (empty($oid_str)) return array();
        
        $sql = '
			SELECT '.$this->row_id_field.'
			FROM `@DB_'.$this->table.'`
			WHERE `'.$this->row_oid_field.'` = \''.$oid_str.'\'
            '.$this->oid_extra_where.'
		';
        
        return Fs2psTools::array_column(Fs2psTools::dbSelect($sql), $this->row_id_field);
    }
    
    /**
     * Sólo debe usarse esta función en caso de que el dto tenga información del oid.
     */
    public function rowIdsFromDto($dto)
    {
        if ($this->multimatch) {
            return $this->_rowIdsFromOid($this->dtoOid($dto));
        } else {
            $row_id = $this->rowIdFromDto($dto);
            return $row_id? array($row_id) : array();
        }
    }
    
}
    
class Fs2psDto2RowDirectMatcher extends Fs2psDto2RowOidMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false)
    {
        parent::__construct($entity, $table, null, $dto_id_fields, $dto_id_fields, $persist, $cache);
    }
    
    public function _rowIdFromOid($oid) { return ((int)$oid)? (int)$oid : null; }
    public function _oidStrFromRowId($row_id) { return $this->dtoIdToStr($row_id); }
}

class Fs2psDto2RowNullMatcher extends Fs2psDto2RowOidMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false)
    {
        parent::__construct($entity, $table, null, $dto_id_fields, $dto_id_fields, $persist, $cache);
    }
    
    public function _rowIdFromOid($oid) { return null; }
    public function _oidStrFromRowId($row_id) { return null; }
}


class Fs2psDto2RowBasicMatcher extends Fs2psDto2RowMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false)
    {
        parent::__construct($entity, $table, null, $dto_id_fields, $persist, $cache);
    }
}

class Fs2psDto2RowRefMatcher extends Fs2psDto2RowOidMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false, $multimatch=false)
    {
        parent::__construct($entity, $table, $dto_id_fields, $dto_id_fields, 'reference', $persist, $cache, $multimatch);
    }
    
    // Fs2psDto2RowRefMatcher es un caso especial de Fs2psDto2RowOidMatcher en el que se cumple que dto_id_fields = dto_oid_fields
    public function _rowIdFromDtoId($dto_id)
    {
        return $this->rowIdFromDtoOid($dto_id, $dto_id);
    }
}

class Fs2psDto2RowPRefMatcher extends Fs2psDto2RowOidMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false, $multimatch=false)
    {
        parent::__construct($entity, $table, $dto_id_fields, array('pref'), 'reference', $persist, $cache, $multimatch);
    }
}

class Fs2psDto2RowEanMatcher extends Fs2psDto2RowOidMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false, $multimatch=false)
    {
        parent::__construct($entity, $table, $dto_id_fields, array('ean'), 'ean13', $persist, $cache, $multimatch);
    }
}

class Fs2psDto2MultiRowRefMatcher extends Fs2psDto2RowRefMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false)
    {
        parent::__construct($entity, $table, $dto_id_fields, $persist, $cache, true);
    }
}

class Fs2psDto2MultiRowPRefMatcher extends Fs2psDto2RowPRefMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false)
    {
        parent::__construct($entity, $table, $dto_id_fields, $persist, $cache, true);
    }
}

class Fs2psDto2MultiRowEanMatcher extends Fs2psDto2RowEanMatcher
{
    public function __construct($entity, $table, $dto_id_fields, $persist=false, $cache=false)
    {
        parent::__construct($entity, $table, $dto_id_fields, $persist, $cache, true);
    }
}

