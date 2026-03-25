<!DOCTYPE html>
<html lang="en">
    <?php include('header.tpl'); ?>

    <body>
        <?php include(BUILDER_DIR.'/tpl/analytics__body.tpl'); ?>

        <div class="app_header">
            <a class="logo" href="index.php">CloudPad9</a>
        </div>
        <div class="login_wrapper">
            <div class="module padding_bottom">
                <div class="header">
                    <span><?php echo _t('Error'); ?></span>
                </div>
                <p class="group"><?php echo _t($error); ?></p>
            </div>
        </div>
    </body>
</html>
