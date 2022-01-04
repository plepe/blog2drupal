#!/usr/bin/php
<?php
require 'lib/drupal-rest-php/drupal.php';
require 'functions.php';

$drupal_conf = [
  'url' => 'https://new.plepe.at',
  'user' => 'plepe',
  'pass' => '',
  'curl_options' => [
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
  ],
];

$drupal = new DrupalRestAPI($drupal_conf);

$_categories = $drupal->loadRestExport('/rest/tags');
$categories = [];
foreach ($_categories as $c) {
  $categories[$c['name'][0]['value']] = $c['tid'][0]['value'];
}

$_articles = $drupal->loadRestExport('/rest/blog');
$articles = [];
foreach ($_articles as $c) {
  $articles[$c['field_id'][0]['value']] = $c['nid'][0]['value'];
}

$dom = new DOMDocument();
$dom->loadXML(file_get_contents('http://plepe.at/feed'));

$channel = $dom->firstChild->firstChild->nextSibling;
$current = $channel->firstChild;
while ($current) {
  if ($current->nodeName === 'item') {
    processItem($current);
  }

  $current = $current->nextSibling;
}


