<div class="padding-md">
    <form data-container="#diff-response" action="index.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="diff"/>
        <div class="row">
            <div class="col-md-6">
                <div>Base text:</div>
                <textarea name="DIFF_FROM" class="diff-text"></textarea>
            </div>
            <div class="col-md-6">
                <div>New text:</div>
                <textarea name="DIFF_TO" class="diff-text"></textarea>
            </div>
        </div>
        <input type="submit" value="Diff"/>
    </form>
    <div class="row">
        <div class="col-md-12">
            <div id="diff-response"></div>
        </div>
    </div>
</div>
