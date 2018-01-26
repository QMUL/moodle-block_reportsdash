<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 22/05/2015
 * Time: 16:54
 */


require_once($CFG->dirroot."/blocks/reportsdash/classes/pchart/class/pData.class.php");
require_once($CFG->dirroot."/blocks/reportsdash/classes/pchart/class/pDraw.class.php");
require_once($CFG->dirroot."/blocks/reportsdash/classes/reportdash_pimage.class.php");


abstract class block_reportsdash_graph {

    protected     $chartData;
    protected     $reportData;
    protected     $picture;
    protected     $dStart;
    protected     $dEnd;
    protected     $gX;
    protected     $gY;
    protected     $gW;
    protected     $gH;
    protected     $useImageMap;
    protected     $titleMask;
    protected     $descMask;



    function __get($key) {
        return $this->$key;
    }

    function __construct()  {
        global      $USER, $CFG;
        $this->chartData        =   new pData();
        $this->chartData->loadPalette($CFG->dirroot."/blocks/reportsdash/classes/pchart/palettes/dashboard.color", TRUE);
        $this->reportData       =   array();
        $this->useImageMap      =   false;
        $this->reportGraphId    =   $USER->id.time();
        $this->titleMask     =   '%s';
        $this->descMask     =   '%s';
    }


    /**
     * @param $reportData
     */
    function getReportData($reportData,$dataStart=0,$dataEnd=0)    {
        $this->reportData   =   $reportData;
        $this->dStart       =   $dataStart;
        $this->dEnd         =   (empty($dataEnd) && isset($reportData->records))   ?  $reportData->records : $dataEnd  ;

    }

    /**
     * Sets the points on the graph. The data can be taken from a recordset given to getReportData or
     * the data can be provided using the $additionalData param.
     *
     * @param array $dataColumn       =   The column(s) that we will be getting the data from
     * @param string $name      =   the name of the points data in the graph
     * @param string $desc      =   Description of points
     * @param bool $colTotal    =   Will this be a total of db data or
     * @param bool $legend      =   Should this column be displayed in the legend/scale
     * @param obj  $additionalData = Allows additional non db data to be added to the graph
     */
    function addGraphPoints($dataColumns,$names='',$desc='',$colTotal=false,$legend=false,$additionalData=false,$removeEmpty=false,$staticvalues=false,$nameDesc=false,$description="description")   {

        global $CFG;

        if ($staticvalues) {
            STATIC $values = array();
        } else {
            $values = array();
        }

        if (!is_array($names))  {
            $datac  =   current($dataColumns);
            $names = array("$datac"=>$names);
        }


			if (empty($additionalData)) {

				$i=0;
				foreach($this->reportData as $data)
				{
					if($i++<$this->dStart)
					{
						continue;
					}

					foreach ($dataColumns as $dc) {

						if (!empty($colTotal)) {

							if (!isset($values[$dc])) $values[$dc] = 0;
							$values[$dc] += $data->$dc;
						} else {
							$values[] = $data->$dc;
						}
						if (empty($names[$dc]))    $names[$dc] =   time();
					}

					if($i==$this->dEnd)  break;
				}

			}   else {
				foreach($additionalData as $adata)   {
					$values[] = $adata;
				}
			}



        //we need a name just in case it hasnt been set give it a timestamp as the name
        //$name = (empty($name)) ? time() : $name;

        if ($removeEmpty)   {

            foreach($values as $l => $v) {
                if (empty($v))  {
                    unset($values[$l]);
                    unset($names[$l]);
                }
            }

        }




        if ($colTotal) {
            //assumes that the data added to values was for more than one series
            foreach ($values as $l => $v) {
                    $this->chartData->addPoints($v, $l);
            }
        } else {
            //assumes that the data added to values was for one series
            $this->chartData->addPoints($values, current($names));
        }

        if (!empty($nameDesc)) {
            $this->chartData->addPoints($names, "descriptions");
            $this->chartData->setSerieDescription("descriptions", $description);
            $this->chartData->setAbscissa("descriptions");
        }

        if (!empty($legend)) {
            $this->chartData->setAbscissa(current($names));
        }


    }

    function setGraphPosition($x,$y,$width,$height,$fontsize=10) {
        global  $CFG;

        $this->gX   =   $x;
        $this->gY   =   $y;
        $this->gW   =   $width;
        $this->gH   =   $height;

        $this->picture       =   new block_reportsdash_reportdash_pimage($width+50,$height+50,$this->chartData, TRUE);
        $this->picture->initialiseImageMap("ImageMap".$this->reportGraphId,IMAGE_MAP_STORAGE_SESSION);
        $this->picture->setGraphArea($x,$y,$width-20,$height-30);
        /* Set the default font properties */
        $this->picture->setFontProperties(array("FontName"=>$CFG->dirroot."/blocks/reportsdash/classes/pchart/fonts/calibri.ttf","FontSize"=>$fontsize,"R"=>80,"G"=>80,"B"=>80));
    }


    //sets the title of the graph
    function setGraphTitle($title='',$x=10,$y=13)  {
        if (!empty($this->picture)) {
            global $CFG;
            $this->picture->setFontProperties(array("FontName" => $CFG->dirroot . "/blocks/reportsdash/classes/pchart/fonts/verdana.ttf", "FontSize" => 10));
            $this->picture->drawText($x, $y, $title, array("R" => 255, "G" => 255, "B" => 255));
        }
    }

    function createImageMap()       {

        $cache = cache::make('block_reportsdash', 'reportdash_graphcache');
        $cache->set("graphImageMap".$this->reportGraphId,$this->picture->dumpImageMap("ImageMap".$this->reportGraphId,IMAGE_MAP_STORAGE_SESSION));

        $this->useImageMap  =   true;

    }

    //this function is expected to contain the specific implementation of the graph
    abstract protected  function createGraph();

    function displayGraph() {
        global $CFG, $PAGE;

        $cache = cache::make('block_reportsdash', 'reportdash_graphcache');
        $cache->set("graph".$this->reportGraphId,$this->picture->stroke());

        $jsmodule = array(
            'name'     	=> 'reportdash_graph_image_map',
            'fullpath' 	=> '/blocks/reportsdash/classes/graph_tooltip.js',
            'requires'  	=> array('event','dom','node','anim','io-form','transition','overlay')
        );


        $values     =   array('PictureID'=>"graph{$this->reportGraphId}","ImageMapID"=>"graphmap{$this->reportGraphId}",
                              "ImageMapURL"=>$CFG->wwwroot."/blocks/reportsdash/classes/displayimagemap.php?graphid={$this->reportGraphId}",
                                "titleTooltipMask"=>$this->titleMask,
                                "descTooltipMask"=>$this->descMask);

        $PAGE->requires->js_init_call('M.reportdash_graph_image_map.init', $values, true, $jsmodule);

        echo '<img src="'.$CFG->wwwroot.'/blocks/reportsdash/classes/displaygraph.php?graphid='.$this->reportGraphId.'" id="graph'.$this->reportGraphId.'" class="graph" >';



    }


    function setAxis($axis,$name)  {
        $this->chartData->setAxisName($axis,$name);
    }

    function setLegend($x=null,$y=null,$horizontal=true) {

        $mode   =   (!empty($horizontal))   ? LEGEND_HORIZONTAL : LEGEND_VERTICAL  ;

        $x  =   (empty($x)) ?   $this->gX+20    :   $x;
        $y  =   (empty($y)) ?   $this->gY-20    :   $y;

        $this->picture->drawLegend($x,$y,array("Style"=>LEGEND_NOBORDER,"Mode"=>$mode,"R"=>51,"G"=>51,"B"=>51));

    }

    /**
     * Allows the text displayed before and after the tooltip title text to be set.
     * When using this function remember to put %s at some point in your text or
     * the title will not be displayed.
     *
     * @param string $text
     */
    function setToolTipTitleMask($text='%s')    {
        $this->titleMask =  $text;
    }

    /**
     * Allows the text displayed before and after the tooltip description text to be set.
     * When using this function remember to put %s at some point in your text or
     * the description will not be displayed.
     *
     * @param string $text
     */
    function setToolTipDescMask($text='%s')    {
        $this->descMask =  $text;
    }



}
