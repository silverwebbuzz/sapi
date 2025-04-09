<?php
require_once 'config.php';

class DB {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new DB();
        }
        return self::$instance->conn;
    }
}

class DBHelper {
    public static function select($query, $types = "", $params = []) {
        $conn = DB::getInstance();
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public static function selectOne($query, $types = "", $params = []) {
        $result = self::select($query, $types, $params);
        return $result ? $result[0] : null;
    }

    public static function insert($query, $types = "", $params = []) {
        $conn = DB::getInstance();
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $conn->insert_id;
    }

    public static function execute($query, $types = "", $params = []) {
        $conn = DB::getInstance();
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public static function createTable($sql) {
        $conn = DB::getInstance();
        return $conn->query($sql);
    }
}
//Select Multiple Rows:
/*$results = DBHelper::select(
    "SELECT id, status FROM stores WHERE shop = ? AND status = ?",
    "si", 
    [$shop, 1]
);*/


//Select Single Row:
/*$store = DBHelper::selectOne(
    "SELECT id, status FROM stores WHERE shop = ?",
    "s",
    [$shop]
);*/


//Insert Data:
/*$newId = DBHelper::insert(
    "INSERT INTO stores (shop, status) VALUES (?, ?)",
    "si",
    ["myshop.com", 1]
);*/


//Update Data:
/*
$affectedRows = DBHelper::execute(
    "UPDATE stores SET status = ? WHERE id = ?",
    "si",
    [0, $storeId]
);
*/


//Delete Data:
/*$affectedRows = DBHelper::execute(
    "DELETE FROM stores WHERE id = ?",
    "i",
    [$storeId]
); */

?>