<?php
require_once  '../../config/db.php';

/**
 * Backup Utility Class for Smart Healthcare System
 */
class BackupUtil
{
    /**
     * Generate backup data based on type and IT admin permissions
     */
    public static function generateBackupData($pdo, $itAdminId, $type)
    {
        $data = [
            'metadata' => [
                'system' => 'Smart Healthcare',
                'version' => '1.0',
                'backup_type' => $type,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $itAdminId
            ]
        ];

        switch ($type) {
            case 'full':
                $data['clinics'] = self::getClinicsData($pdo, $itAdminId);
                $data['doctors'] = self::getDoctorsData($pdo, $itAdminId);
                $data['assignments'] = self::getAssignmentsData($pdo, $itAdminId);
                break;

            case 'clinics':
                $data['clinics'] = self::getClinicsData($pdo, $itAdminId);
                break;

            case 'doctors':
                $data['doctors'] = self::getDoctorsData($pdo, $itAdminId);
                break;

            case 'assignments':
                $data['assignments'] = self::getAssignmentsData($pdo, $itAdminId);
                break;
        }

        return $data;
    }

    /**
     * Get clinic data that the IT admin manages
     */
    private static function getClinicsData($pdo, $itAdminId)
    {
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM clinics c
            JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
            WHERE cia.it_admin_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get doctor data assigned to clinics the IT admin manages
     */
    private static function getDoctorsData($pdo, $itAdminId)
    {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.email, u.phone_number, u.specialization, u.created_at
            FROM users u
            JOIN clinic_doctors cd ON u.user_id = cd.doctor_id
            JOIN clinic_it_admins cia ON cd.clinic_id = cia.clinic_id
            WHERE cia.it_admin_id = ? AND u.role = 'doctor'
            GROUP BY u.user_id
            ORDER BY u.name
        ");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get clinic-doctor assignments for clinics the IT admin manages
     */
    private static function getAssignmentsData($pdo, $itAdminId)
    {
        $stmt = $pdo->prepare("
            SELECT cd.clinic_id, c.name as clinic_name, 
                   cd.doctor_id, u.name as doctor_name
            FROM clinic_doctors cd
            JOIN clinics c ON cd.clinic_id = c.clinic_id
            JOIN users u ON cd.doctor_id = u.user_id
            JOIN clinic_it_admins cia ON cd.clinic_id = cia.clinic_id
            WHERE cia.it_admin_id = ?
            ORDER BY c.name, u.name
        ");
        $stmt->execute([$itAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a physical backup file
     */
    public static function createBackupFile($data, $backupType)
    {
        $backupDir = __DIR__ . '/../../backups';
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup_' . date('Ymd_His') . '_' . $backupType . '.json';
        $filepath = $backupDir . '/' . $filename;

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath)
        ];
    }

    /**
     * Format bytes to human-readable format
     */
    public static function formatSize($bytes)
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

    /**
     * Validate backup file before restoration
     */
    public static function validateBackupFile($filepath)
    {
        if (!file_exists($filepath)) {
            throw new Exception("Backup file not found");
        }

        $content = file_get_contents($filepath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON format in backup file");
        }

        if (!isset($data['metadata']['backup_type'])) {
            throw new Exception("Backup type not specified in metadata");
        }

        return $data;
    }

    /**
     * Restore backup data to database
     */
    public static function restoreBackup($pdo, $backupData, $itAdminId)
    {
        $pdo->beginTransaction();

        try {
            // Verify the IT admin has permission to restore this data
            self::verifyRestorePermissions($pdo, $backupData, $itAdminId);

            // Restore based on backup type
            switch ($backupData['metadata']['backup_type']) {
                case 'full':
                    self::restoreClinics($pdo, $backupData['clinics'] ?? [], $itAdminId);
                    self::restoreDoctors($pdo, $backupData['doctors'] ?? [], $itAdminId);
                    self::restoreAssignments($pdo, $backupData['assignments'] ?? [], $itAdminId);
                    break;

                case 'clinics':
                    self::restoreClinics($pdo, $backupData['clinics'] ?? [], $itAdminId);
                    break;

                case 'doctors':
                    self::restoreDoctors($pdo, $backupData['doctors'] ?? [], $itAdminId);
                    break;

                case 'assignments':
                    self::restoreAssignments($pdo, $backupData['assignments'] ?? [], $itAdminId);
                    break;
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Verify the IT admin has permission to restore this backup
     */
    private static function verifyRestorePermissions($pdo, $backupData, $itAdminId)
    {
        // In a real implementation, you would check if the IT admin has
        // permission to restore the specific data in the backup
        // This is a simplified version
        return true;
    }

    private static function restoreClinics($pdo, $clinics, $itAdminId)
    {
        // Implementation for restoring clinics
        // Would include checks for existing records, updates, etc.
    }

    private static function restoreDoctors($pdo, $doctors, $itAdminId)
    {
        // Implementation for restoring doctors
    }

    private static function restoreAssignments($pdo, $assignments, $itAdminId)
    {
        // Implementation for restoring clinic-doctor assignments
    }
}
