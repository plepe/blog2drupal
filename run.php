#!/usr/bin/php
<?php
require 'lib/drupal-rest-php/drupal.php';

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

$dom = new DOMDocument();
$dom->loadXML(file_get_contents('http://plepe.at/feed'));

$channel = $dom->firstChild->firstChild->nextSibling;
print $channel->nodeName . "\n";
$current = $channel->firstChild;
while ($current) {
  if ($current->nodeName === 'item') {
    processItem($current);
  }

  $current = $current->nextSibling;
}

function processItem ($item) {
  global $drupal;

  $current = $item->firstChild;
  $data = [
    'type' => [[ 'target_id' => 'article' ]],
  ];

  while ($current) {
    print $current->nodeName . "\n";

    if ($current->nodeName === 'title') {
      $data['title'] = [[ 'value' => $current->textContent ]];
    }
    if ($current->nodeName === 'pubDate') {
      $d = new DateTime($current->textContent);
      $data['created'] = [[ 'value' => $d->format('c') ]];
    }
    if ($current->nodeName === 'content:encoded') {
      $data['body'] = [[ 'value' => $current->textContent, 'format' => 'basic_html' ]];
    }

    $current = $current->nextSibling;
  }

  $drupal->nodeSave(null, $data);
}
