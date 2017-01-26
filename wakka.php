<?php
/**
 * Uniquement présent à des fin de compatibilité. A supprimer a terme.
 */

$query = $_SERVER['QUERY_STRING'];
header("Location: index.php?$query");
