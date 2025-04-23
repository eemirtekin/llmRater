<?php
require_once "../config.php";
require_once "lib/DbHelper.php";
require_once "lib/Parsedown.php";  // Include Parsedown

use \Tsugi\Util\U;
use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \Tsugi\UI\SettingsForm;
use \LLMRater\DbHelper;

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
        } else if (isset($_POST['title']) && isset($_POST['question']) && isset($_POST['prompt']) && !isset($_POST['question_id'])) {
            $attemptLimit = !empty($_POST['attempt_limit']) ? intval($_POST['attempt_limit']) : null;
            $db->saveQuestion($LAUNCH->link->id, $_POST['title'], $_POST['question'], $_POST['prompt'], $attemptLimit);
            $_SESSION['success'] = 'Question saved';
            header('Location: ' . addSession('index.php'));
            return;
        } else if (isset($_POST['title']) && isset($_POST['question']) && isset($_POST['prompt']) && isset($_POST['question_id'])) {
            $attemptLimit = !empty($_POST['attempt_limit']) ? intval($_POST['attempt_limit']) : null;
            $db->updateQuestion($_POST['question_id'], $_POST['title'], $_POST['question'], $_POST['prompt'], $attemptLimit);
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
    }
}

// Create Parsedown object
$parsedown = new Parsedown();

$OUTPUT->header();
?>
<link rel="stylesheet" href="css/custom.css?v=<?php echo filemtime(__DIR__ . '/css/custom.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">Question Details</h3>
                            <div class="btn-group">
                                <button class="btn btn-primary" data-toggle="modal" data-target="#editQuestionModal">
                                    Edit Question
                                </button>
                                <?php if (count($responses) > 0): ?>
                                    <a href="export.php?question_id=<?= $selected_question['question_id'] ?>" class="btn btn-secondary">
                                        Export Responses
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteQuestionModal">
                                    Delete Question
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4>Question:</h4>
                            <div class="markdown-content">
                                <?php 
                                    $parsedown = new Parsedown();
                                    // Clean HTML tags and process markdown
                                    $cleanQuestion = strip_tags($selected_question['question']);
                                    // Clean unnecessary spaces at the beginning of lines
                                    $cleanQuestion = implode("\n", array_map('trim', explode("\n", $cleanQuestion)));
                                    echo $parsedown->text($cleanQuestion); 
                                ?>
                            </div>
                            <h4>Evaluation Criteria:</h4>
                            <div class="markdown-content">
                                <?php 
                                    // Clean HTML tags
                                    $cleanPrompt = strip_tags($selected_question['prompt']);
                                    // Clean unnecessary spaces at the beginning of lines
                                    $cleanPrompt = implode("\n", array_map('trim', explode("\n", $cleanPrompt)));
                                    echo $parsedown->text($cleanPrompt); 
                                ?>
                            </div>
                        </div>

                    <!-- Edit Question Modal -->
                    <div class="modal fade" id="editQuestionModal" tabindex="-1" role="dialog" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editQuestionModalLabel">Edit Question</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form method="post">
                                        <input type="hidden" name="question_id" value="<?= $selected_question['question_id'] ?>">
                                        <div class="form-group">
                                            <label for="edit_title">Title:</label>
                                            <input type="text" class="form-control" id="edit_title" name="title" value="<?= htmlspecialchars($selected_question['title']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_question">Question:</label>
                                            <textarea class="form-control" id="edit_question" name="question" rows="3" required><?= htmlspecialchars($selected_question['question']) ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_prompt">Evaluation Prompt (Rubric for LLM):</label>
                                            <textarea class="form-control" id="edit_prompt" name="prompt" rows="5" required><?= htmlspecialchars($selected_question['prompt']) ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_attempt_limit">Maximum Attempts (leave empty for unlimited):</label>
                                            <input type="number" class="form-control" id="edit_attempt_limit" name="attempt_limit" min="1" value="<?= htmlspecialchars($selected_question['attempt_limit'] ?? '') ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Question</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

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

    <!-- Create Question Modal -->
    <div class="modal fade" id="createQuestionModal" tabindex="-1" role="dialog" aria-labelledby="createQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createQuestionModalLabel">Create Question</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="question">Question:</label>
                            <textarea class="form-control" id="question" name="question" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="prompt">Evaluation Prompt (Rubric for LLM):</label>
                            <textarea class="form-control" id="prompt" name="prompt" rows="5" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="attempt_limit">Maximum Attempts (leave empty for unlimited):</label>
                            <input type="number" class="form-control" id="attempt_limit" name="attempt_limit" min="1">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Question</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Question Modal -->
    <div class="modal fade" id="deleteQuestionModal" tabindex="-1" role="dialog" aria-labelledby="deleteQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteQuestionModalLabel">Delete Question</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this question? This action cannot be undone.</p>
                    <p class="text-danger">Warning: All student responses and evaluations for this question will also be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="question_id" value="<?= $selected_question['question_id'] ?>">
                        <input type="hidden" name="delete_question" value="1">
                        <button type="submit" class="btn btn-danger">Delete Question</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
    SettingsForm::start();
    echo("<p>Configure the following settings:</p>\n");
    SettingsForm::text('gemini_api_key', 'Gemini API Key');
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

                    <!-- Evaluation Criteria Modal -->
                    <div class="modal fade" id="evaluationCriteriaModal" tabindex="-1" role="dialog" aria-labelledby="evaluationCriteriaModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="evaluationCriteriaModalLabel">Evaluation Criteria</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="markdown-content">
                                        <?php 
                                            $cleanPrompt = strip_tags($selected_question['prompt']);
                                            $cleanPrompt = implode("\n", array_map('trim', explode("\n", $cleanPrompt)));
                                            echo $parsedown->text($cleanPrompt); 
                                        ?>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

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