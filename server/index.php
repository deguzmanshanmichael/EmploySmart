<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/validator.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; base-uri 'self';");
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize common Hostinger and subfolder deployments before route matching.
// Handles: /api/auth/login, /EmploySmart/api/auth/login, /public/api/auth/login, /server/auth/login
foreach (['api', 'server', 'public'] as $segment) {
    $uri = preg_replace('#^/(?:.*?/)?' . preg_quote($segment, '#') . '(?=/|$)#', '', $uri);
}

$uri    = rtrim($uri, '/');
$parts  = array_values(array_filter(explode('/', $uri), fn($segment) => $segment !== ''));

if (empty($parts)) { sendSuccess('EmploySmart API v1.0'); }

$resource = $parts[0] ?? '';
$id       = $parts[1] ?? null;
$action   = $parts[2] ?? null;

if ($resource === 'auth') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $ctrl = new AuthController();
    match($id) {
        'register'   => $ctrl->register(),
        'login'      => $ctrl->login(),
        'refresh'    => $ctrl->refresh(),
        'logout'     => $ctrl->logout(),
        'me'         => $ctrl->me(),
        default      => sendError('Route not found', 404)
    };
} elseif ($resource === 'users') {
    require_once __DIR__ . '/controllers/UserController.php';
    $ctrl = new UserController();
    if ($id === 'stats') { $ctrl->getDashboardStats(); }
    elseif ($id === 'logs') {
        requireRole(['admin']);
        $db = getDB();
        [$page, $limit, $offset] = getPaginationParams();
        $search = $_GET['search'] ?? '';
        $roleFilter = strtolower(trim((string)($_GET['role'] ?? '')));
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));
        $where = "WHERE 1=1"; $params = []; $types = '';
        if ($search) { $where .= " AND sl.action LIKE ?"; $params[] = "%$search%"; $types .= 's'; }
        if ($roleFilter) { $where .= " AND LOWER(u.role) = ?"; $params[] = $roleFilter; $types .= 's'; }
        if ($dateFrom) { $where .= " AND sl.log_time >= ?"; $params[] = $dateFrom . ' 00:00:00'; $types .= 's'; }
        if ($dateTo) { $where .= " AND sl.log_time <= ?"; $params[] = $dateTo . ' 23:59:59'; $types .= 's'; }
        $cStmt = $db->prepare("SELECT COUNT(*) FROM system_logs sl LEFT JOIN users u ON u.id = sl.user_id $where");
        if ($types) $cStmt->bind_param($types, ...$params);
        $cStmt->execute();
        $total = $cStmt->get_result()->fetch_row()[0];
        $params[] = $limit; $params[] = $offset; $types .= 'ii';
        $stmt = $db->prepare("SELECT sl.id, sl.action, sl.log_time, u.first_name, u.last_name, u.email, u.role FROM system_logs sl LEFT JOIN users u ON u.id = sl.user_id $where ORDER BY sl.log_time DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        sendPaginated($stmt->get_result()->fetch_all(MYSQLI_ASSOC), $total, $page, $limit);
    }
    elseif ($id === null && $method === 'GET') { $ctrl->getAll(); }
    elseif ($id !== null && $action === null) {
        match($method) { 'GET'=>$ctrl->getOne($id),'PUT'=>$ctrl->update($id),'PATCH'=>$ctrl->update($id),'DELETE'=>$ctrl->deleteUser($id),default=>sendError('Method not allowed',405) };
    }
    elseif ($id !== null && $action === 'verify')   { $ctrl->verifyUser($id); }
    elseif ($id !== null && $action === 'password') { $ctrl->updatePassword($id); }
    elseif ($id !== null && $action === 'resume') {
        if ($method === 'GET') { $ctrl->generateResume($id); }
        else { $ctrl->uploadResume($id); }
    }
    elseif ($id !== null && $action === 'avatar')   { $ctrl->uploadProfilePicture($id); }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'jobs') {
    require_once __DIR__ . '/controllers/JobController.php';
    $ctrl = new JobController();
    if ($id === 'pending' && $method === 'GET') { $ctrl->getPendingJobs(); }
    elseif ($id === null && $method === 'GET')  { $ctrl->getAll(); }
    elseif ($id === null && $method === 'POST') { $ctrl->create(); }
    elseif ($id !== null && $action === null) {
        match($method) { 'GET'=>$ctrl->getOne($id),'PUT'=>$ctrl->update($id),'PATCH'=>$ctrl->update($id),'DELETE'=>$ctrl->delete($id),default=>sendError('Method not allowed',405) };
    }
    elseif ($id !== null && $action === 'approve')    { $ctrl->approve($id); }
    elseif ($id !== null && $action === 'applicants') { $ctrl->getJobApplicants($id); }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'employers') {
    require_once __DIR__ . '/controllers/EmployerController.php';
    $ctrl = new EmployerController();
    if ($id === null && $method === 'GET')        { $ctrl->getAll(); }
    elseif ($id === 'user' && $action !== null)   { $ctrl->getByUser($action); }
    elseif ($id !== null && $action === null) {
        match($method) { 'GET'=>$ctrl->getOne($id),'PUT'=>$ctrl->update($id),'PATCH'=>$ctrl->update($id),default=>sendError('Method not allowed',405) };
    }
    elseif ($id !== null && $action === 'verify')    { $ctrl->verify($id); }
    elseif ($id !== null && $action === 'analytics') { $ctrl->getAnalytics($id); }
    elseif ($id !== null && $action === 'document')  { $ctrl->uploadDocument($id); }
    elseif ($id !== null && $action === 'jobs') {
        require_once __DIR__ . '/controllers/JobController.php';
        (new JobController())->getByEmployer($id);
    }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'applications') {
    require_once __DIR__ . '/controllers/ApplicationController.php';
    $ctrl = new ApplicationController();
    if ($id === null && $method === 'POST')         { $ctrl->apply(); }
    elseif ($id === 'my')                           { $ctrl->getMyApplications(); }
    elseif ($id !== null && $action === 'status')   { $ctrl->updateStatus($id); }
    elseif ($id !== null && $action === 'withdraw') { $ctrl->withdraw($id); }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'messages') {
    require_once __DIR__ . '/controllers/MessageController.php';
    $ctrl = new MessageController();
    if ($id !== null && $action === null && $method === 'GET') { $ctrl->getThread($id); }
    elseif ($id !== null && $action === null && $method === 'POST') { $ctrl->sendMessage($id); }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'skills') {
    require_once __DIR__ . '/controllers/SkillController.php';
    $ctrl = new SkillController();
    if ($id === null && $method === 'GET')  { $ctrl->getAll(); }
    elseif ($id === null && $method === 'POST') { $ctrl->create(); }
    elseif ($id !== null && $action === null && $method === 'DELETE') { $ctrl->delete($id); }
    elseif ($id === 'user' && $action !== null) {
        match($method) { 'GET'=>$ctrl->getUserSkills($action),'POST'=>$ctrl->updateUserSkills($action),'PUT'=>$ctrl->updateUserSkills($action),default=>sendError('Method not allowed',405) };
    }
    elseif ($id === 'progression' && $action !== null) {
        $ctrl->getUserSkillProgression($action);
    }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'training') {
    require_once __DIR__ . '/controllers/TrainingController.php';
    $ctrl = new TrainingController();
    if ($id === null && $method === 'GET')  { $ctrl->getAll(); }
    elseif ($id === null && $method === 'POST') { $ctrl->create(); }
    elseif ($id === 'user' && $action !== null) { $ctrl->getUserTrainings($action); }
    elseif ($id !== null && $action === null) {
        match($method) { 'GET'=>$ctrl->getOne($id),'PUT'=>$ctrl->update($id),'PATCH'=>$ctrl->update($id),default=>sendError('Method not allowed',405) };
    }
    elseif ($id !== null && $action === 'enroll') { $ctrl->enroll($id); }
    elseif ($id !== null && $action === 'complete') { $ctrl->completeEnrollment($id, $parts[3] ?? null); }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'notifications') {
    require_once __DIR__ . '/controllers/NotificationController.php';
    $ctrl = new NotificationController();
    if ($id === null && $method === 'GET') {
        $ctrl->getAll();
    } elseif ($id === null && $method === 'POST') {
        $ctrl->create();
    } elseif ($id !== null && $action === 'read' && $method === 'PATCH') {
        $ctrl->markRead($id);
    } elseif ($id === 'read-all' && $method === 'PATCH') {
        $ctrl->markAllRead();
    } else {
        sendError('Route not found', 404);
    }
} elseif ($resource === 'matches') {
    require_once __DIR__ . '/controllers/MatchController.php';
    $ctrl = new MatchController();
    if ($id === 'recommended')              { $ctrl->getRecommendedJobs(); }
    elseif ($id !== null && $action !== null) { $ctrl->getMatchScore($id, $action); }
    elseif ($id !== null)                   { $ctrl->runMatchForUser($id); }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'settings') {
    require_once __DIR__ . '/controllers/SettingsController.php';
    $ctrl = new SettingsController();
    if ($id === 'landing' && $method === 'GET') {
        $ctrl->getPublicLandingConfig();
    } elseif ($id === 'municipality' && ($method === 'GET' || $method === 'POST')) {
        if ($method === 'GET') { $ctrl->getMunicipalityConfig(); }
        else { $ctrl->updateMunicipalityConfig(); }
    } else { sendError('Route not found', 404); }
} elseif ($resource === 'feedback') {
    require_once __DIR__ . '/controllers/FeedbackController.php';
    $ctrl = new FeedbackController();
    if ($id === null && $method === 'POST') { $ctrl->create(); }
    elseif ($id !== null && $action !== null && $method === 'GET') { $ctrl->listForTarget($id, $action); }
    else { sendError('Route not found', 404); }
} elseif ($resource === 'resume-recommendations') {
    require_once __DIR__ . '/controllers/ResumeRecommendationController.php';
    $ctrl = new ResumeRecommendationController();
    if ($id !== null && $method === 'GET') { $ctrl->recommendFromResume($id); }
    else { sendError('Route not found', 404); }
} else {
    sendError('Route not found', 404);
}