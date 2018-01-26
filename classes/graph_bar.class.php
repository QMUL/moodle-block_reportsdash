<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 26/05/2015
 * Time: 16:06
 */

global  $CFG;

include_once($CFG->dirroot."/blocks/reportsdash/classes/graph.class.php");


class block_reportdash_graph_bar extends  block_reportsdash_graph {

    function createGraph()  {
        $this->picture->drawScale(array("CycleBackground"=>TRUE,"InnerTickWidth"=>5,"RemoveXAxis"=>TRUE,"Mode"=>SCALE_MODE_START0,"Factors"=>array(10000)));
        $this->picture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>255,"G"=>255,"B"=>255,"Alpha"=>10));
        $this->picture->drawBarChart(array("DisplayValues"=>TRUE,"DisplayColor"=>DISPLAY_AUTO,"Surrounding"=>60,"RecordImageMap"=>TRUE,"BarWidth"=>30));

    }

}