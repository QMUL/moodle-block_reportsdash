<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 26/05/2015
 * Time: 16:02
 */


global  $CFG;

include_once($CFG->dirroot."/blocks/reportsdash/classes/graph.class.php");


class block_reportdash_graph_line extends  block_reportsdash_graph {

    function createGraph()  {
        $this->picture->drawScale();
        $this->picture->drawLineChart();
    }

}