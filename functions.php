<?php
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
      $body = getBody($current->textContent);
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

function parseContent ($text) {
  $dom = new DOMDocument();
  $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $text . '</body></html>');

  $result = [];

  $content = '';
  $current = $dom->getElementsByTagName('body')->item(0)->firstChild;
  while ($current) {
    if ($current->nodeName === 'style' ||
        ($current->nodeName === 'p' && $current->getAttribute('class') === 'postmetadata alt')
       ) {
      // ignore
    }
    elseif ($current->nodeName === 'p' && $current->getElementsByTagName('img')->length) {
      if (trim($content) !== '') {
        $result[] = [
          'type' => 'text_block',
          'content' => $content,
        ];

        $content = '';
      }
      
      $result[] = [
        'type' => 'image',
        'url' => $current->getElementsByTagName('a')->item(0)->getAttribute('href'),
        'title' => $current->textContent,

      ];
    }
    elseif ($current->nodeName === 'div' && $current->getAttribute('data-carousel-extra')) {
      if (trim($content) !== '') {
        $result[] = [
          'type' => 'text_block',
          'content' => $content,
        ];

        $content = '';
      }

      $gallery = [];
      foreach ($current->getElementsByTagName('dl') as $td) {
        $url = $td->getElementsByTagName('img')->item(0)->getAttribute('data-orig-file');
        $gallery[] = [
          'url' => $td->getElementsByTagName('img')->item(0)->getAttribute('data-orig-file'),
          'title' => trim($td->getElementsByTagName('dd')->item(0)->textContent),
        ];
      }

      $result[] = [
        'type' => 'gallery',
        'content' => $gallery,
      ];
    }
    elseif ($current->nodeName === 'table') {
      if (trim($content) !== '') {
        $result[] = [
          'type' => 'text_block',
          'content' => $content,
        ];

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

      $result[] = [
        'type' => 'gallery',
        'content' => $gallery,
      ];
    }
    else {
      $content .= $dom->saveHTML($current);
    }

    $current = $current->nextSibling;
  }

  if (trim($content) !== '') {
    $result[] = [
      'type' => 'text_block',
      'content' => $content,
    ];
  }

  return $result;
}

function saveContent ($parent_id, $content) {
  $result = [];

  foreach ($content as $item) {
    switch ($item['type']) {
      case 'text_block':
        $result[] = createTextParagraph($parent_id, $content);
        break;
      case 'gallery':
        $result[] = createGalleryParagraph ($parent_id, $gallery);
        break;
    }
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

function createGalleryParagraph ($parent_id, $gallery) {
  global $drupal;

  $content = [
    'type' => [[ 'target_id' => 'gallery' ]],
    'parent_type' => [[ 'value' => 'node' ]],
    'parent_id' => [[ 'value' => $parent_id ]],
    'parent_field_name' => [[ 'value' => 'field_content' ]],
    'field_gallery' => [],
  ];

  foreach ($gallery as $item) {
    $file = $drupal->fileUpload($item['url'], 'paragraph/gallery/field_gallery');
    $content['field_gallery'][] = [
      'target_id' => $file['fid'][0]['value'],
      'title' => $item['title'],
    ];
  }

  $content = $drupal->paragraphSave(null, $content);

  return [
    'target_id' => $content['id'][0]['value'],
    'target_revision_id' => $content['revision_id'][0]['value'],
  ];
}

function getBody ($url) {
  $body = '';

  $page = new DOMDocument();
  $page->loadHTMLFile($url);

  foreach ($page->getElementsByTagName('div') as $div) {
    if ($div->getAttribute('class') === 'entry') {
      $current = $div->firstChild;

      while ($current) {
        $body .= $page->saveHTML($current);

        $current = $current->nextSibling;
      }
    }
  }

  return $body;
}
