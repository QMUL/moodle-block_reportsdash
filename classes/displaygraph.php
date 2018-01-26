<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 08/05/2015
 * Time: 15:46
 */
require_once("../../../config.php");

$graphid    = optional_param('graphid','',PARAM_RAW);


//header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
//header("Cache-Control: no-cache");
//header("Pragma: no-cache");
header('Content-type: image/png');

$cache = cache::make('block_reportsdash', 'reportdash_graphcache');
$imagecachename =  "graph".$graphid  ;
$graphimage  =   $cache->get($imagecachename);
echo $graphimage;
