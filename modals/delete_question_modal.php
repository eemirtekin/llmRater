<?php
// Delete Question Modal
?>
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