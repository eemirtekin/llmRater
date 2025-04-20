<?php
require_once "../config.php";
require_once "lib/DbHelper.php";

use \Tsugi\Util\U;
use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \Tsugi\UI\SettingsForm;
use \LLMRater\DbHelper;

// No parameter means we require CONTEXT, USER, and LINK
$LAUNCH = LTIX::requireData();

// Initialize database helper
$db = new DbHelper($PDOX, $CFG->dbprefix);
$db->createTables();

// Handle settings post if needed
if (SettingsForm::handleSettingsPost()) {
    $_SESSION['success'] = 'Settings updated';
    header('Location: ' . addSession('index.php'));
    return;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($LAUNCH->user->instructor) {
        // Instructor saving question
        if (isset($_POST['question']) && isset($_POST['prompt'])) {
            $db->saveQuestion($LAUNCH->link->id, $_POST['question'], $_POST['prompt']);
            $_SESSION['success'] = 'Question saved';
            header('Location: ' . addSession('index.php'));
            return;
        }
    } else {
        // Student submitting answer
        if (isset($_POST['answer'])) {
            $question = $db->getQuestion($LAUNCH->link->id);
            if ($question) {
                $db->saveResponse($question['question_id'], $LAUNCH->user->id, $_POST['answer']);
                $_SESSION['success'] = 'Answer submitted';
                header('Location: ' . addSession('index.php'));
                return;
            }
        }
    }
}

// Get current question data
$question = $db->getQuestion($LAUNCH->link->id);

// Start the page
$OUTPUT->header();
$OUTPUT->bodyStart();

// Add navigation menu
$menu = new \Tsugi\UI\MenuSet();
$menu->addLeft('Home', 'index.php');

if ($LAUNCH->user->instructor) {
    $menu->addRight('Settings', '#', false, SettingsForm::attr());
    $menu->addRight('Export', 'export.php');
}

$OUTPUT->topNav($menu);
$OUTPUT->flashMessages();

// Settings form
if ($LAUNCH->user->instructor) {
    SettingsForm::start();
    echo("<p>Configure the following settings:</p>\n");
    SettingsForm::text('gemini_api_key', 'Gemini API Key');
    SettingsForm::done();
    SettingsForm::end();
}

// Display the appropriate view based on role
if ($LAUNCH->user->instructor) {
    // Instructor view
    ?>
    <div class="container">
        <h2>Create Question</h2>
        <form method="post">
            <div class="form-group">
                <label for="question">Question:</label>
                <textarea class="form-control" id="question" name="question" rows="3" required><?= htmlspecialchars($question['question'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="prompt">Evaluation Prompt (Rubric for LLM):</label>
                <textarea class="form-control" id="prompt" name="prompt" rows="5" required><?= htmlspecialchars($question['prompt'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Question</button>
        </form>
        
        <?php if ($question): 
            $responses = $db->getResponses($question['question_id']);
            if (count($responses) > 0): ?>
                <h3 class="mt-4">Responses</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Answer</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $response) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($response['displayname']) ?></td>
                                    <td><?= htmlspecialchars(substr($response['answer'], 0, 100)) ?>...</td>
                                    <td><?= htmlspecialchars($response['submitted_at']) ?></td>
                                    <td>
                                        <a href="view.php?response_id=<?= $response['response_id'] ?>" class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif;
        endif; ?>
    </div>
    <?php
} else {
    // Student view
    if (!$question) {
        echo '<div class="alert alert-info">No question has been created yet.</div>';
    } else {
        ?>
        <div class="container">
            <h2>Question</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <?= htmlspecialchars($question['question']) ?>
                </div>
            </div>
            
            <form method="post">
                <div class="form-group">
                    <label for="answer">Your Answer:</label>
                    <textarea class="form-control" id="answer" name="answer" rows="10" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Answer</button>
            </form>
        </div>
        <?php
    }
}

// Add CKEditor
?>
<script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editorElements = ['question', 'prompt', 'answer'];
    editorElements.forEach(elementId => {
        const element = document.getElementById(elementId);
        if (element) {
            ClassicEditor
                .create(element)
                .catch(error => {
                    console.error(error);
                });
        }
    });
});
</script>
<?php
$OUTPUT->footerStart();
$OUTPUT->footerEnd();