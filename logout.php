<?php
session_start();
session_unset();
session_destroy();
header('Location: role_selection.php');
exit;

