<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class SettingsController {

    public function getMunicipalityConfig() {
        requireAuth();
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $config = [];
        foreach ($rows as $row) {
            $config[$row['setting_key']] = $row['setting_value'];
        }

        $defaults = [
            'municipality_name' => env('APP_MUNICIPALITY_NAME', 'EmploySmart Municipality'),
            'municipality_code' => env('APP_MUNICIPALITY_CODE', 'general'),
            'municipality_region' => env('APP_MUNICIPALITY_REGION', 'Philippines'),
            'contact_office' => env('APP_CONTACT_OFFICE', 'PESO'),
            'contact_email' => env('APP_CONTACT_EMAIL', 'peso@example.org'),
            'contact_phone' => env('APP_CONTACT_PHONE', ''),
            'service_scope' => env('APP_SERVICE_SCOPE', 'job matching and training services'),
            'welcome_message' => env('APP_WELCOME_MESSAGE', 'Welcome to the PESO employment portal.'),
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($config[$key]) || $config[$key] === '') {
                $config[$key] = $value;
            }
        }

        sendSuccess('Municipality settings', $config);
    }

    public function updateMunicipalityConfig() {
        requireRole(['admin', 'peso']);
        $data = getJsonBody();
        $allowed = ['municipality_name', 'municipality_code', 'municipality_region', 'contact_office', 'contact_email', 'contact_phone', 'service_scope', 'welcome_message'];

        $db = getDB();
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = is_array($data[$key]) ? json_encode($data[$key]) : (string)$data[$key];
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }

        sendSuccess('Municipality settings updated');
    }
}
