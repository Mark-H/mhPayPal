<?php
/* This hook will set up a token and will redirect the user to PayPal to finish their donation. */

/* @var modX $modx
 * @var array $scriptProperties
 * @var phpPaypal $pp 
 * @var fiHooks $hook
 **/

$path = $modx->getOption('mhpaypal.core_path',null,$modx->getOption('core_path').'components/mhpaypal/').'model/';
if (!$modx->getService('mhpp','mhPayPal',$path)) return 'Error getting service.';


/* Get currency */
$currency = in_array(strtoupper($hook->getValue('amount_cur')),array('EUR','USD','GBP')) ? strtoupper($hook->getValue('amount_cur')) : null;
if (!$currency) $hook->addError('amount_cur','Sorry, I can\'t accept that currency.');

/* Get amount */
$amount = (is_numeric($hook->getValue('amount'))) ? (float)$hook->getValue('amount') : 0;
if ($amount < 1) $hook->addError('amount','Please choose an amount larger than 1.');

/* If we have any errors so far, halt. */
if ($hook->hasErrors()) {
    return false;
}

/* Get project (=resource) for description */
$project = $hook->formit->config['ppProject'];
if ((int)$project < 1) {
    $hook->modx->log(modX::LOG_LEVEL_ERROR,'[mhPayPal] Project '.$project.' is not valid.');
    return false;
}
if ($project == $modx->resource->get('id')) {
    $project = $modx->resource;
} else {
    $project = $hook->modx->getObject('modResource',$project);
}
if (!($project instanceof modResource)) {
    $hook->modx->log(modX::LOG_LEVEL_ERROR,'[mhPayPal] Project '.$project.' could not be found.');
    return false;
}
$description = "Donation for {$project->get('pagetitle')}";

/* Get URLs */
$fail = $hook->formit->config['ppFailure'];
if ((int)$fail < 1) { $hook->modx->log(modX::LOG_LEVEL_ERROR,'[mhPayPal] Failure resource '.$fail.' is not valid.'); return false; }
$fail = $hook->modx->makeUrl($fail,'',array(),'full');

$return = $hook->formit->config['ppFailure'];
if ((int)$return < 1) { $hook->modx->log(modX::LOG_LEVEL_ERROR,'[mhPayPal] Return resource '.$return.' is not valid.'); return false; }
$return = $hook->modx->makeUrl($return,'',array(),'full');

$pp = $modx->mhpp->initiatePaypal();
$pp->currency_code = $currency;
$pp->amount_total = $amount;
$pp->amount_max = $amount;
$pp->description = urlencode($description);
$pp->return_url = $return;
$pp->cancel_url = $fail;
$pp->no_shipping = true;
$pp->user_action = 'commit';

if ($pp->set_express_checkout()) {
    $token = $pp->Response['TOKEN'];
    $data = array(
        'currency' => $currency,
        'amount' => $amount,
        'description' => $description,
    );
    $modx->cacheManager->set('/mhpaypal/'.$token,$data);


    $pp->set_express_checkout_successful_redirect();
} else {
    $hook->addError('amount','Error preparing checkout.');
    $modx->log(modX::LOG_LEVEL_ERROR,'Error preparing donation. Endpoint: '.$pp->API_ENDPOINT.' Request: '.$pp->generateNVPString('SetExpressCheckout').' Response: '.print_r($pp->Response,true));
    return false;
}
return true;

?>
