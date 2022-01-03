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

function processItem ($item) {
  global $drupal;
  global $articles;
  $content = null;

  $current = $item->firstChild;
  $data = [
    'type' => [[ 'target_id' => 'article' ]],
    'field_tags' => [],
  ];

  while ($current) {
    if ($current->nodeName === 'link') {
      $data['field_id'] = [[ 'value' => $current->textContent ]];
    }
    if ($current->nodeName === 'category') {
      $data['field_tags'][] = [ 'target_id' => getCategory($current->textContent) ];
    }
    if ($current->nodeName === 'title') {
      $data['title'] = [[ 'value' => $current->textContent ]];
    }
    if ($current->nodeName === 'pubDate') {
      $d = new DateTime($current->textContent);
      $data['created'] = [[ 'value' => $d->format('c') ]];
    }
    if ($current->nodeName === 'content:encoded') {
      $body = $current->textContent;
    }

    $current = $current->nextSibling;
  }

  if (!array_key_exists($data['field_id'][0]['value'], $articles)) {
    $content = $drupal->nodeSave(null, $data);
    $id = $content['nid'][0]['value'];

    $data = [
      'type' => [[ 'target_id' => 'article' ]],
      'field_content' => parseContent($id, $body),
    ];

    $content = $drupal->nodeSave($id, $data);
  }
}

function getCategory ($category) {
  global $categories;
  global $drupal;

  if (array_key_exists($category, $categories)) {
    return $categories[$category];
  }

  $content = [
    'vid' => [[ 'target_id' => 'tags' ]],
    'name' => [[ 'value' => $category ]],
  ];

  print "Creating {$category} ";
  $content = $drupal->taxonomySave(null, $content);
  print "-> {$content['tid'][0]['value']}\n";

  $categories[$category] = $content['tid'][0]['value'];
}

function parseContent ($parent_id, $text) {
  $dom = new DOMDocument();
  $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $text . '</body></html>');

  $result = [];

  $content = '';
  $current = $dom->getElementsByTagName('body')->item(0)->firstChild;
  while ($current) {
    if ($current->nodeName === 'table') {
      if (trim($content) !== '') {
        $result[] = createTextParagraph($content);
        $content = '';
      }

      $gallery = [];
      foreach ($current->getElementsByTagName('td') as $td) {
        $url = $td->getElementsByTagName('a')->item(0)->getAttribute('href');
        $gallery[] = [
          'url' => $url,
          'title' => trim($td->textContent),
        ];
      }

      //$result[] = $gallery;
    } else {
      $content .= $dom->saveHTML($current);
    }

    $current = $current->nextSibling;
  }

  if (trim($content) !== '') {
    $result[] = createTextParagraph($parent_id, $content);
  }

  return $result;
}

function createTextParagraph ($parent_id, $text) {
  global $drupal;

  $content = [
    'type' => [[ 'target_id' => 'text_block' ]],
    'parent_type' => [[ 'value' => 'node' ]],
    'parent_id' => [[ 'value' => $parent_id ]],
    'parent_field_name' => [[ 'value' => 'field_content' ]],
    'field_body' => [[ 'value' => $text, 'format' => 'full_html' ]],
  ];

  $content = $drupal->paragraphSave(null, $content);

  return [
    'target_id' => $content['id'][0]['value'],
    'target_revision_id' => $content['revision_id'][0]['value'],
  ];
}
