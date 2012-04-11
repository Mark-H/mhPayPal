<?php

$snips = array(
    'mhPayPal' => 'The main snippet for the mhPayPal package. Please see the documenation at http://rtfm.modx.com/display/ADDON/mhPayPal.Snippet+Usage for instructions.',
);

$snippets = array();
$idx = 0;

foreach ($snips as $sn => $sdesc) {
    $idx++;
    $snippets[$idx] = $modx->newObject('modSnippet');
    $snippets[$idx]->fromArray(array(
       'id' => $idx,
       'name' => $sn,
       'description' => $sdesc . ' (Part of mhPayPal)',
       'snippet' => getSnippetContent($sources['snippets']. strtolower($sn) . '.inc.php')
    ));

    $snippetProperties = array();
    $props = array(); //include $sources['snippets'] . 'properties.' . strtolower($sn) . '.php';
    foreach ($props as $key => $value) {
        if (is_string($value) || is_int($value)) { $type = 'textfield'; }
        elseif (is_bool($value)) { $type = 'combo-boolean'; }
        else { $type = 'textfield'; }
        $snippetProperties[] = array(
            'name' => $key,
            'desc' => 'mhPayPal.prop_desc.'.$key,
            'type' => $type,
            'options' => '',
            'value' => ($value != null) ? $value : '',
            'lexicon' => 'mhPayPal:properties'
        );
    }

    if (count($snippetProperties) > 0)
        $snippets[$idx]->setProperties($snippetProperties);
}

return $snippets;
