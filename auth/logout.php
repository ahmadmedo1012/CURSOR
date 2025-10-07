<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::logout();

echo '<script>window.refreshAuthUI && window.refreshAuthUI(); location.replace("/?logged_out=1");</script>';
exit;
?>
