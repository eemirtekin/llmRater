<?php
// Delete All Evaluations Modal
?>
<div class="modal fade" id="deleteAllEvaluationsModal" tabindex="-1" role="dialog" aria-labelledby="deleteAllEvaluationsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAllEvaluationsModalLabel">Delete All Evaluations</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete all evaluations for this question? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="delete_all_evaluations" value="1">
                    <button type="submit" class="btn btn-warning">Delete All Evaluations</button>
                </form>
            </div>
        </div>
    </div>
</div>