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

// Get current question
$question = $db->getQuestion($LAUNCH->link->id);
if (!$question) {
    $_SESSION['error'] = 'No question found';
    header('Location: ' . addSession('index.php'));
    return;
}

// Get all responses with evaluations
$responses = $db->getAllResponsesForExport($question['question_id']);
if (empty($responses)) {
    $_SESSION['error'] = 'No data available for export';
    header('Location: ' . addSession('index.php'));
    return;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="responses_' . date('Y-m-d') . '.csv"');

// Create CSV file
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'Student ID',
    'Student Name',
    'Student Email',
    'Submission Date',
    'Question',
    'Evaluation Criteria',
    'Answer',
    'LLM Evaluation',
    'Evaluation Date'
]);

// Write data
foreach ($responses as $response) {
    $row = [
        $response['user_id'],
        $response['displayname'],
        $response['email'],
        $response['submitted_at'],
        $response['question'],
        $response['prompt'],
        $response['answer'],
        $response['evaluation_text'] ?? 'Not evaluated',
        $response['evaluated_at'] ?? 'N/A'
    ];
    
    fputcsv($output, $row);
}

fclose($output);