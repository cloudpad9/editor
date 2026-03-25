<?php if (Builder::hasPermission('snr')) : ?>
<style>
#tabs-snr #snr_search_wrapper {
    position: relative;
    display:inline-block;
}
#tabs-snr #snr_batch_snr {
    display: none;
    position: absolute;
    top: -19px;
    width: 100%;
    height: 26px;
    background: #f9f5bc;
    overflow: hidden;
    resize: none;
}
#tabs-snr #snr_batch_snr:focus {
    height: 200px;
    overflow: auto;
    resize: auto;
}
#tabs-snr-inner.batch-mode #snr_search_wrapper {
    width: 468px;
}
#tabs-snr-inner.batch-mode #snr_search,
#tabs-snr-inner.batch-mode #snr_replace {
    display: none;
}
#tabs-snr-inner.batch-mode #snr_batch_snr {
    display: block;
}
</style>
<div id="tabs-snr-inner"<?php echo isset($_SESSION['snr-batch-mode']) && $_SESSION['snr-batch-mode'] ? ' class="batch-mode"' : ''; ?>>
    <div class="editor-file-bar commandbar">
        <form data-success="onSearchDone" data-container="#snr-response" data-verbose="1" action="index.php" method="POST" enctype="multipart/form-data" style="float:left;">
            <input type="hidden" name="action" value="snr-search"/>
            <input type="hidden" name="force-replace" id="force-replace" value="0"/>
            <input type="hidden" name="force-delete" id="force-delete" value="0"/>

            <div class="moz-select-wrapper">
                <select name="repository" class="repositories">
                    <?php $repositories = $builder->getRepositories(); ?>

                    <?php foreach ($repositories as $code => $settings) : ?>
                        <option value="<?php echo $code; ?>" <?php echo isset($_SESSION['snr-repository']) && $_SESSION['snr-repository'] == $code? 'selected' : ''; ?>><?php echo $settings['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <span id="snr_search_wrapper">
                <input id="snr_search" type="text" class="filename" name="search" value="<?php echo isset($_SESSION['search'])? htmlspecialchars($_SESSION['search'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Enter a search string"/>
                <textarea id="snr_batch_snr" name="batch-snr" placeholder="or multiple search => replace lines here..."><?php echo isset($_SESSION['batch-snr'])? htmlspecialchars($_SESSION['batch-snr']) : ''; ?></textarea>
            </span>
            <span title="toggle batch mode" style="color: #fff;background: gray;position: relative;cursor: pointer;" onclick="$('#tabs-snr-inner').toggleClass('batch-mode')">...</span>
            <input type="checkbox" name="case-sensitive" title="Case sensitive"/>
            <input type="text" name="max-files-returned" value="<?php echo isset($_SESSION['max-files-returned'])? $_SESSION['max-files-returned'] : '10'; ?>" size="2"/>
            <input type="text" class="filename" name="file-mask" value="<?php echo isset($_SESSION['file-mask'])? $_SESSION['file-mask'] : '*.*'; ?>" placeholder="*.php;*.tpl;-foo;-bar"/>
            <input type="checkbox" name="search-by-filename" title="Search by filename"/>
            <input type="submit" value="Search" onclick="$('#force-replace').val(0)"/>
            <input id="snr_replace" type="text" class="filename" name="replace" value="<?php echo isset($_SESSION['replace'])? htmlspecialchars($_SESSION['replace'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Enter a replacement string"/>
            <input type="submit" value="Replace" onclick="$('#force-replace').val(1)"/>
            <input type="submit" value="Delete" onclick="$('#force-delete').val(1)"/>
            <input type="checkbox" name="confirm-delete" title="Confirm delete" style="margin-right: 5px;"/>
        </form>
        <form data-container="#snr-response" data-verbose="1" action="index.php" method="POST" enctype="multipart/form-data" style="float:left;">
            <input type="hidden" name="action" value="snr-revert"/>
            <input type="submit" value="Revert"/>
        </form>
        <div style="clear:both"></div>
    </div>
    <div id="snr-response" class="response snr"></div>
</div>
<?php endif; ?>
