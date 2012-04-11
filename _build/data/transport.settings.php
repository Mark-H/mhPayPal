<?php
/* @var modX $modx */

$s = array(
    'sandbox' => true,
    'api_username' => '',
    'api_password' => '',
    'api_signature' => '',
    'sandbox_username' => 'handh_1315356862_biz_api1.markhamstra.com',
    'sandbox_password' => '1315356902',
    'sandbox_signature' => 'AFcWxV21C7fd0v3bYYYRCpSSRl31AGBdEj9UxkUfhYJqGJpi6oNU25Wx',
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
