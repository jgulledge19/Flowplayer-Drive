<?php

/**
 * Snippet Input Options:
 * EX:
 * [[getDriveVideos?
 *   &search=`tag`
 *   &cacheTime=`21000`
 * ]]
 */
/** @var string $search ~ search titles */
$search = $modx->getOption('search', $scriptProperties, null);
/** @var string $tags */
$tags = $modx->getOption('tags', $scriptProperties, null);

/** @var int $cache_time ~ in seconds */
$cache_time = $modx->getOption('cacheTime', $scriptProperties, 3600);
/** @var boolean $debug */
$debug = $modx->getOption('debug', $scriptProperties, true);

/** @var string $video_chunk ~ this will be the first video in the list to make the video tag */
$video_chunk = $modx->getOption('videoChunk', $scriptProperties, 'flowPlayerVideo');
$placeholder_prefix = $modx->getOption('placeholderPrefix', $scriptProperties, 'flowPlayer');

/** @var string $clip_chunk */
$clip_chunk = $modx->getOption('clipChunk', $scriptProperties, 'flowPlayerClip');

/**
 * @var string $clip_order ~ comma separated list of video IDs in the desired order, if not in list will be placed
 *      in default order after preferred onces
 */
$clip_order = $modx->getOption('clipOrder', $scriptProperties, null);

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

if ( !is_null($clip_order) ) {
    $videos = $flowPlayerDrive->reorderVideos($videos, $clip_order);
}
// build placeholders for video:
//[[+source.webm.format]]" src="[[+source.webm.src]]
$video_placeholders = array();
if ( isset($videos[0]) ) {
    $video_placeholders = $videos[0];
    if (isset($videos[0]['encodings'])) {
        $video_placeholders['source'] = array(
            'webm' => $flowPlayerDrive->getEncodingInfo($videos[0]['encodings'], 'webm'),
            'mp4' => $flowPlayerDrive->getEncodingInfo($videos[0]['encodings'], 'mp4'),
            'hls' => $flowPlayerDrive->getEncodingInfo($videos[0]['encodings'], 'hls')
        );
        $video_placeholders['encodings'];
    }
}
//print_r($video_placeholders);exit();
$video_content = $modx->getChunk(
    $video_chunk,
    $video_placeholders
);

// iterate the clips:
$clips = '';
$clip_count = 1;
foreach ($videos as $count => $video ) {
    $video['clipCount'] = $clip_count++;
    $video['source'] = array(
        'webm' => $flowPlayerDrive->getEncodingInfo($video['encodings'], 'webm'),
        'mp4'  => $flowPlayerDrive->getEncodingInfo($video['encodings'], 'mp4'),
        'hls'  => $flowPlayerDrive->getEncodingInfo($video['encodings'], 'hls')
    );
    unset($video['encodings']);
    $clips .=
        $modx->getChunk(
            $clip_chunk,
            $video
        );
}

$modx->toPlaceholder('video', $video_content, $placeholder_prefix);
$modx->toPlaceholder('clips', $clips, $placeholder_prefix);
if ( isset($videos[0]) && isset($videos[0]['snapshotUrl']) ) {
    $modx->toPlaceHolder('splash', $videos[0]['snapshotUrl'], $placeholder_prefix);
}

return ($debug ? print_r($videos, true) : '');