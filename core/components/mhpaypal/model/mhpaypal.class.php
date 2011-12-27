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
    /* @var phpPaypal $this->paypal */
    public $paypal = null;
    
    public $config = array();
    private $chunks = array();

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
    
    
    /*
     * @return phpPayPal
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
    
    public function doDonation() {
        /* @var phpPaypal $pp */
        $pp = $this->initiatePaypal();
        $pp->token = $_REQUEST['token'];
        $pp->payer_id = $_REQUEST['PayerID'];
        $data = $this->modx->cacheManager->get('mhpaypal/'.$pp->token);

        if ($data) {
            $pp->currency_code = $data['currency'];
            $pp->amount_total = $data['amount'];
            $pp->amount_max = $data['amount'];
            $pp->description = urlencode($data['description']);
            $pp->return_url = $this->modx->makeUrl($this->modx->resource->get('id'),'','','full');
            $pp->no_shipping = true;
            $pp->user_action = 'commit';
            
            if ($pp->do_express_checkout_payment()) {
                $this->modx->cacheManager->delete('mhpaypal/'.$pp->token);
                $data = array_merge($pp->Response,$data,array(
                    'success' => 1
                ));
                return $this->getChunk('mhpaypalsuccess',$data);
            }
        }
        return '';
    }

}

?>
