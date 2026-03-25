<!DOCTYPE html>
<html lang="en" ng:app="app">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, minimal-ui, viewport-fit=cover" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="robots" content="noindex, nofollow" />
        <title>CLOUDPAD9</title>

        <!-- Cache control -->
        <meta http-equiv="cache-control" content="max-age=0" />
        <meta http-equiv="cache-control" content="no-cache" />
        <meta http-equiv="expires" content="0" />
        <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
        <meta http-equiv="pragma" content="no-cache" />

        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Roboto:400,400i,500,700&amp;subset=vietnamese" rel="stylesheet">

        <!-- Bootstrap -->
        <!--link href="css/bootstrap.min.css" rel="stylesheet"-->
        <link rel="stylesheet" href="lib/bootstrap/bootstrap.min.css">

        <!-- Alerts -->
        <link rel="stylesheet" href="lib/jquery.alerts/jquery.alerts.css" type="text/css" media="screen" />

        <!-- jQuery UI -->
        <link href="lib/jquery-ui/jquery-ui.css" rel="stylesheet">

        <!-- Multiselect -->
        <link href="lib/jquery.multiselect/jquery.multiselect.css" rel="stylesheet">

        <!-- Autocomplete -->
        <link href="css/jquery.autocomplete.css" rel="stylesheet">

        <!-- Builder -->
        <link href="css/builder.css?v=<?php echo date('His'); ?>" rel="stylesheet">
        <link href="css/builder-responsive.css?v=3" rel="stylesheet">

        <!-- Theme -->
        <?php if (isset($_REQUEST['theme'])) : ?>
            <?php $theme = preg_replace('/[^a-z0-9\-]/', '', strtolower($_REQUEST['theme'])); ?>
            <link href="css/theme-<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>.css" rel="stylesheet">
        <?php endif; ?>
    
        <!-- jQuery -->
        <script src="js/jquery.min.js"></script>

        <!-- Vue 2 -->
        <script src="lib/vue@2.7.16/vue.min.js"></script>
        <script src="lib/axios@1.6.7/axios.min.js"></script>

        <!-- xterm -->
        <link rel="stylesheet" href="lib/xterm@5.3.0/xterm.css" />
        <script src="lib/xterm@5.3.0/xterm.js"></script>
        <script src="lib/xterm-addon-attach@0.9.0/xterm-addon-attach.js"></script>
        <script src="lib/xterm-addon-fit@0.8.0/xterm-addon-fit.min.js"></script>

        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

        <script type="text/javascript">
            var USER_SESSION_ID = '<?php echo $builder->getUserSessionId(); ?>'
        </script>
    </head>
    <body>
        <div id="blocked-overlay"></div>

        <div id="overlay">
            <img src="images/loading.gif">
            <div class="meter animate">
                <span><span></span></span>
            </div>
        </div>

        <!-- App -->
        <?php if ($builder->isUserLoggedIn()) : ?>
            <a class="logout tmp-hidden" href="index.php?action=user/logout"><?php echo _t('Logout'); ?></a>
        <?php else : ?>
            <a class="logout" href="index.php?action=user/login"><?php echo _t('Login'); ?></a>
        <?php endif; ?>

        <div class="app-name tmp-hidden"><span class="icon-menu" id="app-btn-menu" onclick="$('body').toggleClass('menu-open')"></span>CloudPad9<span class="v"></span></div>

        <div class="app-tabs" style="display:none">
            <?php $tabs = $builder->getUserTabs(); ?>

            <ul>
                <?php foreach ($tabs as $tab => $handler) : ?>
                    <li><a href="#tabs-<?php echo $tab; ?>"><?php echo _t($handler->getTabTitle()); ?></a></li>
                <?php endforeach; ?>
            </ul>

            <?php foreach ($tabs as $tab => $handler) : ?>
                <div id="tabs-<?php echo $tab; ?>">
                    <?php $handler->render($builder); ?>
                </div>
            <?php endforeach; ?>

            <div class="app-buttons">
                <span class="pointer icon-settings" onclick="$('#app-settings').toggle()"></span>
                <span class="pointer icon-exit" onclick="window.location = 'index.php?action=user/logout'"></span>
            </div>
        </div>

        <div id="popup" class="popup"></div>

        <!-- jQuery UI -->
        <script src="js/jquery-ui.js"></script>
        <script src="js/jquery.ui.touch-punch.min.js"></script>

        <!-- Include all compiled plugins (below), or include individual files as needed -->
        <script src="lib/popper/popper.min.js"></script>
        <script src="lib/bootstrap/bootstrap.min.js"></script>
        <!--<script src="js/jquery.textarea-autogrow.js"></script>-->

        <!-- Alerts -->
        <script type="text/javascript" src="lib/jquery.alerts/jquery.alerts.js"></script>

        <!-- ACE editor -->
        <script src="js/ace-min-noconflict/ace.js"></script>
        <script src="js/ace-min-noconflict/ext-language_tools.js"></script>

        <!-- Multiselect -->
        <script src="lib/jquery.multiselect/jquery.multiselect.min.js"></script>

        <!-- Autocomplete -->
        <script src="js/jquery.autocomplete.js"></script>

        <!-- Live search -->
        <script src="js/jquery.livesearch.js"></script>

        <!-- Builder script -->
        <script src="js/builder.js"></script>
    </body>
</html>
