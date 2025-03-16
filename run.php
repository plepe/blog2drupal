#!/usr/bin/php
<?php
require 'lib/drupal-rest-php/drupal.php';
require 'functions.php';

$dry_run = true;
$debug = true;

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
$fake_id = 0;

$_categories = $drupal->loadRestExport('/rest/tags');
$categories = [];
foreach ($_categories as $c) {
  $categories[$c['name'][0]['value']] = $c['tid'][0]['value'];
}

$nodes = $drupal->loadRestExport('/rest/content?type=topic');
$topics = [];
foreach ($nodes as $n) {
  if (sizeof($n['field_short_title'])) {
    $topics[$n['field_short_title'][0]['value']] = $n['nid'][0]['value'];
  }
}

$cat2topic = [];
foreach ($categories as $name => $id) {
  if (array_key_exists($name, $topics)) {
    $cat2topic[$id] = $topics[$name];
  } else {
    $content = [
      'type' => [[ 'target_id' => 'topic' ]],
      'title' => [[ 'value' => $name ]],
      'field_short_title' => [[ 'value' => $name ]],
    ];

    print "TOPIC {$name} -> ";

    if ($dry_run) {
      $content['nid'] = [['value' => 'f' . $fake_id++ ]];
    } else {
      $content = $drupal->nodeSave(null, $content);
    }
    $cat2topic[$id] = $content['nid'][0]['value'];
    print "{$content['nid'][0]['value']}\n";

    if ($debug) {
      print_r($content);
    }
  }
}

$articles = $drupal->loadRestExport('/rest/content?type=article');
//foreach ($articles as $node) {
//  $content = [
//    'type' => $node['type'],
//    'field_topics' => [],
//  ];
//
//  foreach ($node['field_tags'] as $tag) {
//    $content['field_topics'][] = ['target_id' => $cat2topic[$tag['target_id']]];
//  }
//
//  print "SAVE {$node['nid'][0]['value']} ";
//  print_r($content);
//  //$drupal->nodeSave($node['nid'][0]['value'], $content);
//}

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


