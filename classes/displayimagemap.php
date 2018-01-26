<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 08/05/2015
 * Time: 15:46
 */
require_once("../../../config.php");

$graphid    = optional_param('graphid','',PARAM_RAW);

$cache = cache::make('block_reportsdash', 'reportdash_graphcache');
$imagecachename     = "graphImageMap".$graphid;
$graphimage         =   $cache->get($imagecachename);
echo $graphimage;
