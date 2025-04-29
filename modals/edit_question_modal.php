<?php
// Edit Question Modal
?>
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
                        <label for="edit_prompt">Rubric:</label>
                        <textarea class="form-control" id="edit_prompt" name="prompt" rows="5" required><?= htmlspecialchars($selected_question['prompt']) ?></textarea>
                        <small class="form-text text-muted">Must be markdown format to best evaluation.</small>

                    </div>
                    <div class="form-group">
                        <label for="edit_additional_prompt">Instructions:</label>
                        <textarea class="form-control" id="edit_additional_prompt" name="additional_prompt" rows="3"><?= htmlspecialchars($selected_question['additional_prompt'] ?? '') ?></textarea>
                        <small class="form-text text-muted">You can write additional evaluation instructions specific to this question here.</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_attempt_limit">Maximum Attempts (leave empty for unlimited):</label>
                        <input type="number" class="form-control" id="edit_attempt_limit" name="attempt_limit" min="1" value="<?= htmlspecialchars($selected_question['attempt_limit'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit_llm_model">LLM Model:</label>
                        <select class="form-control" id="edit_llm_model" name="llm_model">
                            <option value="gemini" <?= ($selected_question['llm_model'] ?? 'gemini') === 'gemini' ? 'selected' : '' ?>>Gemini</option>
                            <option value="openai" <?= ($selected_question['llm_model'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI (GPT-4)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Question</button>
                </form>
            </div>
        </div>
    </div>
</div>