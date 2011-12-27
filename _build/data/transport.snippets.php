<?php

$snips = array(
    'makeDonate' => 'Put the makeDonate snippet where you want the form to show up.',
    'makeDonateHook' => 'The makeDonateHook snippet is used as hook for the FormIt call in the form.',
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
            'desc' => 'designeveryday.prop_desc.'.$key,
            'type' => $type,
            'options' => '',
            'value' => ($value != null) ? $value : '',
            'lexicon' => 'designeveryday:properties'
        );
    }

    if (count($snippetProperties) > 0)
        $snippets[$idx]->setProperties($snippetProperties);
}

return $snippets;
