<?php
// Create Question Modal
?>
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
                        <label for="prompt">Rubric:</label>
                        <textarea class="form-control" id="prompt" name="prompt" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="additional_prompt">Additional Evaluation Instructions:</label>
                        <textarea class="form-control" id="additional_prompt" name="additional_prompt" rows="3"></textarea>
                        <small class="form-text text-muted">You can write additional evaluation instructions specific to this question here.</small>
                    </div>
                    <div class="form-group">
                        <label for="attempt_limit">Maximum Attempts (leave empty for unlimited):</label>
                        <input type="number" class="form-control" id="attempt_limit" name="attempt_limit" min="1">
                    </div>
                    <div class="form-group">
                        <label for="llm_model">LLM Model:</label>
                        <select class="form-control" id="llm_model" name="llm_model">
                            <option value="gemini">Gemini</option>
                            <option value="openai">OpenAI (GPT-4)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Question</button>
                </form>
            </div>
        </div>
    </div>
</div>