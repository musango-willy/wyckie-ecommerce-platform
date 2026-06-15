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
        /**
     * Execute SQL Query and return structured results
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        // If the query is an INSERT, UPDATE, or DELETE, stop here and return an empty array
        if (preg_match('/^\s*(insert|update|delete|alter)/i', $sql)) {
            return [];
        }
        
        $results = $stmt->fetchAll();
        
        // If we requested a LIMIT 1 query, automatically flatten the array wrapper down to a single row
        if (strpos(strtolower($sql), 'limit 1') !== false && !empty($results)) {
            return $results[0];
        }
        
        return $results;
    }
}

