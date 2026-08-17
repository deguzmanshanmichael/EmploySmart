<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../services/JobMatchingService.php';

class MatchController {

    public function getRecommendedJobs() {
        $payload = requireRole(['jobseeker']);
        $db = getDB();
        $service = new JobMatchingService($db);
        $matches = $service->getTopMatchesForUser($payload['sub']);
        sendSuccess('Recommended jobs', $matches);
    }

    public function runMatchForUser($userId) {
        requireRole(['admin','peso']);
        $db = getDB();
        $service = new JobMatchingService($db);
        $service->computeAndStoreMatches($userId);
        sendSuccess('Matches computed');
    }

    public function getMatchScore($userId, $jobId) {
        requireAuth();
        $db = getDB();
        $service = new JobMatchingService($db);
        $score = $service->computeScore($userId, $jobId);
        sendSuccess('Match score', ['score' => $score]);
    }
}