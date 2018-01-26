<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 26/05/2015
 * Time: 15:44
 */



global  $CFG;

include_once($CFG->dirroot."/blocks/reportsdash/classes/graph.class.php");
require_once($CFG->dirroot."/blocks/reportsdash/classes/pchart/class/pPie.class.php");

class block_reportdash_graph_pie extends block_reportsdash_graph {

    private    $pieChart;

    function createGraph()  {

        $Settings = array("RecordImageMap"=>TRUE);

        $this->pieChart   =   new pPie($this->picture,$this->chartData,$Settings);

        $pieX   =   $this->gX;
        $pieY   =   $this->gY;
        $this->pieChart->draw2DPie($pieX,$pieY,array("Radius"=>120,"LabelStacked"=>TRUE,"WriteValues"=>TRUE,"ValueR"=>80,"ValueG"=>80,"ValueB"=>80,"RecordImageMap"=>TRUE));
    }


    function setLegend($x=null,$y=null,$horizontal=true) {

        $mode   =   (!empty($horizontal))   ? LEGEND_HORIZONTAL : LEGEND_VERTICAL  ;

        $x  =   (empty($x)) ?   $this->gX+20    :   $x;
        $y  =   (empty($y)) ?   $this->gY-20    :   $y;

        $this->pieChart->drawPieLegend($x,$y,array("Style"=>LEGEND_NOBORDER,"Mode"=>$mode,"R"=>51,"G"=>51,"B"=>51));
    }

}