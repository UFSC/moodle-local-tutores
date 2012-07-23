<?php

/**
 * Classe de conexão com o Middleware UFSC (Singleton)
 */
class Academico {

    /**
     * Referência ao acesso a base de dados
     */
    public $db;

    // Singleton - http://php.net/manual/pt_BR/language.oop5.patterns.php

    private static $instance;

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    /**
     * Singleton - Retorna instância única da classe
     *
     * @return Academico instância da classe
     */
    public static function singleton() {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }

        return self::$instance;
    }


    private function __construct() {
        global $DB;

        // Carrega configurações do enrol_ufsc
        $config = get_config("enrol_ufsc");

        // Define atributos
        $this->db =& $DB;
        $this->dbname = $config->dbname;
        $this->contexto = $config->contexto;
    }

    // Singleton

    /**
     * Método mágico para retornar nomes de views e tabelas como parâmetros.
     * Exemplo de utilização (contexto = Presencial):
     * <code>
     * $a = new Academico();
     * echo $a->table_usuarios_turma; // "middleware_unificado.usuarios_turma"
     * echo $a->view_usuarios_turma; // "middleware_unificado.ViewPresencialUsuariosTurmas"
     * </code>
     */
    function __get($attribute) {
        $a = explode('_', $attribute);
        $prefix = array_shift($a);

        if ($prefix == 'table') {
            return $this->table_name($a);
        } else if ($prefix == 'view') {
            return $this->view_name($a);
        }

    }

    private function table_name($array_names) {
        $table_name = "{$this->dbname}.";
        foreach ($array_names as $name) {
            $table_name .= ucfirst($name);
        }
        return $table_name;
    }

    private function view_name($array_names) {
        $table_name = "{$this->dbname}.View_{$this->contexto}";
        foreach ($array_names as $name) {
            $table_name .= '_' . ucfirst($name);
        }
        return $table_name;
    }

    private function view_geral_name($array_names) {
        $table_name = "{$this->dbname}.View_Geral";
        foreach ($array_names as $name) {
            $table_name .= '_' . ucfirst($name);
        }
        return $table_name;
    }
}

?>