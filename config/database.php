<?php
/**
 * TESA Syllabus Monitor
 * Conexión a Base de Datos - VERSIÓN SEGURA
 * 
 * Usa variables de entorno para credenciales
 */

// Prevenir acceso directo
if (!defined('APP_ACCESS')) {
    die('Acceso directo no permitido');
}

class Database {
    private static $instance = null;
    private $connection;
    
    // Configuración desde variables de entorno
    private $host;
    private $port;
    private $database;
    private $username;
    private $password;
    
    private function __construct() {
        // Obtener configuración desde variables de entorno
        $this->host = getenv('DB_HOST');
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->database = getenv('DB_DATABASE');
        $this->username = getenv('DB_USERNAME');
        $this->password = getenv('DB_PASSWORD');
        
        // Validar que todas las credenciales estén presentes
        if (!$this->host || !$this->database || !$this->username || !$this->password) {
            throw new Exception('Configuración de base de datos incompleta. Verifica las variables de entorno.');
        }
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            // Para Azure MySQL, agregar opciones SSL si es necesario
            if (strpos($this->host, 'azure.com') !== false || strpos($this->host, 'database.windows.net') !== false) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = true;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            logMessage("Conexión a base de datos establecida correctamente", 'INFO');
            
        } catch (PDOException $e) {
            logMessage("Error de conexión a base de datos: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error al conectar con la base de datos");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Ejecutar consulta SELECT y obtener todos los resultados
     */
    public function fetchAll($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            logMessage("Error en fetchAll: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Ejecutar consulta SELECT y obtener un solo resultado
     */
    public function fetchOne($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            logMessage("Error en fetchOne: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Ejecutar consulta INSERT/UPDATE/DELETE
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            logMessage("Error en execute: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Ejecutar INSERT y obtener ID insertado
     */
    public function insert($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            logMessage("Error en insert: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Ejecutar UPDATE
     */
    public function update($query, $params = []) {
        return $this->execute($query, $params);
    }
    
    /**
     * Ejecutar DELETE
     */
    public function delete($query, $params = []) {
        return $this->execute($query, $params);
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Verificar si hay una transacción activa
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton");
    }
}
