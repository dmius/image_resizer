<?php
define("DEBUG", 1);

if (!extension_loaded('gd') && !extension_loaded('gd2')) {
    trigger_error("GD is not loaded", E_USER_WARNING);
    return false;
}

set_error_handler(function ($severity, $message, $filepath, $line) {
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE);

$SUPPORTED_TYPES = array(
    'image/png',
    'image/jpg',
    'image/jpeg',
    'image/gif',
);

try {
    $src = @$_GET['src'];
    if (!$src) {
        throw new Exception("Required parameter is not set: 'src'.");
    }
    $data = file_get_contents($src);
    $headers = parseHeaders($http_response_header);
    $contentType = strtolower(@$headers['content-type']);

    if (!$contentType || !in_array($contentType, $SUPPORTED_TYPES)) {
        throw new Exception("Either the file is not an image or its type is not supported.");
    }

    $resImg = null;

    $img = imagecreatefromstring($data);
    $w = imagesx($img);
    $h = imagesy($img);

    $resize = null;
    if (isset($_GET['w']) && ($resW = intval($_GET['w'])) && $resW > 0) { // resize, target: WIDTH
        $resH = round($resW * $h / $w);
        $resize = true;
    }
    if (isset($_GET['h']) && (intval($_GET['h']) > 0) && (!isset($resH) || (intval($_GET['h']) < $resH))) { // resize, target: HEIGHT.
        $resH = intval($_GET['h']);
        $resW = round($resH * $w / $h);
        $resize = true;
    }
    if ($resize) {
        $resImg = imagecreatetruecolor($resW, $resH);
        if (in_array($contentType, array('image/png', 'image/gif'))) { // tricks to preserve transparency of GIF/PNG
            imagealphablending($resImg, false);
            imagesavealpha($resImg, true);
            $transparent = imagecolorallocatealpha($resImg, 255, 255, 255, 1);
            imagefilledrectangle($resImg, 0, 0, $resW, $resH, $transparent);
        }
        imagecopyresampled($resImg, $img, 0, 0, 0, 0, $resW, $resH, $w, $h);
    } else {
        $resImg = $img;
    }

    if ($resImg) {
        switch ($contentType) {
        case 'image/jpeg':
            header("Content-Type: image/jpeg");
            imagejpeg($resImg, NULL, 80);
            break;
        case 'image/png':
            header("Content-Type: image/png");
            imagepng($resImg, NULL, 9);
            break;
        case 'image/gif':
            header("Content-Type: image/gif");
            imagegif($resImg, NULL);
            break;
        default:
            $err = "Output for Content-Type='{$headers['content-type']}' is not yet implemented";
            header("X-IMAGE-RESIZER-ERROR: $err");
            header($err, true, 501);
            unset($err);
            exit;
        }
    }
} catch (Exception $e) {
    header("Bad request", true, 400);
    header("X-IMAGE-RESIZER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if (defined("DEBUG")) echo $e->getMessage();
    exit;
}

function parseHeaders($headers, $lowerNames = true)
{
    $res = array();
    foreach ($headers as $h) {
        if (strpos($h, ": ") > 0) {
            preg_match("/^(.*)\: (.*)$/", $h, $matches);
            if ($lowerNames) {
                $matches[1] = strtolower($matches[1]);
            } 
            $res[$matches[1]] = $matches[2];
            unset($matches);
        }
    }
    return $res;
}
