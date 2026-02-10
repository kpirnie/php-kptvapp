<?php

/**
 * 
 * No direct access allowed!
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

use KPT\KPT;

// fire up the playlist class
$playlist = new KPTV_Stream_Playlists();

// setup the type of stream to pull...
$stream_type = ['live' => 0, 'series' => 5, 'vod' => 4];

// if there is no provider
if (! isset($provider)) {

    // pull the records
    $records = $playlist->handleUserPlaylist($user, $stream_type[$which]);

    // there is
} else {

    // pull the records
    $records = $playlist->handleProviderPlaylist($user, $provider, $stream_type[$which]);
}

// make sure there's records
if ($records) {

    // set the mimetype and filename to download
    header('Content-Type: application/mpegurl');
    header('Content-Disposition: attachment; filename="' . $which . '.m3u8"');

    // set the expires and caching headers
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() + KPTV::DAY_IN_SECONDS) . " GMT", true);
    header('Cache-Control: public, max-age=' . KPTV::DAY_IN_SECONDS, true);
    header_remove('set-cookie');

    // start the M3U no matter what
    echo "#EXTM3U" . PHP_EOL;

    // loop over the records
    foreach ($records as $rec) {

        // start creating the EXTINF line
        $extinf = sprintf('#EXTINF:-1 tvg-name="%s" tvg-chno="%s" tvg-type="%s"', $rec->TvgName, $rec->TvgChNo, $rec->TvgType);

        // if there's a tvg-group
        if (! empty($rec->TvgGroup)) {
            $extinf .= sprintf(' tvg-group="%s"', $which);
            $extinf .= sprintf(' group-title="%s"', $which);
        }

        // if there's a tvg-id
        if (! empty($rec->TvgID)) {
            $extinf .= sprintf(' tvg-id="%s"', $rec->TvgID);
            $extinf .= sprintf(' channel-id="%s"', $rec->TvgID);
            $extinf .= sprintf(' tvg-epgid="%s"', $rec->TvgID);
            //$extinf .= sprintf( ' tvc-guide-stationid="%s"', $rec -> TvgID );
        }

        // if there's a tvg-logo
        if (! empty($rec->TvgLogo)) {
            $extinf .= sprintf(' tvg-logo="%s"', $rec->TvgLogo);
        }

        // finish up the extinf line and write it out
        $extinf .= sprintf(', %s', $rec->TvgName);
        echo $extinf . PHP_EOL;

        // write out the stream url line
        echo $rec->Stream . PHP_EOL;
    }
}
