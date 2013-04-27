<?php
/* @var modX $modx
 * @var array $scriptProperties
 * @var mhPayPal $mhpp
 **/ 

/* Get the mhPayPal class */
$path = $modx->getOption('mhpaypal.core_path',null,$modx->getOption('core_path').'components/mhpaypal/').'model/';
$mhpp = $modx->getService('mhpp','mhPayPal',$path);
if (!$mhpp) return 'Error loading mhPayPal class.';

/* Get & Set Properties */
$sp = array( // Defaults
    /* Amounts */
    'currencies' => 'EUR,USD,GBP',
    'amount' => 0,
    'amountTax' => 0,
    'amountFees' => 0,
    'amountHandling' => 0,
    'minAmount' => 5,
    'decimals' => 2,

    /* Form handling */
    'formTpl' => 'mhPayPalTpl',
    'formTplAnonymous' => '',
    'errorTpl' => 'mhPayPalErrorTpl',
    'successTpl' => 'mhPayPalSuccess',
    'errorSeparator' => '<br />',
    'extraRequiredFields' => '',
    'method' => 'POST',
    'submitVar' => 'makeDonation',
    'id' => 'pp',
    'showFormOnSuccess' => true,

    /* PayPal stuff */
    'returnResource' => $modx->resource->get('id'),
    'failureResource' => $modx->resource->get('id'),
    'description' => 'Your [[+currency]][[+amount]] Donation',
    'shipping' => false,

    /* Hooks */
    'preHooks' => '',
    'postHooks' => '',
    'postPaymentHooks' => '',
    'outputSeparator' => "\n", // Used to join together the internal output array

    /* Email hook */
    'emailTpl' => 'mhpaypalemail',
    'emailSubject' => 'Thank you for your Donation!',
    'emailTo' => '',
    'emailCC' => '',
    'emailBCC' => '',
    'emailFrom' => '',
    'emailFromName' => '',
    'emailTpl2' => 'mhpaypalemail2',
    'emailSubject2' => 'New Donation received from [[+name]]',
    'emailTo2' => '',
    'emailCC2' => '',
    'emailBCC2' => '',
    'emailFrom2' => '',
    'emailFromName2' => '',

    /* Redirect hook */
    'redirectTo' => 0,
    'redirectParams' => '',
    'redirectContext' => '',
    'redirectScheme' => $modx->getOption('link_tag_scheme', null, -1),
);
$sp = array_merge($sp,$scriptProperties);
$mhpp->setProperties($sp);
$gpc = (strtolower($mhpp->getProperty('method')) == 'get') ? $_GET : $_POST;

/* If we got a token & PayerID var we just returned from PayPal */
if (isset($_REQUEST['token']) && isset($_REQUEST['PayerID'])) {
    $data = $mhpp->doCheckout();
    if (is_array($data)) {
        $result = $mhpp->processHooks($mhpp->getProperty('postPaymentHooks'));
        if ($result === true) {
            $mhpp->showSuccessMessage($data);
            if ((bool)$mhpp->getProperty('showFormOnSuccess',false)) {
                $mhpp->showForm();
            }
        } else {
            $mhpp->showForm($result);
        }
    } else {
        $mhpp->output[] = '<p class="error">' . $data . '</p>';
        $mhpp->showForm();
    }
}

elseif (isset($gpc[$sp['submitVar']])) {
    $mhpp->processHooks($mhpp->getProperty('postHooks'), $gpc);
    $errors = $mhpp->validateForm($gpc);
    if (empty($errors)) {
        $result = $mhpp->prepareCheckout();
        if ($result !== true) {
            return $result;
        }
    }
    $mhpp->showForm($errors);
}

else {
    $mhpp->processHooks($mhpp->getProperty('preHooks'));
    $mhpp->showForm();
}

return $mhpp->getOutput();


?>
