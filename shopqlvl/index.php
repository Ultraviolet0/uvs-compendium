<?php
declare(strict_types=1);

// Permanent redirect from the old Shop Qlvl Calculator URL
// to the new calculator location.
$destination = '/calculators/shop-qlvl/';

if (!empty($_SERVER['QUERY_STRING'])) {
    $destination .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $destination, true, 301);
exit;
