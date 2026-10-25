<?php
require __DIR__ . '/inc/baslat.php';
$_SESSION = [];
session_regenerate_id(true);
header('Location: ../atolyeler.php');
exit;
