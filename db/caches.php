<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 08/05/2015
 * Time: 11:49
 */

defined('MOODLE_INTERNAL') || die;

$definitions = array(
    'reportdash_graphcache' => array(
        'mode' => cache_store::MODE_SESSION,
        'persistent'=>true
    )
);

