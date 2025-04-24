<?php
require_once "../config.php";
require_once "lib/DbHelper.php";
require_once "lib/GeminiRater.php";
require_once "lib/Parsedown.php";

use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \LLMRater\DbHelper;
use \LLMRater\GeminiRater;

$LAUNCH = LTIX::requireData();
if (!$LAUNCH->user->instructor) {
    die('Instructor role required');
}

$response_id = filter_input(INPUT_GET, 'response_id', FILTER_VALIDATE_INT);
if (!$response_id) {
    $_SESSION['error'] = 'Invalid response ID';
    header('Location: ' . addSession('index.php'));
    return;
}

// Initialize database helper and get response details
$db = new DbHelper($PDOX, $CFG->dbprefix);
$response = $db->getResponseDetails($response_id);
if (!$response) {
    $_SESSION['error'] = 'Response not found';
    header('Location: ' . addSession('index.php'));
    return;
}

// Get navigation links
$adjacent = $db->getAdjacentResponses($response_id);

// Handle evaluation request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['evaluate'])) {
        try {
            $apiKey = Settings::linkGet('gemini_api_key');
            if (!$apiKey) {
                throw new Exception('Gemini API key not configured');
            }

            $rater = new GeminiRater($apiKey);
            $evaluation = $rater->evaluate(
                $response['question'],
                $response['answer'],
                $response['prompt'],
                $response['additional_prompt'] ?? null
            );

            $db->saveEvaluation($response_id, $evaluation['raw_response']);
            $_SESSION['success'] = 'Evaluation completed successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Evaluation failed: ' . $e->getMessage();
        }
        header('Location: ' . addSession('view.php?response_id=' . $response_id));
        return;
    }
}

// Initialize Markdown parser
$parsedown = new Parsedown();

// Start output
$OUTPUT->header();
?>
<link rel="stylesheet" href="css/custom.css?v=<?= filemtime(__DIR__ . '/css/custom.css') ?>">
<?php
$OUTPUT->bodyStart();

$menu = new \Tsugi\UI\MenuSet();
$menu->addLeft('Back to List', 'index.php');
$OUTPUT->topNav($menu);
$OUTPUT->flashMessages();
?>

<div class="container">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h4 class="mb-2"><?= htmlspecialchars($response['title'] ?? '') ?></h4>
            </div>
        </div>
        <div class="card-body">
            <div class="question-box">
                <div class="question-metadata">
                    <strong>Student:</strong> <?= htmlspecialchars($response['displayname'] ?? '') ?><br>
                    <strong>Submitted:</strong> <?= htmlspecialchars($response['submitted_at'] ?? '') ?>
                </div>
                <h5>Question:</h5>
                <div class="markdown-content">
                    <?= $parsedown->text(trim(strip_tags($response['question']))) ?>
                </div>
            </div>

            <h5>Evaluation Criteria:</h5>
            <div class="markdown-content mb-4">
                <?= $parsedown->text(trim(strip_tags($response['prompt']))) ?>
            </div>

            <h5>Answer:</h5>
            <div class="mb-4">
                <pre class="p-3 bg-light border rounded"><?= htmlspecialchars($response['answer']) ?></pre>
            </div>

            <?php if (isset($response['evaluation_text'])): ?>
                <h5>LLM Evaluation:</h5>
                <div class="evaluation-box">
                    <pre><?= htmlspecialchars($response['evaluation_text']) ?></pre>
                    <?php if (isset($response['evaluated_at'])): ?>
                        <small class="text-muted mt-2 d-block">Evaluated on: <?= htmlspecialchars($response['evaluated_at']) ?></small>
                    <?php endif; ?>
                    <form method="post" class="mt-2">
                        <button type="submit" name="evaluate" value="1" class="btn btn-sm btn-outline-secondary">
                            Re-evaluate
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <form method="post">
                    <button type="submit" name="evaluate" value="1" class="btn btn-sm btn-primary">
                        Evaluate Response
                    </button>
                </form>
            <?php endif; ?>

            <?php 
            $apiKey = Settings::linkGet('gemini_api_key');
            if (!$apiKey): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Gemini API key is not configured. Please configure it in the settings.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="btn-group mb-4">
        <button class="btn btn-primary" data-toggle="modal" data-target="#editQuestionModal">
            <i class="fas fa-edit"></i> Edit Question
        </button>
        <?php if (count($responses) > 0): ?>
            <a href="export.php?question_id=<?= $selected_question['question_id'] ?>" class="btn btn-secondary">
                <i class="fas fa-file-export"></i> Export
            </a>
            <form method="post" style="display: inline;">
                <input type="hidden" name="evaluate_all" value="1">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Evaluate All
                </button>
            </form>
            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#deleteAllEvaluationsModal">
                <i class="fas fa-trash-alt"></i> Clear Evaluations
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteQuestionModal">
            <i class="fas fa-times-circle"></i> Delete
        </button>
    </div>

    <div class="navigation-buttons">
        <?php if ($adjacent && $adjacent['prev_id']): ?>
            <a href="?response_id=<?= $adjacent['prev_id'] ?>" class="btn btn-primary">
                <i class="fas fa-chevron-left"></i> Previous Response
            </a>
        <?php else: ?>
            <div></div>
        <?php endif; ?>

        <?php if ($adjacent && $adjacent['next_id']): ?>
            <a href="?response_id=<?= $adjacent['next_id'] ?>" class="btn btn-primary">
                Next Response <i class="fas fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <div></div>
        <?php endif; ?>
    </div>
</div>

<?php
$OUTPUT->footerStart();
$OUTPUT->footerEnd();