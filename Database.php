<?php

namespace Wyckie\EcommercePlatform;

use PDO;
use PDOException;

class Database
{
    private ?PDO $connection = null;

    /**
     * Connect to the XAMPP MySQL Database
     */
    public function __construct(string $host = '127.0.0.1', string $dbName = 'ecommerce_db', string $user = 'root', string $password = '')
    {
        try {
            $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
            
            $this->connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Database Connection Failed: " . $e->getMessage());
        }
    }

    /**
     * Fetch all records from a table (e.g., pulling all products or orders)
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
