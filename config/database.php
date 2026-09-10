<?php
class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $this->connect();
    }

    private function connect(): void {
        $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4; SET time_zone = '-03:00'",
            ]);
        } catch (PDOException $e) {
            if (APP_ENV === 'development') die('DB Error: '.$e->getMessage());
            die('Erro de conexão com o banco. Contate o suporte.');
        }
    }

    private function reconnectIfNeeded(): void {
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            // MySQL server has gone away (2006) ou Lost connection (2013)
            if (in_array($e->errorInfo[1] ?? 0, [2006, 2013])) {
                $this->connect();
            } else {
                throw $e;
            }
        }
    }

    public static function getInstance(): Database {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function getConnection(): PDO { return $this->pdo; }

    public function query(string $sql, array $params=[]): PDOStatement {
        $this->reconnectIfNeeded();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastId(): string { return $this->pdo->lastInsertId(); }
    public function begin(): void    { $this->pdo->beginTransaction(); }
    public function commit(): void   { $this->pdo->commit(); }
    public function rollback(): void { $this->pdo->rollBack(); }

    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }
}
