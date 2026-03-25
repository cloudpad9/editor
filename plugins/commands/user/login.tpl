<!DOCTYPE html>
<html lang="en" ng:app="app">
    <?php include('header.tpl'); ?>

    <body>
        <div class="app_header">
            <a class="logo" href="index.php">CloudPad9</a>
        </div>
        <div class="login_wrapper" id="auth">
            <div class="module">
                <div class="header">
                    <span><?php echo _t('Log In to Your Account'); ?></span>
                </div>
                <?php if (isset($message) && $message) : ?>
                    <p class="group"><?php echo _t($message); ?></p>
                <?php endif; ?>
                <?php if ($error) : ?>
                    <p class="group error"><?php echo _t($error); ?></p>
                <?php endif; ?>
                <form action="index.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="action" value="user/login"/>
                    <div class="group">
                        <label><?php echo _t('Username'); ?></label> <input type="text" name="username" autocapitalize="none" value="<?php echo $username; ?>" required autocomplete="off"/>
                    </div>
                    <div class="group">
                        <label><?php echo _t('Password'); ?></label> <input type="password" name="password" value="<?php echo $password; ?>" required autocomplete="new-password"/>
                    </div>
                    <div class="group">
                        <label></label>
                        <input type="submit" class="btn" value="<?php echo _t('Sign in and Continue'); ?>"/>
                    </div>
                </form>
            </div>
        </div>

        <!-- Auth -->
    </body>
</html>
