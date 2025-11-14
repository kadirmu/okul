<?php

class Database {
    private $host = 'test.mutecno.com';
    private $db_name = 'mutecno_okul';
    private $username = 'mutecno_okul';
    private $password = '15797530Mk-';
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            echo "Bağlantı Hatası: " . $e->getMessage();
            die();
        }

        return $this->conn;
    }
}