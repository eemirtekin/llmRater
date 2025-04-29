<?php
// Rubric View Modal
?>
<div class="modal fade" id="rubricViewModal" tabindex="-1" role="dialog" aria-labelledby="rubricViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rubricViewModalLabel">Evaluation Criteria</h5>
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