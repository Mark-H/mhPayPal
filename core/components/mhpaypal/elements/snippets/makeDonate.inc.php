<?php
/* @var modX $modx */
$path = $modx->getOption('mhpaypal.core_path',null,$modx->getOption('core_path').'components/mhpaypal/').'elements/snippets/';
return include $path.'makeDonate.snippet.php';
