<?php

require_once 'users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';


$data = Server::get('REQUEST_URI');
dnd($data);
logger("", "log test", "logging", ['test' => 'This is a test log entry']);
?>

<!-- Place any per-page javascript here -->
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>