<?php

namespace App\Database;

class Migration {
    private $db;
    private $migrations = [];

    public function __construct() {
        $this->db = (new \DB())->connect();
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();
    }

    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->exec($sql);
    }

    public function addMigration($migrationClass) {
        $this->migrations[] = new $migrationClass($this->db);
    }

    public function migrate() {
        // Get already executed migrations
        $executed = $this->getExecutedMigrations();

        foreach ($this->migrations as $migration) {
            if (!in_array(get_class($migration), $executed)) {
                try {
                    // Begin transaction
                    $this->db->beginTransaction();
                    
                    // Run the migration
                    $migration->up();
                    
                    // Record the migration
                    $this->recordMigration(get_class($migration));
                    
                    // Commit transaction
                    $this->db->commit();
                    
                    echo "Migrated: " . get_class($migration) . "\n";
                } catch (\Exception $e) {
                    // Rollback on error
                    $this->db->rollBack();
                    echo "Error in migration " . get_class($migration) . ": " . $e->getMessage() . "\n";
                }
            }
        }
    }

    private function getExecutedMigrations() {
        $stmt = $this->db->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function recordMigration($migration) {
        $stmt = $this->db->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$migration]);
    }

    public function rollback() {
        // Get the last executed migration
        $stmt = $this->db->query("SELECT migration FROM migrations ORDER BY id DESC LIMIT 1");
        $lastMigration = $stmt->fetch(\PDO::FETCH_COLUMN);

        if ($lastMigration) {
            foreach ($this->migrations as $migration) {
                if (get_class($migration) === $lastMigration) {
                    try {
                        // Begin transaction
                        $this->db->beginTransaction();
                        
                        // Run the rollback
                        $migration->down();
                        
                        // Remove the migration record
                        $stmt = $this->db->prepare("DELETE FROM migrations WHERE migration = ?");
                        $stmt->execute([$lastMigration]);
                        
                        // Commit transaction
                        $this->db->commit();
                        
                        echo "Rolled back: " . $lastMigration . "\n";
                    } catch (\Exception $e) {
                        // Rollback on error
                        $this->db->rollBack();
                        echo "Error in rollback " . $lastMigration . ": " . $e->getMessage() . "\n";
                    }
                    break;
                }
            }
        }
    }
} 