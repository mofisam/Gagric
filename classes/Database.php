<?php
class Database {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '1234';
    private $database = 'greenagric';
    public $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);

        if ($this->conn->connect_error) {
            $this->logError("Connection failed: " . $this->conn->connect_error);
            throw new Exception("Database connection failed.");
        }

        $this->conn->set_charset("utf8mb4");
    }

    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->logError("Prepare failed: " . $this->conn->error . " | SQL: $sql");
            throw new Exception("Prepare statement failed.");
        }

        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $this->logError("Query execution failed: " . $stmt->error . " | SQL: $sql");
            throw new Exception("Query execution failed.");
        }

        return $stmt;
    }

    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            return $data;
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            return null;
        }
    }

    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $data;
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            return [];
        }
    }

    public function insert($table, $data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

            $stmt = $this->query($sql, array_values($data));
            $id = $this->conn->insert_id;
            $stmt->close();
            return $id;
        } catch (Exception $e) {
            $this->logError("Insert failed: " . $e->getMessage());
            return false;
        }
    }

    public function update($table, $data, $where, $whereParams = []) {
        try {
            $setClause = implode(' = ?, ', array_keys($data)) . ' = ?';
            $sql = "UPDATE $table SET $setClause WHERE $where";

            $params = array_merge(array_values($data), $whereParams);
            $stmt = $this->query($sql, $params);
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            $this->logError("Update failed: " . $e->getMessage());
            return false;
        }
    }

    private function getParamTypes($params) {
        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
        }
        return $types;
    }

    // LOGGING FUNCTION
    private function logError($message) {
        error_log(
            "[" . date("Y-m-d H:i:s") . "] " . $message . "\n",
            3,
            __DIR__ . "/logs/error.log"
        );
    }

    public function close() {
        $this->conn->close();
    }
}
?>
