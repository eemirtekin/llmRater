<?php
require_once "../config.php";
require_once "lib/DbHelper.php";
require_once "lib/GeminiRater.php";
require_once "lib/OpenAIRater.php";
require_once "lib/Parsedown.php";  // Include Parsedown

use \Tsugi\Util\U;
use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \Tsugi\UI\SettingsForm;
use \LLMRater\DbHelper;
use \LLMRater\GeminiRater;
use \LLMRater\OpenAIRater;

$LAUNCH = LTIX::requireData();
$db = new DbHelper($PDOX, $CFG->dbprefix);
$db->createTables();

if (SettingsForm::handleSettingsPost()) {
    $_SESSION['success'] = 'Settings updated';
    header('Location: ' . addSession('index.php'));
    return;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($LAUNCH->user->instructor) {
        if (isset($_POST['delete_question']) && isset($_POST['question_id'])) {
            $db->deleteQuestion($_POST['question_id']);
            $_SESSION['success'] = 'Question deleted';
            header('Location: ' . addSession('index.php'));
            return;
        } else if (isset($_POST['evaluate_all']) && isset($_GET['question_id'])) {
            $question = $db->getQuestionById($_GET['question_id']);
            $llmModel = $question['llm_model'] ?? 'gemini';

            if ($llmModel === 'openai') {
                $apiKey = Settings::linkGet('openai_api_key');
                if (!$apiKey) {
                    $_SESSION['error'] = 'OpenAI API key not configured';
                    header('Location: ' . addSession('index.php?question_id=' . $_GET['question_id']));
                    return;
                }
                $rater = new OpenAIRater($apiKey);
            } else {
                $apiKey = Settings::linkGet('gemini_api_key');
                if (!$apiKey) {
                    $_SESSION['error'] = 'Gemini API key not configured';
                    header('Location: ' . addSession('index.php?question_id=' . $_GET['question_id']));
                    return;
                }
                $rater = new GeminiRater($apiKey);
            }

            // Get all unevaluated responses
            $responses = $db->getUnevaluatedResponses($_GET['question_id']);
            if (empty($responses)) {
                $_SESSION['success'] = 'No responses to evaluate';
                header('Location: ' . addSession('index.php?question_id=' . $_GET['question_id']));
                return;
            }

            try {
                // Process responses in batches
                $results = $rater->evaluateBatch($responses, 5); // Process 5 at a time
                
                // Save all evaluation results
                $evaluatedCount = 0;
                foreach ($results as $responseId => $evaluation) {
                    $db->saveEvaluation($responseId, $evaluation['raw_response']);
                    $evaluatedCount++;
                }

                $_SESSION['success'] = "Successfully evaluated $evaluatedCount responses";
            } catch (Exception $e) {
                $_SESSION['error'] = 'Evaluation failed: ' . $e->getMessage();
            }
            header('Location: ' . addSession('index.php?question_id=' . $_GET['question_id']));
            return;
        } else if (isset($_POST['title']) && isset($_POST['question']) && isset($_POST['prompt']) && !isset($_POST['question_id'])) {
            $attemptLimit = !empty($_POST['attempt_limit']) ? intval($_POST['attempt_limit']) : null;
            $additionalPrompt = $_POST['additional_prompt'] ?? null;
            $llmModel = $_POST['llm_model'] ?? 'gemini';
            $db->saveQuestion($LAUNCH->link->id, $_POST['title'], $_POST['question'], $_POST['prompt'], $attemptLimit, $additionalPrompt, $llmModel);
            $_SESSION['success'] = 'Question saved';
            header('Location: ' . addSession('index.php'));
            return;
        } else if (isset($_POST['title']) && isset($_POST['question']) && isset($_POST['prompt']) && isset($_POST['question_id'])) {
            $attemptLimit = !empty($_POST['attempt_limit']) ? intval($_POST['attempt_limit']) : null;
            $additionalPrompt = $_POST['additional_prompt'] ?? null;
            $llmModel = $_POST['llm_model'] ?? 'gemini';
            $db->updateQuestion($_POST['question_id'], $_POST['title'], $_POST['question'], $_POST['prompt'], $attemptLimit, $additionalPrompt, $llmModel);
            $_SESSION['success'] = 'Question updated';
            header('Location: ' . addSession('index.php?question_id=' . $_POST['question_id']));
            return;
        }
    } else {
        if (isset($_POST['answer']) && isset($_POST['question_id'])) {
            $question = $db->getQuestionById($_POST['question_id']);
            if ($question && $db->canSubmitResponse($question['question_id'], $LAUNCH->user->id)) {
                $db->saveResponse($question['question_id'], $LAUNCH->user->id, $_POST['answer']);
                $_SESSION['success'] = 'Answer submitted successfully';
                header('Location: ' . addSession('index.php'));
                return;
            } else {
                $_SESSION['error'] = 'You have reached the maximum number of attempts for this question';
                header('Location: ' . addSession('index.php?question_id=' . $_POST['question_id']));
                return;
            }
        }
    }
}

// Get all questions
$questions = $db->getAllQuestions($LAUNCH->link->id);

// Get selected question if question_id is provided
$selected_question = null;
$responses = null;
if (isset($_GET['question_id'])) {
    $selected_question = $db->getQuestionById($_GET['question_id']);
    if ($selected_question) {
        $responses = $db->getResponses($selected_question['question_id']);
        
        // Handle delete_all_evaluations after we have the selected_question
        if ($LAUNCH->user->instructor && isset($_POST['delete_all_evaluations'])) {
            $db->deleteAllEvaluationsForQuestion($selected_question['question_id']);
            $_SESSION['success'] = 'All evaluations for this question have been deleted';
            header('Location: ' . addSession('index.php?question_id=' . $selected_question['question_id']));
            return;
        }
    }
}

// Create Parsedown object
$parsedown = new Parsedown();

$OUTPUT->header();
?>
<link rel="stylesheet" href="css/custom.css?v=<?php echo filemtime(__DIR__ . '/css/custom.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script>
// LTI iframe resize handler
function sendLTIFrameHeight() {
    try {
        // Get the height of the document
        const height = Math.max(
            document.documentElement.clientHeight,
            document.documentElement.scrollHeight,
            document.documentElement.offsetHeight
        );
        
        // Send message to parent window
        if (window.parent && window.parent !== window) {
            const message = {
                subject: 'lti.frameResize',
                height: height
            };
            window.parent.postMessage(JSON.stringify(message), '*');
        }
    } catch (e) {
        console.error('Error sending frame height:', e);
    }
}

// Call on page load and after any dynamic content changes
window.addEventListener('load', sendLTIFrameHeight);
window.addEventListener('resize', sendLTIFrameHeight);

// Call when modals are shown/hidden
document.addEventListener('DOMContentLoaded', function() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', sendLTIFrameHeight);
        modal.addEventListener('hidden.bs.modal', sendLTIFrameHeight);
    });
});

// Periodic check for dynamic content changes
setInterval(sendLTIFrameHeight, 1000);
</script>
<?php
$OUTPUT->bodyStart();

$menu = new \Tsugi\UI\MenuSet();
$menu->addLeft('Home', 'index.php');

if ($LAUNCH->user->instructor) {
    $menu->addRight('Create Question', '#', false, 'data-toggle="modal" data-target="#createQuestionModal"');
    $menu->addRight('Settings', '#', false, SettingsForm::attr());
}

$OUTPUT->topNav($menu);
$OUTPUT->flashMessages();

if ($LAUNCH->user->instructor) {
    ?>
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Questions</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($questions as $q): ?>
                            <a href="?question_id=<?= $q['question_id'] ?>" 
                               class="list-group-item list-group-item-action <?= ($selected_question && $selected_question['question_id'] == $q['question_id']) ? 'active bg-light text-dark' : '' ?>"
                               style="&:hover { background-color: var(--light) !important; color: var(--dark) !important; }">
                                <div class="d-block font-weight-bold">
                                    <?= htmlspecialchars($q['title']) ?>
                                </div>
                                <small class="d-block text-muted"><?= htmlspecialchars($q['created_at']) ?></small>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($questions)): ?>
                            <div class="list-group-item">
                                No questions created yet. Click on "Options" and select "Create Question" to create one.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <?php if ($selected_question): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="mb-0">Question Details</h3>
                        </div>
                        <div class="card-body">
                            <h4><?= htmlspecialchars($selected_question['title']) ?></h4>
                            <div class="markdown-content mb-4">
                                <?php 
                                    $parsedown = new Parsedown();
                                    // Clean HTML tags and process markdown
                                    $cleanQuestion = strip_tags($selected_question['question']);
                                    // Clean unnecessary spaces at the beginning of lines
                                    $cleanQuestion = implode("\n", array_map('trim', explode("\n", $cleanQuestion)));
                                    echo $parsedown->text($cleanQuestion); 
                                ?>
                            </div>
                            
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-4">
                                <button class="btn btn-primary" data-toggle="modal" data-target="#editQuestionModal">
                                    <i class="fas fa-pencil-alt"></i> Edit
                                </button>
                                
                                <?php if (count($responses) > 0): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="evaluate_all" value="1">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-magic"></i> Evaluate
                                        </button>
                                    </form>

                                    <a href="export.php?question_id=<?= $selected_question['question_id'] ?>" class="btn btn-success">
                                        <i class="fas fa-file-csv"></i> Export
                                    </a>
                                <?php endif; ?>

                                <div class="dropdown">
                                    <button class="btn btn-danger dropdown-toggle" type="button" id="deleteMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="deleteMenuButton">
                                        <button type="button" class="dropdown-item text-danger" data-toggle="modal" data-target="#deleteQuestionModal">
                                            Question
                                        </button>
                                        <?php if (count($responses) > 0): ?>
                                            <button type="button" class="dropdown-item text-danger" data-toggle="modal" data-target="#deleteAllEvaluationsModal">
                                                Evaluations
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h4 style="display: inline-block; margin-right: 10px;">Evaluation Criteria:</h4>
                                <button class="btn btn-link p-0" data-toggle="modal" data-target="#evaluationCriteriaModal" style="vertical-align: baseline;">
                                    <i class="fas fa-table"></i>
                                </button>
                            </div>
                        </div>

                        <?php include 'modals/evaluation_criteria_modal.php'; ?>
                        <?php include 'modals/edit_question_modal.php'; ?>
                        <?php include 'modals/delete_question_modal.php'; ?>
                        <?php include 'modals/delete_all_evaluations_modal.php'; ?>

                    <?php if (count($responses) > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="mb-0">Responses</h3>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                           
                                            <th>Submitted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($responses as $response) : ?>
                                            <tr>
                                                <td><?= htmlspecialchars($response['displayname'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($response['submitted_at'] ?? '') ?></td>
                                                <td>
                                                    <a href="view.php?response_id=<?= $response['response_id'] ?>" class="btn btn-sm btn-info">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif (!empty($questions)): ?>
                    <div class="alert alert-info">Select a question from the list to view details.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'modals/create_question_modal.php'; ?>

    <?php
    SettingsForm::start();
    echo("<p>Configure the following settings:</p>\n");
    SettingsForm::text('gemini_api_key', 'Gemini API Key');
    SettingsForm::text('openai_api_key', 'OpenAI API Key');
    SettingsForm::done();
    SettingsForm::end();
} else {
    // Student view
    ?>
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Questions</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($questions as $q): ?>
                            <a href="?question_id=<?= $q['question_id'] ?>" 
                               class="list-group-item list-group-item-action <?= ($selected_question && $selected_question['question_id'] == $q['question_id']) ? 'active bg-light text-dark' : '' ?>"
                               style="&:hover { background-color: var(--light) !important; color: var(--dark) !important; }">
                                <div class="d-block font-weight-bold">
                                    <?= htmlspecialchars($q['title']) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($questions)): ?>
                            <div class="list-group-item">
                                No questions available yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <?php if ($selected_question): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="mb-0">Question Details</h3>
                        </div>
                        <div class="card-body">
                            <h4><?= htmlspecialchars($selected_question['title']) ?>:</h4>
                            <div class="markdown-content">
                                <?php 
                                    // Clean HTML tags and process markdown
                                    $cleanQuestion = strip_tags($selected_question['question']);
                                    // Clean unnecessary spaces at the beginning of lines
                                    $cleanQuestion = implode("\n", array_map('trim', explode("\n", $cleanQuestion)));
                                    echo $parsedown->text($cleanQuestion); 
                                ?>
                            </div>

                            <div class="mt-4">
                                <h4 style="display: inline-block; margin-right: 10px;">Evaluation Criteria:</h4>
                                <button class="btn btn-link p-0" data-toggle="modal" data-target="#evaluationCriteriaModal" style="vertical-align: baseline;">
                                    <i class="fas fa-table"></i>
                                </button>
                            </div>
                            
                            <?php
                            $currentAttempts = $db->getAttemptCount($selected_question['question_id'], $LAUNCH->user->id);
                            $canSubmit = $db->canSubmitResponse($selected_question['question_id'], $LAUNCH->user->id);
                            if ($selected_question['attempt_limit'] !== null) {
                                echo '<div class="alert alert-info">';
                                echo '<strong>Attempt Limit:</strong> ';
                                echo 'You can answer this question ' . $selected_question['attempt_limit'] . ' times. ';
                                echo 'You have used ' . $currentAttempts . ' attempts. ';
                                echo 'Remaining attempts: ' . max(0, $selected_question['attempt_limit'] - $currentAttempts);
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-info">';
                                echo '<strong>Attempt Limit:</strong> You can answer this question unlimited times. ';
                                echo 'Current attempts: ' . $currentAttempts;
                                echo '</div>';
                            }
                            ?>
                            
                            <?php if ($canSubmit): ?>
                                <form method="post">
                                    <input type="hidden" name="question_id" value="<?= $selected_question['question_id'] ?>">
                                    <div class="form-group">
                                        <label for="answer">Your Answer:</label>
                                        <textarea class="form-control" id="answer" name="answer" rows="10" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Submit Answer</button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    You have used up your maximum attempts for this question.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php include 'modals/evaluation_criteria_modal.php'; ?>

                    <?php
                    $userResponses = array_filter($responses ?? [], function($r) use ($LAUNCH) {
                        return $r['user_id'] == $LAUNCH->user->id;
                    });
                    if (!empty($userResponses)): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h3 class="mb-0">Your Previous Attempts</h3>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php foreach ($userResponses as $response): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">Submitted: <?= htmlspecialchars($response['submitted_at']) ?></small>
                                            </div>
                                            <div class="mt-2">
                                                <strong>Your Answer:</strong>
                                                <pre class="mt-2"><?= htmlspecialchars($response['answer']) ?></pre>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif (!empty($questions)): ?>
                    <div class="alert alert-info">Select a question from the list to answer.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php
$OUTPUT->footerStart();
$OUTPUT->footerEnd();