<?php
require_once "../config.php";
require_once "lib/DbHelper.php";
require_once "lib/GeminiRater.php";
require_once "lib/Parsedown.php";  // Include Parsedown

use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \LLMRater\DbHelper;
use \LLMRater\GeminiRater;

$LAUNCH = LTIX::requireData();
if (!$LAUNCH->user->instructor) {
    die('Instructor role required');
}

$response_id = isset($_GET['response_id']) ? intval($_GET['response_id']) : 0;
if (!$response_id) {
    $_SESSION['error'] = 'Invalid response ID';
    header('Location: ' . addSession('index.php'));
    return;
}

// Initialize database helper
$db = new DbHelper($PDOX, $CFG->dbprefix);

// Get response details including question, student info, and evaluation
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
            // Get API key from settings
            $apiKey = Settings::linkGet('gemini_api_key');
            if (!$apiKey) {
                $_SESSION['error'] = 'Gemini API key not configured';
                header('Location: ' . addSession('view.php?response_id=' . $response_id));
                return;
            }

            $rater = new GeminiRater($apiKey);
            $evaluation = $rater->evaluate(
                $response['question'],
                $response['answer'],
                $response['prompt'],
                $response['additional_prompt'] ?? null
            );

            // Store evaluation result
            $db->saveEvaluation($response_id, $evaluation['raw_response']);

            $_SESSION['success'] = 'Evaluation completed successfully';
            header('Location: ' . addSession('view.php?response_id=' . $response_id));
            return;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Evaluation failed: ' . $e->getMessage();
            header('Location: ' . addSession('view.php?response_id=' . $response_id));
            return;
        }
    } elseif (isset($_POST['delete_all_evaluations'])) {
        // Ensure we have the question_id from the response
        if (!isset($response['question_id'])) {
            $_SESSION['error'] = 'Question ID not found';
            header('Location: ' . addSession('view.php?response_id=' . $response_id));
            return;
        }
        // Delete all evaluations for this question
        $db->deleteAllEvaluationsForQuestion($response['question_id']);
        $_SESSION['success'] = 'All evaluations for this question have been deleted';
        header('Location: ' . addSession('view.php?response_id=' . $response_id));
        return;
    }
}

// Start the page
$OUTPUT->header();
?>
<link rel="stylesheet" href="css/custom.css?v=<?php echo filemtime(__DIR__ . '/css/custom.css'); ?>">
<style>
.navigation-buttons {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.navigation-buttons .btn {
    min-width: 100px;
}
</style>
<?php
$OUTPUT->bodyStart();

$menu = new \Tsugi\UI\MenuSet();
$menu->addLeft('Back to List', 'index.php');
$OUTPUT->topNav($menu);
$OUTPUT->flashMessages();
?>

<div class="container">
    <h2 class="mb-3">Student Response</h2>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Student:</strong> <?= htmlspecialchars($response['displayname'] ?? '') ?>
            <br>
            <strong>Question:</strong> <?= htmlspecialchars($response['title'] ?? '') ?>
            <br>
            <strong>Submitted:</strong> <?= htmlspecialchars($response['submitted_at'] ?? '') ?>
        </div>
        <div class="card-body">
            <h5>Question:</h5>
            <div class="markdown-content mb-4">
                <?php 
                    $parsedown = new Parsedown();
                    // Clean HTML tags and process markdown
                    $cleanQuestion = strip_tags($response['question']);
                    // Remove unnecessary spaces at the beginning of lines
                    $cleanQuestion = implode("\n", array_map('trim', explode("\n", $cleanQuestion)));
                    echo $parsedown->text($cleanQuestion); 
                ?>
            </div>

            <h5>Evaluation Criteria:</h5>
            <div class="markdown-content mb-4">
                <?php 
                    $parsedown = new Parsedown();
                    // Remove HTML tags and process markdown
                    $cleanPrompt = strip_tags($response['prompt']);
                    echo $parsedown->text($cleanPrompt);
                ?>
            </div>

            <h5>Answer:</h5>
            <div class="mb-4">
                <?= htmlspecialchars($response['answer']) ?>
            </div>

            <?php if (isset($response['evaluation_text'])): ?>
                <h5>LLM Evaluation:</h5>
                <div class="evaluation-box">
                    <pre><?= htmlspecialchars($response['evaluation_text']) ?></pre>
                    <small class="text-muted mt-2 d-block">Evaluated on: <?= htmlspecialchars($response['evaluated_at'] ?? '') ?></small>
                </div>
            <?php endif; ?>

            <?php 
            $apiKey = Settings::linkGet('gemini_api_key');
            if (!$apiKey): ?>
                <div class="alert alert-warning">
                    Gemini API key is not configured. Please configure it in the settings before evaluating responses.
                </div>
            <?php endif; ?>
            <div class="mt-4">
                <form method="post">
                    <button type="submit" name="evaluate" value="1" class="btn btn-primary">
                        <?= isset($response['evaluation_text']) ? 'Re-evaluate' : 'Evaluate' ?> Response
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="navigation-buttons">
        <?php if ($adjacent && $adjacent['prev_id']): ?>
            <a href="?response_id=<?= $adjacent['prev_id'] ?>" class="btn btn-primary">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
        <?php else: ?>
            <div></div>
        <?php endif; ?>

        <?php if ($adjacent && $adjacent['next_id']): ?>
            <a href="?response_id=<?= $adjacent['next_id'] ?>" class="btn btn-primary">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <div></div>
        <?php endif; ?>
    </div>
</div>

<?php
$OUTPUT->footerStart();
$OUTPUT->footerEnd();