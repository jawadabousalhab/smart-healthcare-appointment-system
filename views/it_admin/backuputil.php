<?php

class BackupUtil
{
    /**
     * Generates backup data from the database based on the specified type.
     * This is a placeholder; actual implementation depends on how you want to extract data.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param int $itAdminId The ID of the IT admin performing the backup (for filtering).
     * @param string $backupType The type of backup (e.g., 'full', 'clinics', 'doctors').
     * @return array The backup data as an associative array.
     */
    public static function generateBackupData(PDO $pdo, int $itAdminId, string $backupType): array
    {
        $data = [];

        // Example placeholder logic:
        // In a real scenario, you would fetch data from tables based on $backupType.
        // For partial backups related to an IT admin, you would filter by clinics assigned
        // to that IT admin using the `clinic_it_admins` table.

        switch ($backupType) {
            case 'full':
                // Fetch data from all relevant tables
                $data['users'] = self::fetchTableData($pdo, 'users');
                $data['clinics'] = self::fetchTableData($pdo, 'clinics');
                $data['doctors'] = self::fetchTableData($pdo, 'doctors');
                $data['appointments'] = self::fetchTableData($pdo, 'appointments');
                $data['clinic_doctors'] = self::fetchTableData($pdo, 'clinic_doctors');
                $data['clinic_it_admins'] = self::fetchTableData($pdo, 'clinic_it_admins');
                $data['activity_logs'] = self::fetchTableData($pdo, 'activity_logs');
                $data['notifications'] = self::fetchTableData($pdo, 'notifications');
                $data['password_resets'] = self::fetchTableData($pdo, 'password_resets');
                $data['reviews'] = self::fetchTableData($pdo, 'reviews');
                // Add any other tables needed for a full backup
                break;
            case 'clinics':
                // Fetch clinics data, possibly filtered by IT admin's assigned clinics
                $data['clinics'] = self::fetchClinicsForAdmin($pdo, $itAdminId);
                break;
            case 'doctors':
                // Fetch doctors data, possibly filtered by clinics assigned to IT admin
                $data['doctors'] = self::fetchDoctorsForAdminClinics($pdo, $itAdminId);
                $data['clinic_doctors'] = self::fetchClinicDoctorsForAdminClinics($pdo, $itAdminId);
                break;
            case 'appointments':
                // Fetch appointments data, possibly filtered by clinics assigned to IT admin
                $data['appointments'] = self::fetchAppointmentsForAdminClinics($pdo, $itAdminId);
                break;
            case 'assignments':
                // Fetch clinic-doctor assignments and clinic-IT admin assignments
                $data['clinic_doctors'] = self::fetchClinicDoctorsForAdminClinics($pdo, $itAdminId);
                $data['clinic_it_admins'] = self::fetchClinicItAdminsForAdmin($pdo, $itAdminId);
                break;
            default:
                throw new Exception("Invalid backup type for data generation.");
        }

        return $data;
    }

    /**
     * Helper to fetch all data from a table.
     */
    private static function fetchTableData(PDO $pdo, string $tableName): array
    {
        $stmt = $pdo->query("SELECT * FROM `$tableName`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches clinics assigned to the IT admin.
     */
    private static function fetchClinicsForAdmin(PDO $pdo, int $itAdminId): array
    {
        $stmt = $pdo->prepare("SELECT c.* FROM clinics c JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id WHERE cia.it_admin_id = ?");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches doctors associated with clinics assigned to the IT admin.
     */
    private static function fetchDoctorsForAdminClinics(PDO $pdo, int $itAdminId): array
    {
        $stmt = $pdo->prepare("SELECT DISTINCT d.* FROM doctors d
                               JOIN clinic_doctors cd ON d.doctor_id = cd.doctor_id
                               JOIN clinic_it_admins cia ON cd.clinic_id = cia.clinic_id
                               WHERE cia.it_admin_id = ?");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches appointments associated with clinics assigned to the IT admin.
     */
    private static function fetchAppointmentsForAdminClinics(PDO $pdo, int $itAdminId): array
    {
        $stmt = $pdo->prepare("SELECT a.* FROM appointments a
                               JOIN clinics c ON a.clinic_id = c.clinic_id
                               JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
                               WHERE cia.it_admin_id = ?");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches clinic-doctor assignments for clinics assigned to the IT admin.
     */
    private static function fetchClinicDoctorsForAdminClinics(PDO $pdo, int $itAdminId): array
    {
        $stmt = $pdo->prepare("SELECT cd.* FROM clinic_doctors cd
                               JOIN clinic_it_admins cia ON cd.clinic_id = cia.clinic_id
                               WHERE cia.it_admin_id = ?");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches clinic-IT admin assignments for the current IT admin.
     */
    private static function fetchClinicItAdminsForAdmin(PDO $pdo, int $itAdminId): array
    {
        $stmt = $pdo->prepare("SELECT cia.* FROM clinic_it_admins cia WHERE cia.it_admin_id = ?");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Creates a backup file from the generated data.
     *
     * @param array $backupData The data to backup.
     * @param string $filepath The full path where the backup file should be saved.
     * @return array File information (e.g., 'size').
     */
    public static function createBackupFile(array $backupData, string $filepath): array
    {
        // Encode data as JSON
        $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($jsonContent === false) {
            throw new Exception("Failed to encode backup data to JSON: " . json_last_error_msg());
        }

        // Write to file
        $bytesWritten = file_put_contents($filepath, $jsonContent);

        if ($bytesWritten === false) {
            throw new Exception("Failed to write backup file: " . error_get_last()['message']);
        }

        return ['size' => $bytesWritten];
    }

    /**
     * Restores data from a JSON backup file to the database.
     * This is the core restoration logic. It handles different backup types.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param string $filepath The full path to the JSON backup file.
     * @param string $backupType The type of backup (e.g., 'full', 'clinics').
     * @return bool True on success, false on failure.
     */
    public static function restoreDataFromJson(PDO $pdo, string $filepath, string $backupType): bool
    {
        if (!file_exists($filepath)) {
            throw new Exception("Backup file does not exist at: " . $filepath);
        }

        $jsonContent = file_get_contents($filepath);
        if ($jsonContent === false) {
            throw new Exception("Failed to read backup file: " . error_get_last()['message']);
        }

        $backupData = json_decode($jsonContent, true);
        if ($backupData === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON from backup file: " . json_last_error_msg());
        }

        // --- Important: Restoration Logic ---
        // You MUST handle the order of restoration due to foreign key constraints.
        // For example, clinics must exist before doctors, and doctors/clinics before appointments.
        // It's also critical to decide on the conflict resolution strategy:
        // - TRUNCATE and INSERT (destructive, for full or specific table overwrites)
        // - INSERT IGNORE (skip if primary key exists)
        // - REPLACE (delete and re-insert if primary key exists, or insert new)
        // - INSERT ... ON DUPLICATE KEY UPDATE (update if primary key exists, insert new)

        try {
            switch ($backupType) {
                case 'full':
                    // Order matters due to foreign keys: restore parent tables first
                    self::restoreTable($pdo, 'users', $backupData['users'] ?? [], 'REPLACE'); // Users might need special handling
                    self::restoreTable($pdo, 'clinics', $backupData['clinics'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'doctors', $backupData['doctors'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'clinic_doctors', $backupData['clinic_doctors'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'clinic_it_admins', $backupData['clinic_it_admins'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'appointments', $backupData['appointments'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'reviews', $backupData['reviews'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'notifications', $backupData['notifications'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'activity_logs', $backupData['activity_logs'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'password_resets', $backupData['password_resets'] ?? [], 'REPLACE');
                    // Ensure all tables from smarthealthcare (7).sql.txt are covered if full backup implies full restoration
                    break;
                case 'clinics':
                    self::restoreTable($pdo, 'clinics', $backupData['clinics'] ?? [], 'REPLACE');
                    break;
                case 'doctors':
                    // Restore doctors and their assignments
                    self::restoreTable($pdo, 'doctors', $backupData['doctors'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'clinic_doctors', $backupData['clinic_doctors'] ?? [], 'REPLACE');
                    break;
                case 'appointments':
                    self::restoreTable($pdo, 'appointments', $backupData['appointments'] ?? [], 'REPLACE');
                    break;
                case 'assignments':
                    self::restoreTable($pdo, 'clinic_doctors', $backupData['clinic_doctors'] ?? [], 'REPLACE');
                    self::restoreTable($pdo, 'clinic_it_admins', $backupData['clinic_it_admins'] ?? [], 'REPLACE');
                    break;
                default:
                    throw new Exception("Unsupported backup type for restoration: " . $backupType);
            }
            return true;
        } catch (Exception $e) {
            // Re-throw to be caught by the transaction in backups.php
            throw $e;
        }
    }

    /**
     * Generic function to restore data to a single table.
     * This uses REPLACE INTO, which will insert new rows or replace existing rows
     * if a primary key or unique index matches. Be careful with this strategy.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param string $tableName The name of the table to restore.
     * @param array $data The array of associative arrays representing table rows.
     * @param string $strategy SQL strategy for insertion (e.g., 'INSERT', 'REPLACE', 'INSERT IGNORE').
     */
    private static function restoreTable(PDO $pdo, string $tableName, array $data, string $strategy = 'REPLACE')
    {
        if (empty($data)) {
            return; // Nothing to restore
        }

        // Get column names from the first row (assuming all rows have same columns)
        $columns = array_keys($data[0]);
        $columnNames = '`' . implode('`, `', $columns) . '`';
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        // Prepare the statement outside the loop for efficiency
        $sql = "{$strategy} INTO `{$tableName}` ({$columnNames}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);

        foreach ($data as $row) {
            // Ensure the order of values matches the order of columns
            $values = [];
            foreach ($columns as $col) {
                $values[] = $row[$col];
            }
            $stmt->execute($values);
        }
    }

    /**
     * Formats bytes into human-readable units (KB, MB, GB).
     * This function is also part of the previous backups.php,
     * but including it here if BackupUtil becomes a standalone utility.
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
