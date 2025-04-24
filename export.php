<?php
require_once "../config.php";
require_once "lib/DbHelper.php";

use \Tsugi\Core\LTIX;
use \LLMRater\DbHelper;

// Set memory limit for large exports
ini_set('memory_limit', '256M');

// Require instructor role
$LAUNCH = LTIX::requireData();
if (!$LAUNCH->user->instructor) {
    die('Instructor role required');
}

// Initialize database helper
$db = new DbHelper($PDOX, $CFG->dbprefix);

try {
    // Get and validate question ID
    $question_id = filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT);
    if (!$question_id) {
        throw new Exception('Question ID is required for export');
    }

    // Get question details
    $question = $db->getQuestionById($question_id);
    if (!$question) {
        throw new Exception('Question not found');
    }

    // Get all responses with evaluations
    $responses = $db->getAllResponsesForExport($question_id);
    if (empty($responses)) {
        throw new Exception('No data available for export');
    }

    // Generate filename with sanitized question title
    $safeTitle = preg_replace('/[^a-z0-9]+/i', '_', $question['title']);
    $filename = sprintf(
        'responses_%s_%s_%s.csv',
        $safeTitle,
        $question_id,
        date('Y-m-d_His')
    );

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Start output buffering for large files
    ob_start();

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

    // Write data rows with chunking for memory efficiency
    $chunkSize = 100;
    $processed = 0;
    $total = count($responses);

    while ($processed < $total) {
        $chunk = array_slice($responses, $processed, $chunkSize);
        
        foreach ($chunk as $response) {
            fputcsv($output, [
                $response['displayname'] ?? 'Unknown',
                $response['email'] ?? '',
                strip_tags($response['question']), // Remove HTML tags from question
                $response['answer'],
                $response['submitted_at'],
                $response['evaluation_text'] ?? '',
                $response['evaluated_at'] ?? ''
            ]);
        }

        $processed += count($chunk);
        ob_flush(); // Flush output buffer
        flush(); // Flush system buffer
    }

    fclose($output);
    ob_end_flush();
    exit();

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . addSession('index.php' . 
        ($question_id ? '?question_id=' . $question_id : '')));
    exit();
}