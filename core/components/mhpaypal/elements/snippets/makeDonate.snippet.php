<?php
/* @var modX $modx
 * @var array $scriptProperties 
 **/ 

$path = $modx->getOption('mhpaypal.core_path',null,$modx->getOption('core_path').'components/mhpaypal/').'model/';
if (!$modx->getService('mhpp','mhPayPal',$path)) return 'Error getting service.';

$sp = array(
    'project' => $modx->resource->get('id'),
    'formTpl' => 'makeDonateTpl',
    'id' => 'df',
    'method' => 'POST',
    'return' => $modx->resource->get('id'),
    'failure' => $modx->resource->get('id'),
    'extrahooks' => '',
    'extrasettings' => '',
);

$sp = array_merge($sp,$scriptProperties);
$sp['action'] = $modx->makeUrl($modx->resource->get('id'));

$result = $modx->mhpp->getChunk($sp['formTpl'],$sp);
if (empty($result)) return 'Error getting form.';

if (isset($_REQUEST['token']) && isset($_REQUEST['PayerID'])) {
    $donationResult = $modx->mhpp->doDonation();
    $result = $donationResult . $result;
}

return $result;


?>
