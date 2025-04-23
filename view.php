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

// Handle evaluation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluate'])) {
    try {
        // Get API key from settings
        $apiKey = Settings::linkGet('gemini_api_key');
        if (!$apiKey) {
            throw new Exception('Gemini API key not configured');
        }

        $rater = new GeminiRater($apiKey);
        $evaluation = $rater->evaluate(
            $response['question'],
            $response['answer'],
            $response['prompt']
        );

        // Store evaluation result
        $db->saveEvaluation($response_id, $evaluation['raw_response']);

        $_SESSION['success'] = 'Evaluation completed successfully';
        header('Location: ' . addSession('view.php?response_id=' . $response_id));
        return;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Evaluation failed: ' . $e->getMessage();
    }
}

// Start the page
$OUTPUT->header();
?>
<link rel="stylesheet" href="css/custom.css?v=<?php echo filemtime(__DIR__ . '/css/custom.css'); ?>">
<?php
$OUTPUT->bodyStart();

$menu = new \Tsugi\UI\MenuSet();
$menu->addLeft('Back', 'index.php');
$OUTPUT->topNav($menu);
$OUTPUT->flashMessages();
?>

<div class="container">
    <h2>Student Response</h2>
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
                <div class="mb-4">
                    <?= nl2br(htmlspecialchars($response['evaluation_text'])) ?>
                    <br>
                    <small class="text-muted">Evaluated on: <?= htmlspecialchars($response['evaluated_at'] ?? '') ?></small>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <form method="post">
                    <button type="submit" name="evaluate" class="btn btn-primary">
                        <?= isset($response['evaluation_text']) ? 'Re-evaluate' : 'Evaluate' ?> Response
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$OUTPUT->footerStart();
$OUTPUT->footerEnd();