<?php

/**
 * Snippet will make a List of Videos to be consumed by a TV to make a List Drop down option
 */

/** @var string $item ~ this will be the item chunk for each iteration of video  */
$item = $modx->getOption('item', $scriptProperties, 'flowPlayerVideoTVItem');

/** @var string $item_separator to use with a TV set || */
$item_separator = $modx->getOption('itemSeparator', $scriptProperties, "||");

/** @var string $tags */
$tags = $modx->getOption('tags', $scriptProperties, null);

/** @var string $search ~ search titles */
$search = $modx->getOption('search', $scriptProperties, null);

if(is_object($modx->resource)) {
    $tags = $modx->resource->getTVValue('videoTags');
    $search = $modx->resource->getTVValue('videoSearch');
}

/** @var int $cache_time ~ in seconds */
$cache_time = $modx->getOption('cacheTime', $scriptProperties, 3600);

/** @var boolean $debug */
$debug = $modx->getOption('debug', $scriptProperties, true);

if ( $debug ) {
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
}

$core_path = $modx->getOption('flowplayerdrive.core_path', null, $modx->getOption('core_path').'components/flowplayerdrive/');
require_once $core_path.'model/flowplayerdrive/FlowPlayerDrive.php';

$flowPlayerDrive = new FlowPlayerDrive($modx);

if ( $debug ) {
    $flowPlayerDrive->setDebug();
    $flowPlayerDrive->setUseCache(false);
}

$videos = $flowPlayerDrive->getVideos($search, $tags, $cache_time);

// iterate the clips:
$list = '';
$item_count = 1;
foreach ($videos as $count => $video ) {
    if ( $item_count > 1) {
        $list .= $item_separator;
    }
    $video['clipCount'] = $item_count++;
    $video['source'] = array(
        'webm' => $flowPlayerDrive->getEncodingInfo($video['encodings'], 'webm'),
        'mp4'  => $flowPlayerDrive->getEncodingInfo($video['encodings'], 'mp4'),
        'hls'  => $flowPlayerDrive->getEncodingInfo($video['encodings'], 'hls')
    );
    unset($video['encodings']);
    $list .=
        $modx->getChunk(
            $item,
            $video
        );
}

return $list;