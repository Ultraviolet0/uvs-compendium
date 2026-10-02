<?php
declare(strict_types=1);

$destination = '/calculators/damage/';
if (!empty($_SERVER['QUERY_STRING'])) {
  $destination .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $destination, true, 301);
exit;
