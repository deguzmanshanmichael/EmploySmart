<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../services/resume/ResumeMatchingService.php';

class ResumeRecommendationController {

    public const LABEL = 'Based on Your Uploaded Resume';
    public const BASIS = 'Recommended based on the skills, qualifications, education, and work experience identified in your uploaded resume.';
    public const DISCLAIMER = 'Match percentages are estimates of how closely your resume matches each posting\'s stated requirements. They are not the probability of being hired, and employers make the final assessment of every requirement.';

    public function recommendFromResume($userId) {
        $payload = requireAuth();
        if ($userId === 'me') $userId = $payload['sub'];
        if (!validateInteger((string)$userId)) sendError('Invalid user', 422);
        $userId = (int)$userId;
        verifyOwnership($userId, $payload, ['admin', 'peso', 'clcdo']);

        $db = getDB();
        $stmt = $db->prepare("SELECT status, confirmed_data, confirmed_at FROM resume_profiles WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $base = ['label' => self::LABEL, 'basis' => self::BASIS, 'disclaimer' => self::DISCLAIMER, 'recommendations' => [], 'matched_jobs' => [], 'total_matches' => 0];
        if (!$row) {
            sendSuccess('No uploaded resume', $base + ['status' => 'no_resume', 'message' => 'Upload your resume to get job recommendations based on it.']);
        }
        if ($row['status'] !== 'confirmed' || empty($row['confirmed_data'])) {
            sendSuccess('Resume not yet confirmed', $base + ['status' => 'needs_review', 'message' => 'Review and confirm the information extracted from your resume to see recommendations based on it.']);
        }

        $resume = json_decode($row['confirmed_data'], true) ?: [];
        $limit = (int)($_GET['limit'] ?? 10);
        $recommendations = (new ResumeMatchingService($db))->recommend($userId, $resume, $limit > 0 ? $limit : 10);

        sendSuccess('Resume-based recommendations', array_merge($base, [
            'status' => 'ok',
            'resume_confirmed_at' => $row['confirmed_at'],
            'recommendations' => $recommendations,
            // Kept for older clients that read matched_jobs.
            'matched_jobs' => $recommendations,
            'total_matches' => count($recommendations),
            'message' => $recommendations ? null : 'No open job postings currently match the information in your resume. New postings are checked every time you visit, so please check back later.',
        ]));
    }
}
