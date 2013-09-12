<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adodb/adodb.inc.php');

/**
 * Classe de conexão com o Middleware UFSC (Singleton)
 */
class Middleware {

    /**
     * Objeto de conexão com o banco de dados.
     *
     * É possível realizar consultas diretamente por ele utilizando a API do ADODB,
     * ou utilizando as funções de consultas desta classe, que imitam as consultas do Moodle.
     *
     * @var ADOConnection referência a conexão com o banco de dados (usando ADODB)
     */
    public $db;

    public $lasterror;

    /**
     * Padrões de busca e substituição de nomes de tabelas.
     * @var array chave sendo a expressão regular de pesquisa e valor sendo a string de substituição
     */
    private static $patterns;

    // Singleton - http://php.net/manual/pt_BR/language.oop5.patterns.php

    private static $instance;

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    /**
     * Singleton - Retorna instância única da classe
     *
     * @return Middleware instância da classe
     */
    public static function singleton() {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }

        return self::$instance;
    }

    public function configured() {
        return (isset($this->dbname) && isset($this->contexto));
    }


    private function __construct() {
        global $CFG;

        // Carrega configurações do local_academico
        $config = get_config("local_academico");

        // Carrega configurações de prefixo do banco de dados do Moodle
        $moodle_prefix = empty($CFG->prefix) ? $CFG->dbname : "{$CFG->dbname}.{$CFG->prefix}";

        // Verifica se o plugin está configurado
        if (empty($config->dbname) || empty($config->contexto))
            return false;

        // Configura padrões de substituição de nomes de tabelas no SQL
        self::$patterns = array(
            '/\{view_([a-zA-Z][0-9a-zA-Z_]*)\}/i' => $config->dbname . '.View_' . $config->contexto . '_$1',
            '/\{geral_([a-zA-Z][0-9a-zA-Z_]*)\}/i' => $config->dbname . '.View_Geral_$1',
            '/\{table_([a-zA-Z][0-9a-zA-Z_]*)\}/i' => $config->dbname . '.$1',
            '/\{([a-z][a-z0-9_]*)\}/' => $moodle_prefix.'.$1'); // regexp do moodle, precisa ser o útlimo

        // Inicializa conexão com a base de dados
        $db = $this->db_init($config);

        // Define atributos
        $this->db =& $db;
        $this->dbname = $config->dbname;
        $this->contexto = $config->contexto;
    }

    // Singleton

    public function count_records_sql($sql, array $params=null) {
        if ($count = $this->get_field_sql($sql, $params)) {
            return $count;
        } else {
            return 0;
        }
    }

    /**
     * Executa um comando SQL no banco de dados
     *
     * @param string $sql
     * @param array $params
     * @return bool|ADORecordSet
     */
    public function execute($sql, array $params=null) {
        $rawsql = $this->fix_names($sql);
        list($rawsql, $params) = $this->fix_sql_params($rawsql, $params);

        return $this->db->Execute($rawsql, $params);
    }

    public function get_field_sql($sql, array $params=null) {
        if (!$record = $this->get_record_sql($sql, $params)) {
            return false;
        }

        $record = (array)$record;
        return reset($record); // first column
    }

    public function get_record_sql($sql, array $params=null) {
        $records = $this->get_records_sql($sql, $params, 0, 1);

        return $records ? reset($records) : false;
    }

    public function get_records_sql($sql, array $params=null, $limitfrom=0, $limitnum=0) {

        $limitfrom = (int)$limitfrom;
        $limitnum  = (int)$limitnum;
        $limitfrom = ($limitfrom < 0) ? 0 : $limitfrom;
        $limitnum  = ($limitnum < 0)  ? 0 : $limitnum;

        if ($limitfrom or $limitnum) {
            if ($limitnum < 1) {
                $limitnum = "18446744073709551615";
            }
            $sql .= " LIMIT $limitfrom, $limitnum";
        }

        $rs = $this->execute($sql, $params);

        if (!$rs) {
            throw new dml_read_exception($this->db->ErrorMsg(), $sql, $params);
        }

        $result = array();
        while (!$rs->EOF) {

            $id = reset($rs->fields);

            // poor query check
            if (isset($result[$id])) {
                $colname = key($row);
                debugging("Did you remember to make the first column something unique in your call to get_records? Duplicate value '$id' found in column '$colname'.", DEBUG_DEVELOPER);
            }

            $result[$id] = $rs->FetchObject(false);
            $rs->MoveNext();
        }

        return $result;
    }

    public function get_records_sql_menu($sql, array $params=null, $limitfrom=0, $limitnum=0) {
        $menu = array();
        if ($records = $this->get_records_sql($sql, $params, $limitfrom, $limitnum)) {
            foreach ($records as $record) {
                $record = (array)$record;
                $key   = array_shift($record);
                $value = array_shift($record);
                $menu[$key] = $value;
            }
        }
        return $menu;
    }

    /**
     * Retorna o RecordSet da consulta e permite manipulações mais avançadas dos dados.
     *
     * @param string $sql
     * @param array $params
     * @param int $limitfrom
     * @param int $limitnum
     * @return ADORecordSet|bool
     * @throws dml_read_exception
     */
    public function get_recordset_sql($sql, array $params=null, $limitfrom=0, $limitnum=0) {

        $limitfrom = (int)$limitfrom;
        $limitnum  = (int)$limitnum;
        $limitfrom = ($limitfrom < 0) ? 0 : $limitfrom;
        $limitnum  = ($limitnum < 0)  ? 0 : $limitnum;

        if ($limitfrom or $limitnum) {
            if ($limitnum < 1) {
                $limitnum = "18446744073709551615";
            }
            $sql .= " LIMIT $limitfrom, $limitnum";
        }

        $rs = $this->execute($sql, $params);

        if (!$rs) {
            throw new dml_read_exception($this->db->ErrorMsg(), $sql, $params);
        }

        return $rs;
    }

    /**
     * @param string $sql
     * @param array $params
     * @return bool|int
     */
    public function insert_record_sql($sql, array $params=null) {

        $rs = $this->execute($sql, $params);

        return ($rs) ? $this->db->Insert_ID() : false;
    }

    /**
     * @param stdClass $config
     * @return ADOConnection
     */
    private function db_init($config) {
        global $CFG;

        // Connect to the external database (forcing new connection)
        $externaldb = ADONewConnection($CFG->dbtype);

        // Considerando os dados de conexão do config.php e apenas alterando o dbname pelas configurações do plugin.
        $externaldb->Connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $config->dbname, true);
        $externaldb->SetFetchMode(ADODB_FETCH_ASSOC);
        $externaldb->SetCharSet('utf8');

        return $externaldb;
    }

    /**
     * Detects object parameters and throws exception if found
     * @param mixed $value
     * @return void
     * @throws coding_exception if object detected
     */
    private function detect_objects($value) {
        if (is_object($value)) {
            throw new coding_exception('Invalid database query parameter value', 'Objects are are not allowed: '.get_class($value));
        }
    }

    /**
     * @param $sql string Consulta SQL que deverá ter nomes de tabelas substituídas
     * @return mixed
     */
    public function fix_names($sql) {
        return preg_replace(array_keys(self::$patterns), array_values(self::$patterns), $sql);
    }

    /**
     * Normalizes sql query parameters and verifies parameters.
     * @param string $sql The query or part of it.
     * @param array $params The query parameters.
     * @return array (sql, params, type of params)
     */
    public function fix_sql_params($sql, array $params=null) {
        $params = (array)$params; // mke null array if needed
        $allowed_types = SQL_PARAMS_QM; // mysqli

        // cast booleans to 1/0 int and detect forbidden objects
        foreach ($params as $key => $value) {
            $this->detect_objects($value);
            $params[$key] = is_bool($value) ? (int)$value : $value;
        }

        // NICOLAS C: Fixed regexp for negative backwards look-ahead of double colons. Thanks for Sam Marshall's help
        $named_count = preg_match_all('/(?<!:):[a-z][a-z0-9_]*/', $sql, $named_matches); // :: used in pgsql casts
        $dollar_count = preg_match_all('/\$[1-9][0-9]*/', $sql, $dollar_matches);
        $q_count     = substr_count($sql, '?');

        $count = 0;

        if ($named_count) {
            $type = SQL_PARAMS_NAMED;
            $count = $named_count;

        }
        if ($dollar_count) {
            if ($count) {
                throw new dml_exception('mixedtypesqlparam');
            }
            $type = SQL_PARAMS_DOLLAR;
            $count = $dollar_count;

        }
        if ($q_count) {
            if ($count) {
                throw new dml_exception('mixedtypesqlparam');
            }
            $type = SQL_PARAMS_QM;
            $count = $q_count;

        }

        if (!$count) {
            // ignore params
            if ($allowed_types & SQL_PARAMS_NAMED) {
                return array($sql, array(), SQL_PARAMS_NAMED);
            } else if ($allowed_types & SQL_PARAMS_QM) {
                return array($sql, array(), SQL_PARAMS_QM);
            } else {
                return array($sql, array(), SQL_PARAMS_DOLLAR);
            }
        }

        if ($count > count($params)) {
            $a = new stdClass;
            $a->expected = $count;
            $a->actual = count($params);
            throw new dml_exception('invalidqueryparam', $a);
        }

        $target_type = $allowed_types;

        if ($type & $allowed_types) { // bitwise AND
            if ($count == count($params)) {
                if ($type == SQL_PARAMS_QM) {
                    return array($sql, array_values($params), SQL_PARAMS_QM); // 0-based array required
                } else {
                    //better do the validation of names below
                }
            }
            // needs some fixing or validation - there might be more params than needed
            $target_type = $type;
        }

        if ($type == SQL_PARAMS_NAMED) {
            $finalparams = array();
            foreach ($named_matches[0] as $key) {
                $key = trim($key, ':');
                if (!array_key_exists($key, $params)) {
                    throw new dml_exception('missingkeyinsql', $key, '');
                }
                if (strlen($key) > 30) {
                    throw new coding_exception(
                        "Placeholder names must be 30 characters or shorter. '" .
                                $key . "' is too long.", $sql);
                }
                $finalparams[$key] = $params[$key];
            }
            if ($count != count($finalparams)) {
                throw new dml_exception('duplicateparaminsql');
            }

            if ($target_type & SQL_PARAMS_QM) {
                $sql = preg_replace('/(?<!:):[a-z][a-z0-9_]*/', '?', $sql);
                return array($sql, array_values($finalparams), SQL_PARAMS_QM); // 0-based required
            } else if ($target_type & SQL_PARAMS_NAMED) {
                return array($sql, $finalparams, SQL_PARAMS_NAMED);
            }

        } else if ($type == SQL_PARAMS_QM) {
            if (count($params) != $count) {
                $params = array_slice($params, 0, $count);
            }

            if ($target_type & SQL_PARAMS_QM) {
                return array($sql, array_values($params), SQL_PARAMS_QM); // 0-based required
            } else if ($target_type & SQL_PARAMS_NAMED) {
                $finalparams = array();
                $pname = 'param0';
                $parts = explode('?', $sql);
                $sql = array_shift($parts);
                foreach ($parts as $part) {
                    $param = array_shift($params);
                    $pname++;
                    $sql .= ':'.$pname.$part;
                    $finalparams[$pname] = $param;
                }
                return array($sql, $finalparams, SQL_PARAMS_NAMED);
            } else {  // $type & SQL_PARAMS_DOLLAR
                //lambda-style functions eat memory - we use globals instead :-(
                $this->fix_sql_params_i = 0;
                $sql = preg_replace_callback('/\?/', array($this, '_fix_sql_params_dollar_callback'), $sql);
                return array($sql, array_values($params), SQL_PARAMS_DOLLAR); // 0-based required
            }
        }
    }

}

/**
 * Classe com funcionalidades auxiliares que são comuns a vários projetos, e sejam relacionados a consultas com o Middleware
 */
class MiddlewareUtil {

    /**
     * Retorna os cursos UFSC ativos neste contexto
     * @return array
     */
    static function get_cursos_ufsc() {
        $middleware = Middleware::singleton();

        if (!$middleware->configured())
            return false;

        return $middleware->get_records_sql_menu("SELECT cursos.curso, cursos.nome FROM {View_Cursos_Ativos} cursos");
    }
}
