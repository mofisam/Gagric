<?php
class Database {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '1234';
    private $database = 'greenagric';

    public $conn;

    public function __construct() {

        mysqli_report(MYSQLI_REPORT_OFF);

        $this->conn = new mysqli(
            $this->host,
            $this->username,
            $this->password,
            $this->database
        );

        if ($this->conn->connect_error) {
            $this->logError(
                "Database connection failed: " . $this->conn->connect_error,
                __FILE__,
                __LINE__
            );
            throw new Exception("Database connection failed.");
        }

        $this->conn->set_charset("utf8mb4");
    }

    public function query($sql, $params = []) {

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            $this->logError(
                "Prepare failed: " . $this->conn->error . " | SQL: $sql",
                __FILE__,
                __LINE__
            );
            throw new Exception("Database prepare failed.");
        }

        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $this->logError(
                "Query execution failed: " . $stmt->error . " | SQL: $sql",
                __FILE__,
                __LINE__
            );
            throw new Exception("Database query execution failed.");
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

            $this->logError($e->getMessage(), $e->getFile(), $e->getLine());

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

            $this->logError($e->getMessage(), $e->getFile(), $e->getLine());

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

            $this->logError(
                "Insert failed: " . $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );

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

            $this->logError(
                "Update failed: " . $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );

            return false;
        }
    }

    private function getParamTypes($params) {

        $types = '';

        foreach ($params as $param) {

            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }

        }

        return $types;
    }

    // IMPROVED LOGGING
    private function logError($message, $file = '', $line = '') {

        $logDir = __DIR__ . "/logs";

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . "/error.log";

        $log = sprintf(
            "[%s] %s | File: %s | Line: %s%s",
            date("Y-m-d H:i:s"),
            $message,
            $file,
            $line,
            PHP_EOL
        );

        error_log($log, 3, $logFile);
    }

    public function close() {

        if ($this->conn) {
            $this->conn->close();
        }

    }
}
?>