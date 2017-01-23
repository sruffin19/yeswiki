<?php
$query = $_SERVER['QUERY_STRING'];
header("Location: index.php?$query");
//exit;
