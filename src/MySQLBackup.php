<?php

/**
 * MySQL Backup & Restore Library (CodeIgniter 4 Compatible)
 *
 * This library provides functionalities for backing up and restoring MySQL databases,
 * modified to work with the CodeIgniter 4 database abstraction layer.
 *
 * Originally created by: Ramazan Çetinkaya <ramazancetinkayadev@hotmail.com>
 * Modified for CodeIgniter 4 by: Shahzaib <zaiblab@yahoo.com>
 *
 * @category Library
 * @package  MySQLBackup
 * @version  1.1
 * @license  MIT License
 */

namespace DatabaseBackupManager;

use CodeIgniter\Database\BaseConnection;
use ZipArchive;
use Exception;

class MySQLBackup
{
    /**
     * The CodeIgniter 4 database object instance.
     * 
     * @var BaseConnection
     */
    private BaseConnection $db;

    /**
     * The directory to store backup files.
     * 
     * @var string
     */
    private string $backupFolder;

    /**
     * @param BaseConnection $db CI4 DB instance
     * @param string $backupFolder Path to the backup folder
     */
    public function __construct(BaseConnection $db, string $backupFolder = 'backup')
    {
        $this->db = $db;
        $this->backupFolder = rtrim($backupFolder, '/') . '/';
        $this->checkBackupFolder();

        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not found. Enable the Zip module in your PHP configuration.');
        }
    }

    /**
     * Check if the backup folder exists and has appropriate permissions.
     * If the folder does not exist, attempt to create it with appropriate permissions.
     * If the folder exists but does not have write permissions, attempt to set the permissions.
     *
     * @throws Exception If unable to create or set permissions for the backup folder.
     */
    private function checkBackupFolder(): void
    {
        if (!file_exists($this->backupFolder) && !mkdir($this->backupFolder, 0755, true)) {
            throw new Exception('Failed to create backup folder.');
        }

        if (!is_writable($this->backupFolder) && !chmod($this->backupFolder, 0755)) {
            throw new Exception('Failed to set write permissions for backup folder.');
        }
    }

    /**
     * Backup the database tables.
     * 
     * @param array|string|null $tables Names of the tables to backup. If null, all tables will be backed up.
     * @param bool $includeData Whether to include table data in the backup.
     * @param bool $archive Whether to archive the backup file.
     * @return array
     * @throws Exception If backup process fails.
     */
    public function backup($tables = null, bool $includeData = true, bool $archive = false): array
    {
        try {
            $this->db->simpleQuery('SET foreign_key_checks = 0');
            $this->db->transStart();

            $backupFileName = $this->generateBackupFileName($tables);
            $backupFile = fopen($backupFileName, 'w');
            if (!$backupFile) {
                throw new Exception('Failed to open backup file for writing.');
            }

            $this->writeBackupHeader($backupFile);
            fwrite($backupFile, "SET foreign_key_checks=0;\n\n");

            if ($tables) {
                $this->backupTables($tables, $includeData, $backupFile);
            } else {
                $this->backupAllTables($includeData, $backupFile);
            }

            fwrite($backupFile, "-- End of Database Backup Process");
            fclose($backupFile);

            if ($archive) {
                $backupFileName = $this->archiveBackupFile($backupFileName);
            }

            $this->db->transComplete();

            return [
                'file_name' => basename($backupFileName),
                'file_size' => filesize($backupFileName)
            ];
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        } finally {
            $this->db->simpleQuery('SET foreign_key_checks = 1');
        }
    }

    /**
     * Restore the database from a backup file.
     * 
     * @param string $backupFilePath Path to the backup file.
     * @param bool $dropTables Whether to drop existing tables before restoring data. Default is true.
     * @throws Exception If restore process fails.
     */
    public function restore(string $backupFilePath, bool $dropTables = true): bool
    {
        try {
            $this->db->transStart();

            if ($dropTables) {
                $tables = $this->extractTableNames($backupFilePath);
                $this->dropTables($tables);
            }

            $backupContent = file_get_contents($backupFilePath);
            $queries = explode(';', $backupContent);
            foreach ($queries as $query) {
                if (trim($query) !== '') {
                    $this->db->simpleQuery($query);
                }
            }

            $this->db->transComplete();
            return true;
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Extract table names from the backup file.
     * 
     * @param string $backupFilePath Path to the backup file.
     * @return array Table names extracted from the backup file.
     */
    private function extractTableNames(string $backupFilePath): array
    {
        $backupContent = file_get_contents($backupFilePath);
        preg_match_all('/Table structure for table `(\w+)`/', $backupContent, $matches);
        return $matches[1];
    }

    /**
     * Drop tables from the database.
     * 
     * @param array $tables Table names to be dropped.
     * @throws Exception If dropping tables fails.
     */
    private function dropTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->db->simpleQuery("DROP TABLE IF EXISTS `$table`");
        }
    }

    /**
     * Generate backup file name.
     * 
     * @param array|string|null $tables Names of the tables to backup.
     * @return string Backup file name.
     */
    private function generateBackupFileName($tables): string
    {
        $dbName = $this->db->query('SELECT DATABASE()')->getRowArray()['DATABASE()'];
        $fileName = $this->backupFolder . 'backup_' . $dbName . ($tables ? '-' . implode('_', (array) $tables) : '') . '-' . date('Y-m-d_His') . '.sql';
        return $fileName;
    }

    /**
     * Write backup header information to the backup file.
     * 
     * @param resource $backupFile File handle of the backup file.
     */
    private function writeBackupHeader($backupFile): void
    {
        fwrite($backupFile, "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n");
    }

    /**
     * Backup specified tables.
     * 
     * @param array|string $tables Names of the tables to backup.
     * @param bool $includeData Whether to include table data in the backup.
     * @param resource $backupFile File handle of the backup file.
     */
    private function backupTables($tables, bool $includeData, $backupFile): void
    {
        foreach ((array) $tables as $table) {
            $this->backupTableStructure($table, $backupFile);
            if ($includeData) {
                $this->backupTableData($table, $backupFile);
            }
        }
    }

    /**
     * Backup all tables in the database.
     * 
     * @param bool $includeData Whether to include table data in the backup.
     * @param resource $backupFile File handle of the backup file.
     */
    private function backupAllTables(bool $includeData, $backupFile): void
    {
        $query = $this->db->query("SHOW TABLES");
        $tables = array_map('current', $query->getResultArray());
        $this->backupTables($tables, $includeData, $backupFile);
    }

    /**
     * Backup table structure.
     * 
     * @param string $tableName Name of the table to backup.
     * @param resource $backupFile File handle of the backup file.
     */
    private function backupTableStructure(string $tableName, $backupFile): void
    {
        $query = $this->db->query("SHOW CREATE TABLE `$tableName`");
        $result = $query->getRowArray();
        fwrite($backupFile, "--\n-- Table Structure for Table `$tableName`\n--\n\n");
        fwrite($backupFile, $result['Create Table'] . ";\n\n");
    }

    /**
     * Backup table data.
     * 
     * @param string $tableName Name of the table to backup.
     * @param resource $backupFile File handle of the backup file.
     */
    private function backupTableData(string $tableName, $backupFile): void
    {
        $query = $this->db->query("SELECT * FROM `$tableName`");
        $tableData = $query->getResultArray();

        if (empty($tableData)) {
            fwrite($backupFile, "--\n-- No Data Found For Table `$tableName`\n--\n\n");
            return;
        }

        fwrite($backupFile, "--\n-- Dumping Data for Table `$tableName`\n--\n\n");
        fwrite($backupFile, "INSERT INTO `$tableName` (");
        $fields = array_keys($tableData[0]);
        fwrite($backupFile, "`" . implode("`, `", $fields) . "`");
        fwrite($backupFile, ") VALUES\n");

        foreach ($tableData as $row) {
            fwrite($backupFile, "(");
            $values = array_map(fn($v) => "'" . addslashes((string) $v) . "'", array_values($row));
            fwrite($backupFile, implode(", ", $values));
            fwrite($backupFile, "),\n");
        }

        fseek($backupFile, -2, SEEK_END);
        fwrite($backupFile, ";\n\n");
    }

    /**
     * Archive backup file.
     * 
     * @param string $backupFileName Path to the backup file.
     * @return string Path to the archived backup file.
     * @throws Exception If archiving fails.
     */
    private function archiveBackupFile(string $backupFileName): string
    {
        $zipFileName = $backupFileName . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($backupFileName, basename($backupFileName));
            $zip->close();
            unlink($backupFileName);
            return $zipFileName;
        } else {
            throw new Exception('Failed to create zip archive.');
        }
    }
}
