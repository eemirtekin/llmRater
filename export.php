<?php
require_once "../config.php";
require_once "lib/DbHelper.php";

use \Tsugi\Core\LTIX;
use \LLMRater\DbHelper;

// Require instructor role
$LAUNCH = LTIX::requireData();
if (!$LAUNCH->user->instructor) {
    die('Instructor role required');
}

// Initialize database helper
$db = new DbHelper($PDOX, $CFG->dbprefix);

// Get question ID from URL
$question_id = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;
if (!$question_id) {
    $_SESSION['error'] = 'Question ID is required for export';
    header('Location: ' . addSession('index.php'));
    return;
}

// Get question details
$question = $db->getQuestionById($question_id);
if (!$question) {
    $_SESSION['error'] = 'Question not found';
    header('Location: ' . addSession('index.php'));
    return;
}

// Get all responses with evaluations
$responses = $db->getAllResponsesForExport($question_id);
if (empty($responses)) {
    $_SESSION['error'] = 'No data available for export';
    header('Location: ' . addSession('index.php?question_id=' . $question_id));
    return;
}

// Set headers for CSV download
$filename = 'responses_' . $question_id . '_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create output handle
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'Student',
    'Email',
    'Question',
    'Answer',
    'Submitted At',
    'Evaluation',
    'Evaluated At'
]);

// Write data rows
foreach ($responses as $response) {
    fputcsv($output, [
        $response['displayname'] ?? 'Unknown',
        $response['email'] ?? '',
        $response['question'],
        $response['answer'],
        $response['submitted_at'],
        $response['evaluation_text'] ?? '',
        $response['evaluated_at'] ?? ''
    ]);
}

fclose($output);