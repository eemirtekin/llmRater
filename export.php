<?php
require_once "../config.php";
require_once "lib/DbHelper.php";
require_once "functions/auth_functions.php";
require_once "functions/export_functions.php";

use \Tsugi\Core\LTIX;
use \LLMRater\DbHelper;
use \LLMRater\Functions\Auth;
use \LLMRater\Functions\Export;

// Set memory limit for large exports
ini_set('memory_limit', '256M');

// Initialize and validate
$LAUNCH = LTIX::requireData();
Auth::requireInstructor($LAUNCH);

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

    // Generate filename and set headers
    $filename = Export::generateExportFilename($question);
    Export::setExportHeaders($filename);

    // Start output buffering for large files
    ob_start();
    $output = fopen('php://output', 'w');
    
    // Export data
    Export::exportToCSV($responses, $question, $output);

    fclose($output);
    ob_end_flush();
    exit();

} catch (Exception $e) {
    Auth::redirectWithMessage(
        'index.php' . ($question_id ? '?question_id=' . $question_id : ''),
        $e->getMessage(),
        'error'
    );
}