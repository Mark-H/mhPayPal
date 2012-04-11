<?php
/* @var modX $modx */

$s = array(
    'sandbox' => true,
    'api_username' => '',
    'api_password' => '',
    'api_signature' => '',
    'sandbox_username' => '',
    'sandbox_password' => '',
    'sandbox_signature' => '',
);

$settings = array();

foreach ($s as $key => $value) {
    if (is_string($value) || is_int($value)) { $type = 'textfield'; }
    elseif (is_bool($value)) { $type = 'combo-boolean'; }
    else { $type = 'textfield'; }

    $area = 'Default';
    $settings['mhpaypal.'.$key] = $modx->newObject('modSystemSetting');
    $settings['mhpaypal.'.$key]->set('key', 'mhpaypal.'.$key);
    $settings['mhpaypal.'.$key]->fromArray(array(
        'value' => $value,
        'xtype' => $type,
        'namespace' => 'mhpaypal',
        'area' => $area
    ));
}

return $settings;

?>
