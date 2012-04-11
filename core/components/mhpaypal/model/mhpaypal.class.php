<?php
/**
 * mhPayPal
 *
 * Copyright 2011 by Mark Hamstra <hello@markhamstra.com>
 *
 * This file is part of mhPayPal, a real estate property listings component
 * for MODX Revolution.
 *
 * mhPayPal is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * mhPayPal is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * mhPayPal; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
*/

class mhPayPal {
    /* @var modX $modx */
    public $modx = null;
    /* @var phpPaypal $paypal */
    public $paypal = null;
    /* @var array $properties */
    public $properties = array();

    public $output = array();
    public $currencies = array();
    public $data = array();

    public $errors = array();
    public $config = array();
    private $chunks = array();

    protected $useCurrency = null;
    /* @var float $useAmount */
    protected $useAmount = null;
    protected $values = array();

    /**
     * Main mhPayPal constructor for setting up configuration etc.
     *
     * @param \modX $modx
     * @param array $config
     * @return \mhPayPal
     */
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
 
        $basePath = $this->modx->getOption('mhpaypal.core_path',$config,$this->modx->getOption('core_path').'components/mhpaypal/');
        $assetsUrl = $this->modx->getOption('mhpaypal.assets_url',$config,$this->modx->getOption('assets_url').'components/mhpaypal/');
        $assetsPath = $this->modx->getOption('mhpaypal.assets_path',$config,$this->modx->getOption('assets_path').'components/mhpaypal/');
        $this->config = array_merge(array(
            'base_bath' => $basePath,
            'core_path' => $basePath,
            'model_path' => $basePath.'model/',
            'processors_path' => $basePath.'processors/',
            'elements_path' => $basePath.'elements/',
            'assets_path' => $assetsPath,
            'js_url' => $assetsUrl.'js/',
            'css_url' => $assetsUrl.'css/',
            'assets_url' => $assetsUrl,
            'connector_url' => $assetsUrl.'connector.php',
        ),$config);

        $this->modx->addPackage('mhpaypal',$this->config['model_path']);
        $this->modx->lexicon->load('mhpaypal:default');
    }

    /**
     * Optional context specific initialization.
     *
     * @param string $ctx Context name
     * @return bool
     */
    public function initialize($ctx = 'web') {
        switch ($ctx) {
            case 'mgr':
            break;
        }
        return true;
    }

    /**
    * Gets a Chunk and caches it; also falls back to file-based templates
    * for easier debugging.
    *
    * @author Shaun McCormick
    * @access public
    * @param string $name The name of the Chunk
    * @param array $properties The properties for the Chunk
    * @return string The processed content of the Chunk
    */
    public function getChunk($name,$properties = array()) {
        $chunk = null;
        if (!isset($this->chunks[$name])) {
            $chunk = $this->modx->getObject('modChunk',array('name' => $name),true);
            if (empty($chunk)) {
                $chunk = $this->_getTplChunk($name);
                if ($chunk == false) return false;
            }
            $this->chunks[$name] = $chunk->getContent();
        } else {
            $o = $this->chunks[$name];
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setContent($o);
        }
        $chunk->setCacheable(false);
        return $chunk->process($properties);
    }

    /**
    * Returns a modChunk object from a template file.
    *
    * @author Shaun McCormick
    * @access private
    * @param string $name The name of the Chunk. Will parse to name.chunk.tpl
    * @param string $postFix The postfix to append to the name
    * @return modChunk/boolean Returns the modChunk object if found, otherwise
    * false.
    */
    private function _getTplChunk($name,$postFix = '.tpl') {
        $chunk = false;
        $f = $this->config['elements_path'].'chunks/'.strtolower($name).$postFix;
        if (file_exists($f)) {
            $o = file_get_contents($f);
            /* @var modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk');
            $chunk->set('name',$name);
            $chunk->setContent($o);
        }
        return $chunk;
    }

    /**
     * @param array $values
     * @return array
     */
    public function validateForm(array $values = array()) {
        $this->values = $values;

        $errors = array();
        if (!is_numeric($this->getProperty('amount')) || ($this->getProperty('amount') <= 0)) {
            /* Make sure the amount is set and not empty */
            if (!isset($values['amount']) || empty($values['amount'])) {
                $errors['amount'] = $this->modx->lexicon('validation.fieldrequired');
            }
            /* Make sure the amount is numeric and at least 0 */
            elseif (!is_numeric($values['amount']) || ((float)$values['amount'] < 0)) {
                $errors['amount'] = $this->modx->lexicon('validation.amount.notnumeric');
            }
            /* Make sure the amount is larger than the minium amount. */
            elseif ((float)$values['amount'] < (float)$this->getProperty('minAmount')) {
                $errors['amount'] = $this->modx->lexicon('validation.amount.lessthanmin',array('min' => $this->getProperty('minAmount')));
            }
            else {
                $this->useAmount = (float)$values['amount'];
                $this->values['amount'] = (float)$values['amount'];
            }
        } else {
            $this->useAmount = (float)$this->getProperty('amount');
            $this->values['amount'] = (float)$this->getProperty('amount');
        }

        /* Make sure a currency is set */
        if (!isset($values['currency']) || empty($values['currency'])) {
            $errors['currency'] = $this->modx->lexicon('validation.fieldrequired');
        }
        /* Make sure we accept the currency */
        elseif (!in_array($values['currency'],$this->currencies)) {
            $errors['currency'] = $this->modx->lexicon('validation.currency.notallowed',array('currencies' => implode(', ', $this->currencies)));
        }
        else {
            $this->useCurrency = $values['currency'];
            $this->values['currency'] = $values['currency'];
        }

        $erfs = trim($this->getProperty('extraRequiredFields',''));
        if (!empty($erfs)) {
            $erfs = explode(',',$erfs);
            foreach ($erfs as $extraField) {
                if (!isset($values[$extraField]) || empty($values[$extraField])) {
                    $errors[$extraField] = $this->modx->lexicon('validation.fieldrequired');
                }
            }
        }

        return $errors;
    }

    /**
     * @param array $errors
     */
    public function showForm(array $errors = array()) {
        $formChunk = $this->getProperty('formTpl','mhPayPalTpl');
        $placeholders = array_merge($this->getProperties('config.'),array(
            'action' => $this->modx->makeUrl($this->modx->resource->get('id')),
        ));

        if (!empty($this->values)) {
            foreach ($this->values as $key => $value) {
                if (in_array($key,array(
                    $this->getProperty('submitVar'),
                    'action',
                ))) continue;
                $placeholders[$key] = htmlentities($value, ENT_QUOTES, 'UTF-8');
            }
            if (isset($placeholders['currency'])) {
                $placeholders['currency_'.strtoupper($placeholders['currency'])] = '1';
            }
        }

        $message = array();
        if (!empty($errors)) {
            foreach ($errors as $key => $e) {
                $err = array(
                    'key' => $key,
                    'error' => $e
                );
                $placeholders[$key.'.error'] = $this->getChunk($this->getProperty('errorTpl'),$err);
                $message[] = $this->modx->lexicon('error.message',$err);
            }
        }
        $message = implode($this->getProperty('errorSeparator'),$message);
        $placeholders['errors'] = $message;

        /* Check if the user was logged in & act on that */
        if (!$this->modx->user || !$this->modx->user->hasSessionContext($this->modx->context->get('key'))) {
            if ($this->getProperty('formTplAnonymous')) {
                $formChunk = $this->getProperty('formTplAnonymous');
            }
        }

        $this->output[] = $this->getChunk($formChunk, $placeholders);
    }
    
    /**
     * @return null|\phpPaypal
     */
    public function initiatePaypal () {
        require_once(dirname(__FILE__).'/paypal.class.php');
        
        /* Check if we're in the sandbox or live and fetch the appropriate credentials */
        $p['sandbox'] = $this->modx->getOption('mhpaypal.sandbox',null,true);
        if (!$p['sandbox']) {
            /* We're live */
            $this->paypal = new phpPayPal(false);
            $p['username'] = $this->modx->getOption('mhpaypal.api_username');
            $p['password'] = $this->modx->getOption('mhpaypal.api_password');
            $p['signature'] = $this->modx->getOption('mhpaypal.api_signature');
        } else {
            /* We're using the sandbox */
            $this->paypal = new phpPayPal(true);
            $p['username'] = $this->modx->getOption('mhpaypal.sandbox_username');
            $p['password'] = $this->modx->getOption('mhpaypal.sandbox_password');
            $p['signature'] = $this->modx->getOption('mhpaypal.sandbox_signature');
        }
        
        $this->paypal->API_USERNAME = $p['username'];
        $this->paypal->API_PASSWORD = $p['password'];
        $this->paypal->API_SIGNATURE = $p['signature'];
        
        $this->paypal->ip_address = $_SERVER['REMOTE_ADDR'];
        $this->paypal->version = '60.0';

        return $this->paypal;
    }


    /**
     * @param string $hooks
     *
     * @return array|bool
     */
    public function processHooks($hooks = '') {
        if (empty($hooks)) return true;
        $hooks = (is_array($hooks)) ?  $hooks : explode(',',$hooks);
        $errors = array();

        foreach ($hooks as $hook) {
            $res = $this->_runHook($hook);
            if ($res !== true) {
                $errors[] = $res;
                break;
            }
        }
        return (count($errors) > 0) ? $errors : true;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    private function _runHook($name) {
        $success = false;
        if (method_exists($this,$name)) {
            $success = $this->$name();
        }
        /* @var modSnippet $snippet */
        elseif ($snippet = $this->modx->getObject('modSnippet',array('name' => $name))) {
            $properties = $this->getProperties();
            $properties['mhpp'] =& $this;
            $success = $snippet->process($properties);
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[mhPayPal] Could not find requested hook: ' . $name);
        }
        return $success;
    }

    /**
     * @return bool|string
     */
    public function prepareCheckout() {
        $this->initiatePaypal();

        $this->paypal->currency_code = $this->useCurrency;

        /* Set amounts */
        $this->paypal->amount_total = $this->useAmount;
        if ($this->getProperty('amountTax',0) > 0) {
            $this->paypal->amount_sales_tax = $this->getProperty('amountTax');
        }
        if ($this->getProperty('amountFees',0) > 0) {
            $this->paypal->amount_fee = $this->getProperty('amountFees');
        }
        if ($this->getProperty('amountHandling',0) > 0) {
            $this->paypal->amount_handling = $this->getProperty('amountHandling');
        }

        /**
         * Prepare the description
         * @var modChunk $desc
         */
        $desc = $this->modx->newObject('modChunk');
        $desc->setCacheable(false);
        $desc->setContent($this->getProperty('description'));
        $descVariables = array(
            'currency' => $this->useCurrency,
            'amount' => number_format($this->useAmount, $this->getProperty('decimals',2)),
        );
        $description = $this->paypal->description = $desc->process($descVariables);

        $this->paypal->return_url = $this->modx->makeUrl($this->getProperty('returnResource',1),'','','full');
        $this->paypal->cancel_url = $this->modx->makeUrl($this->getProperty('returnResource',1),'','','full');

        $this->paypal->no_shipping = (bool)!$this->getProperty('shipping');
        
        $this->paypal->user_action = 'commit';

        if ($this->paypal->set_express_checkout()) {
            $token = $this->paypal->Response['TOKEN'];
            $data = array_merge($this->values,array(
                'currency' => $this->useCurrency,
                'amount' => $this->useAmount,
                'description' => $description,
                'token' => $token,
            ));
            if ($this->modx->user && $this->modx->user->get('id') > 0) {
                $data['user'] = $this->modx->user->get('id');
            }
            $this->modx->cacheManager->set('/mhpaypal/'.md5($token),$data);
            $this->paypal->set_express_checkout_successful_redirect();
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'Error preparing donation. Endpoint: '.$this->paypal->API_ENDPOINT.' Request: '.$this->paypal->generateNVPString('SetExpressCheckout').' Response: '.print_r($this->paypal->Response,true));
            return 'Sorry, something went wrong patching you through with PayPal! Timestamp: ' . $this->paypal->_error_date . ' Error code: '.$this->paypal->_error_code;
        }
        return true;
    }


    /**
     * @return array|bool|mixed
     */
    public function doCheckout() {
        /* @var phpPaypal $this->paypal */
        $this->paypal = $this->initiatePaypal();
        $this->paypal->token = $_REQUEST['token'];
        $this->paypal->payer_id = $_REQUEST['PayerID'];
        $data = $this->modx->cacheManager->get('mhpaypal/'.md5($this->paypal->token));

        if ($data) {
            $this->paypal->currency_code = $data['currency'];
            $this->paypal->amount_total = $data['amount'];
            $this->paypal->amount_max = $data['amount'];
            $this->paypal->description = urlencode($data['description']);
            $this->paypal->return_url = $this->modx->makeUrl($this->modx->resource->get('id'),'','','full');
            $this->paypal->no_shipping = !$this->getProperty('shipping');
            $this->paypal->user_action = 'commit';

            if ($this->paypal->do_express_checkout_payment()) {
                $data = array_merge($this->paypal->Response,$data);
                if ($this->paypal->get_express_checkout_details()) {
                    $data = array_merge($this->paypal->Response,$data);
                }
                $data = array_change_key_case($data,CASE_LOWER);

                $this->modx->cacheManager->delete('mhpaypal/'.md5($this->paypal->token));

                $this->data = $data;
                return $data;
            } else {
                $this->modx->log(modX::LOG_LEVEL_ERROR,'Error completing payment. Endpoint: '.$this->paypal->API_ENDPOINT.' Request: '.$this->paypal->generateNVPString('DoExpressCheckoutPayment').' Response: '.print_r($this->paypal->Response,true));
                return 'Sorry, something went wrong completing your PayPal Payment. Timestamp: ' . $this->paypal->_error_date . ' Error code: '.$this->paypal->_error_code;
            }
        } else {
            return 'Sorry, your payment data could not be found.';
        }
    }


    /**
     * Joins existing properties with the array set.
     * @param array $properties
     */
    public function setProperties(array $properties = array()) {
        $this->properties = array_merge($this->properties,$properties);
        if (isset($properties['currencies'])) {
            $this->currencies = explode(',',$properties['currencies']);
        }
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getProperty($key = '', $default = null) {
        if (isset($this->properties[$key]) && !empty($this->properties[$key])) return $this->properties[$key];
        else return $default;
    }

    /**
     * Adds an error.
     * @param $fld
     * @param $msg
     */
    public function addError($fld,$msg) {
        $this->errors[$fld] = $msg;
    }

    /**
     * @param string $prefix
     *
     * @return array
     */
    public function getProperties($prefix = '') {
        $returnArray = array();
        foreach ($this->properties as $key => $value) {
            $returnArray[$prefix.$key] = $value;
        }
        return $returnArray;
    }

    /**
     *
     * @return string
     */
    public function getOutput() {
        return implode($this->getProperty('outputSeparator'),$this->output);
    }

    /**
     * @param array $phs
     */
    public function showSuccessMessage(array $phs = array()) {
        $this->output[] = $this->getChunk($this->getProperty('successTpl','mhPayPalSuccess'),$phs);
    }

    /**
     * @param string $ps
     *
     * @return bool
     */
    public function email($ps = '') {
        $message = $this->getChunk($this->getProperty('emailTpl'.$ps),$this->data);
        $emailto = str_replace('[[+email]]',$this->data['email'], $this->getProperty('emailTo'.$ps, $this->modx->getOption('emailsender')));
        $emailto = explode(',',$emailto);
        $emailcc = str_replace('[[+email]]',$this->data['email'], $this->getProperty('emailCC'.$ps));
        $emailcc = explode(',',$emailcc);
        $emailbcc = str_replace('[[+email]]',$this->data['email'], $this->getProperty('emailBCC'.$ps));
        $emailbcc = explode(',',$emailbcc);
        $emailreplyto = $this->getProperty('emailReplyTo'.$ps,$this->modx->getOption('emailsender'));
        $emailfrom = $this->getProperty('emailFrom'.$ps,$this->modx->getOption('emailsender'));
        $emailfromname = $this->getProperty('emailFromName'.$ps, $this->modx->getOption('site_name'));

        /* @var modChunk $subject */
        $subject = $this->modx->newObject('modChunk');
        $subject->setContent($this->getProperty('emailSubject'.$ps));
        $subject = $subject->process($this->data);


        $this->modx->getService('mail', 'mail.modPHPMailer');
        $this->modx->mail->set(modMail::MAIL_BODY,$message);
        $this->modx->mail->set(modMail::MAIL_FROM,$emailfrom);
        $this->modx->mail->set(modMail::MAIL_FROM_NAME,$emailfromname);
        $this->modx->mail->set(modMail::MAIL_SENDER,$emailfrom);
        $this->modx->mail->set(modMail::MAIL_SUBJECT,$subject);
        foreach ($emailto as $email) { $this->modx->mail->address('to',$email); }
        foreach ($emailcc as $email) { $this->modx->mail->address('cc',$email); }
        foreach ($emailbcc as $email) { $this->modx->mail->address('bcc',$email); }
        $this->modx->mail->address('reply-to',$emailreplyto);
        $this->modx->mail->setHTML(true);
        if (!$this->modx->mail->send()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'An error occurred while trying to send the email: '.$this->modx->mail->mailer->ErrorInfo);
        }
        $this->modx->mail->reset();

        return true;
    }

    /**
     * @return bool
     */
    public function email2 () {
        return $this->email('2');
    }

    /**
     * @return bool|void
     */
    public function redirect () {
        $params = $this->modx->fromJSON($this->getProperty('redirectParams'));
        if (is_numeric($this->getProperty('redirectTo')) && ($this->getProperty('redirectTo') > 0)) {
            $url = $this->modx->makeUrl($this->getProperty('redirectTo'), $this->getProperty('redirectContext'), (is_array($params)) ? $params : null, $this->getProperty('redirectScheme'));
        } else {
            $url = $this->getProperty('redirectTo');
        }
        if (!$url || empty($url)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[mhPayPal] Could not resolve redirectTo target: '.$this->getProperty('redirectTo'));
            return false;
        }
        return $this->modx->sendRedirect($url);
    }

}

?>
