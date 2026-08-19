<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class SettingsController {

    private function landingDefaults() {
        return [
            'municipality_name' => env('APP_MUNICIPALITY_NAME', 'EmploySmart Municipality'),
            'municipality_code' => env('APP_MUNICIPALITY_CODE', 'general'),
            'municipality_region' => env('APP_MUNICIPALITY_REGION', 'Philippines'),
            'contact_office' => env('APP_CONTACT_OFFICE', 'PESO'),
            'contact_email' => env('APP_CONTACT_EMAIL', 'peso@example.org'),
            'contact_phone' => env('APP_CONTACT_PHONE', ''),
            'service_scope' => env('APP_SERVICE_SCOPE', 'job matching and training services'),
            'welcome_message' => env('APP_WELCOME_MESSAGE', 'Welcome to the PESO employment portal.'),
            'landing_hero_title' => env('APP_LANDING_HERO_TITLE', 'Find work that moves your future forward.'),
            'landing_hero_subtitle' => env('APP_LANDING_HERO_SUBTITLE', 'EmploySmart connects jobseekers, employers, PESO, and CLCDO through one trusted local employment platform.'),
            'landing_about' => env('APP_LANDING_ABOUT', 'EmploySmart makes local employment services easier to discover, manage, and access. Search opportunities, build skills, connect with employers, and follow your progress in one place.'),
            'landing_mission' => env('APP_LANDING_MISSION', 'To connect people to decent work and practical skills through accessible, transparent, and community-centered employment services.'),
            'landing_vision' => env('APP_LANDING_VISION', 'A thriving local workforce where every capable person can discover opportunity and every responsible employer can find the talent they need.'),
            'landing_peso' => env('APP_LANDING_PESO', 'PESO supports job matching, employer coordination, job approval, applicant monitoring, and employment reports for the community.'),
            'landing_clcdo' => env('APP_LANDING_CLCDO', 'CLCDO coordinates training programs, participant enrollment, skills development, and completion tracking for local residents.'),
        ];
    }

    public function getMunicipalityConfig() {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $config = [];
        foreach ($rows as $row) {
            $config[$row['setting_key']] = $row['setting_value'];
        }

        $defaults = $this->landingDefaults();

        foreach ($defaults as $key => $value) {
            if (!isset($config[$key]) || $config[$key] === '') {
                $config[$key] = $value;
            }
        }

        sendSuccess('Municipality settings', $config);
    }

    public function getPublicLandingConfig() {
        $config = $this->landingDefaults();
        // The public home page must remain available while hosting/database setup is incomplete.
        // Read admin overrides when possible, but keep environment defaults as a valid fallback.
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$db->connect_error) {
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
            if ($stmt) {
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach ($rows as $row) {
                    if (array_key_exists($row['setting_key'], $config) && $row['setting_value'] !== '') {
                        $config[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
            $db->close();
        }
        sendSuccess('Landing page configuration', $config);
    }

    public function updateMunicipalityConfig() {
        requireRole(['admin', 'peso']);
        $data = getJsonBody();
        $allowed = [
            'municipality_name', 'municipality_code', 'municipality_region', 'contact_office',
            'contact_email', 'contact_phone', 'service_scope', 'welcome_message',
            'landing_hero_title', 'landing_hero_subtitle', 'landing_about', 'landing_mission',
            'landing_vision', 'landing_peso', 'landing_clcdo'
        ];

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
