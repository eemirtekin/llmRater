<?php
require_once "../config.php";
require_once "lib/db.php";
require_once "lib/gemini.php";
require_once "lib/openai.php";
require_once "lib/parsedown.php";
require_once "functions/ui.php";
require_once "functions/auth.php";

use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \LLMRater\Db;
use \LLMRater\Gemini;
use \LLMRater\OpenAI;
use \LLMRater\Functions\UI;
use \LLMRater\Functions\Auth;

// Initialize $responses as empty array
$responses = [];
$LAUNCH = LTIX::requireData();
Auth::requireInstructor($LAUNCH);

$response_id = filter_input(INPUT_GET, 'response_id', FILTER_VALIDATE_INT);
if (!$response_id) {
    Auth::redirectWithMessage('index.php', 'Invalid response ID', 'error');
}

// Initialize database helper and get response details
$db = new Db($PDOX, $CFG->dbprefix);
$response = $db->getResponseDetails($response_id);
if (!$response) {
    Auth::redirectWithMessage('index.php', 'Response not found', 'error');
}

// Get responses for the current question
$responses = $db->getResponses($response['question_id']);

// Get navigation links
$adjacent = $db->getAdjacentResponses($response_id);

// Handle evaluation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluate'])) {
    try {
        $question = $db->getQuestionById($response['question_id']);
        $llmModel = $question['llm_model'] ?? 'gemini';

        if ($llmModel === 'openai') {
            $apiKey = Settings::linkGet('openai_api_key');
            if (!$apiKey) {
                throw new Exception('OpenAI API key not configured');
            }
            $rater = new OpenAI($apiKey);
        } else {
            $apiKey = Settings::linkGet('gemini_api_key');
            if (!$apiKey) {
                throw new Exception('Gemini API key not configured');
            }
            $rater = new Gemini($apiKey);
        }

        $evaluation = $rater->evaluate(
            $response['question'],
            $response['answer'],
            $response['prompt'],
            $response['additional_prompt'] ?? null
        );

        $db->saveEvaluation($response_id, $evaluation['raw_response']);
        Auth::redirectWithMessage('view.php?response_id=' . $response_id, 'Evaluation completed successfully');
    } catch (Exception $e) {
        Auth::redirectWithMessage('view.php?response_id=' . $response_id, 'Evaluation failed: ' . $e->getMessage(), 'error');
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
UI::renderMenu($LAUNCH, $menu);

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
                    <?= UI::formatMarkdown($response['question'], $parsedown) ?>
                </div>
            </div>

            <h5>Evaluation Criteria:</h5>
            <div class="markdown-content mb-4">
                <?= UI::formatMarkdown($response['prompt'], $parsedown) ?>
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
                        <button type="submit" name="evaluate" value="1" class="btn btn-sm btn-outline-secondary" title="Re-evaluate">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <form method="post">
                    <button type="submit" name="evaluate" value="1" class="btn btn-sm btn-primary" title="Evaluate Response">
                        <i class="fas fa-magic"></i>
                    </button>
                </form>
            <?php endif; ?>

            <?php 
            $question = $db->getQuestionById($response['question_id']);
            $llmModel = $question['llm_model'] ?? 'gemini';
            $modelApiKey = $llmModel === 'openai' ? 
                Settings::linkGet('openai_api_key') : 
                Settings::linkGet('gemini_api_key');
            
            if (!$modelApiKey): 
                $modelName = $llmModel === 'openai' ? 'OpenAI' : 'Gemini';
            ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <?= $modelName ?> API key is not configured. Please configure it in the settings.
                </div>
            <?php endif; ?>
        </div>
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

<?php include 'modals/rubric.view.php'; ?>

<?php
$OUTPUT->footerStart();
$OUTPUT->footerEnd();