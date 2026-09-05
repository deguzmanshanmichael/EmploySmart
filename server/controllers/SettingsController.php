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
            'landing_hero_image' => env('APP_LANDING_HERO_IMAGE', ''),
            'landing_logo_image' => env('APP_LANDING_LOGO_IMAGE', ''),
            'landing_primary_color' => env('APP_LANDING_PRIMARY_COLOR', '#047857'),
            'landing_accent_color' => env('APP_LANDING_ACCENT_COLOR', '#f59e0b'),
            'landing_footer_text' => env('APP_LANDING_FOOTER_TEXT', 'Connecting people, skills, and opportunity.'),
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
            'landing_vision', 'landing_peso', 'landing_clcdo', 'landing_hero_image',
            'landing_logo_image', 'landing_primary_color', 'landing_accent_color', 'landing_footer_text'
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

    public function resetMunicipalityConfig() {
        requireRole(['admin']);
        $db = getDB();
        $keys = array_keys($this->landingDefaults());
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = $db->prepare("DELETE FROM system_settings WHERE setting_key IN ($placeholders)");
        $stmt->bind_param($types, ...$keys);
        $stmt->execute();
        sendSuccess('Municipality and landing settings restored to defaults', $this->landingDefaults());
    }

    public function uploadLandingImage($imageType) {
        requireRole(['admin']);
        if (!in_array($imageType, ['hero', 'logo'], true)) {
            sendError('Invalid landing image type', 422);
        }
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            sendError('Please choose an image to upload', 400);
        }

        $file = $_FILES['image'];
        if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
            sendError('Image must be smaller than 5MB', 422);
        }
        $imageInfo = @getimagesize($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!$imageInfo || !in_array($imageInfo['mime'], $allowedMimes, true)) {
            sendError('Only JPG, PNG, and WebP images are allowed', 422);
        }

        $dir = __DIR__ . '/../uploads/landing/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            sendError('Unable to prepare image upload directory', 500);
        }
        if (!is_writable($dir)) sendError('Image upload directory is not writable', 500);

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'landing_' . $imageType . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            sendError('Image upload failed', 500);
        }

        $path = 'api/uploads/landing/' . $filename;
        $settingKey = 'landing_' . $imageType . '_image';
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param('ss', $settingKey, $path);
        $stmt->execute();
        sendSuccess('Landing image uploaded', [$settingKey => $path]);
    }
}
