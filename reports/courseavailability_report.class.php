<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

global  $CFG;

require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_pie.class.php");
 

class block_reportsdash_courseavailability_report extends block_reportsdash_report
{
   static function services()
   {
      return array('reportsdash_course_availability'=>'Lists all categories and the number of courses within each, recursively counting courses in sub-categories');
   }

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
   protected static function fields()
   {
      return (object)array('outputs'=>array(new Reportdash_field_info('name',PARAM_TEXT,"Category name"),
                                            new Reportdash_field_info('coursecount',PARAM_INT,'Number of courses'),
                                            new Reportdash_field_info('percent',PARAM_FLOAT,'Percentage of all courses in this category'),
),

                           'webservice'=>array(new Reportdash_field_info('depth',PARAM_INT,"Depth of category (1=top-level, 2=sub-category, 3=sub-sub-category etc.)"),
                                               new Reportdash_field_info('id',PARAM_INT,"Category id"),
                                               new Reportdash_field_info('parent',PARAM_INT,"ID of parent category"),
                                               new Reportdash_field_info('own',PARAM_INT,"Courses directly contained in this category (ie, not in sub-categories)")),

                           'inputs'=>array(new Reportdash_field_info('depthlimit',PARAM_INT,'Maximum depth of sub-category to report on. 0=only top-level.',0)));
   }


//Instance

   protected $totalcourses;

   function __construct()
   {
      parent::__construct(static::column_names(),false);
   }

   protected function setSql($usesort=true)
   {
      global $CFG;

      $pfx=$CFG->prefix;

      $optional=' and c.startdate>0';

      $sql="select cc1.id,parent,cc1.name, depth, sortorder, path, 0 as own, 0 as coursecount, r.id rid,
                      substring_index(substring_index(path, '/', 2),'/',-1) as tlc
                  FROM {$pfx}course_categories cc1
                       JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring_index(substring_index(cc1.path, '/', 2),'/',-1)
                       JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid)
                       WHERE r.visible=1 and cc1.visible=1";

      $this->sql=$sql;
   }

//Another irritating case where we need an array instead of a recordset
//se we have to re-write getdata
   protected function getData($usesort=true)
   {
      global $CFG;

      static::checkInstall();

      $this->setSql();

      $pfx=$CFG->prefix;

//No params for this report

      $allcats=$this->mydb->get_records_sql($this->sql);

      $secondarysql="SELECT id, category FROM {$pfx}course
                                     WHERE visible=1
                                     AND (startdate=0 OR startdate < :timenow)";

      $final=array();


//Count each course in a category towards the category's own total ($own)
// and it's overall total ($coursecount, ie including sub category courses)
      $tot=0;
      foreach($this->mydb->get_recordset('course',array('visible'=>1),'','id,category') as $c)
      {
         if(isset($allcats[$c->category]))
         {
            $allcats[$c->category]->coursecount++;
            $allcats[$c->category]->own++;
            $tot++;
         }
      }

      $this->totalcourses=$tot;

//Find the maximum depth of sub categories, and while we're at it
//give each category an empty array of sub-categories
      $maxdepth=1;
      foreach($allcats as $cat)
      {
         if($cat->depth>$maxdepth)
            $maxdepth=$cat->depth;

         $allcats[$cat->id]->children=array();
      }

//Starting at the max depth, find all categories at that level
//and add their coursecounts to their parent's course counts,
//also adding each category to its parent's children array.
//Then go to the parent level and repeat.
      for($d=$maxdepth;$d>1;$d--)
      {
         foreach($allcats as $cat)
         {
            if($cat->depth==$d)
            {
               $allcats[$cat->parent]->children[$cat->id]=$allcats[$cat->id];
               $allcats[$cat->parent]->coursecount+=$cat->coursecount;
            }
         }
      }

//Nearly there. Prime the semifinal array with
//the top level categories.
//Also set the percentage value
      $semifinal=$this->data=array();

      foreach($allcats as $cat)
      {
         if($cat->depth==1)
            $semifinal[$cat->id]=$allcats[$cat->id];

         $cat->percent=format_float(100*$cat->coursecount/$tot,3);
      }

      $filters=$this->filters;
      (!isset($filters->depthlimit) and $filters->depthlimit=100);
//The final result has to be a 1D array, so the
//parents and children now need to be separated
//in the correct order. This is done recursively.
      static::unravelCats($this->data,$semifinal,$filters->depthlimit+1);

      $this->records=count($this->data);

      return $this->data;
   }

//Conceptually, the first time this is called
//the "kids" are the top level categories
   protected function unravelCats(&$result,$kids,$depthlimit=100)
   {
      if($this->sort())
      {
         block_reportsdash_report::external_sort($kids,$this->sort());
      }

      foreach($kids as $category)
      {
//Add the kid to the result set
         $result[]=$category;
//Then add any of its children
         if($category->children and $category->depth < $depthlimit)
         {
            static::unravelCats($result,$category->children,$depthlimit);
         }
      }
   }

   protected function setColumnStyles()
   {
      parent::setColumnStyles();
      $this->table()->column_style_all('text-align','left');
      $this->table()->column_style('coursecount','text-align','right');
      $this->table()->column_style('percent','text-align','right');
   }

   protected function preprocessExport($rowdata)
   {
      global $CFG;

      $rowdata->name=str_repeat('  ',$rowdata->depth-1).$rowdata->name;;

      return $rowdata;
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG;

      $rowdata->name=str_repeat('-',$rowdata->depth-1).$rowdata->name;
      block_reportsdash::wrap($rowdata->name,"$CFG->wwwroot/course/category.php?id=$rowdata->id");
      return $rowdata;
   }

   protected function reportHeader($ex=false)
   {
      parent::reportHeader($ex);
      if(empty($ex))
      {
         global $OUTPUT;

         print $OUTPUT->heading($this->translate('totalcourses',$this->totalcourses),3);
      }
      else
      {
      }
   }

  function get_filter_form()
   {
       $this->filterform=new block_reportsdash_courseavailability_filter_form(null,
                                                                              array('rptname'=>$this->reportname()),
                                                                              '','',array('id'=>'rptdashfilter'));
       return $this->filterform;
   }


    static function get_report_category(){
        return 'coursesetup';
    }

    function reportGraph($dataStart = 0, $dataEnd = 0)  {
        echo $this->display_graph_label();
        $this->chart = new block_reportdash_graph_pie();

        $data = $this->data;

        usort($data, function($a, $b) {
           return $b->coursecount - $a->coursecount;
        });

       //set to display 10 top categories with highest course count
        $output_data = array_slice($data, 0, 10, true);
        $other_coursecount = 0;
        foreach($data as $key=>$value){
           // count only records that are not already in output
           if(!empty($output_data) &&!array_key_exists($key, $output_data)) {
              $other_coursecount = $other_coursecount + $value->coursecount;
           }
        }

       if ($other_coursecount !=0) {
          $others = new stdClass();
          $others->name = 'Others';
          $others->coursecount = $other_coursecount;

          $output_data[] = $others;
       }


        $this->chart->getReportData($output_data);
        $this->chart->addGraphPoints(array('coursecount'), 'data1', 'piedata');
        $this->chart->addGraphPoints(array('name'), '', '', false, true);

        $this->chart->setToolTipDescMask('%s course(s)');

        $this->chart->setGraphPosition(160, 160, 650, 320);


        $this->chart->createGraph();
        $this->chart->setLegend(310,20,false);
        $this->chart->displayGraph();
        $this->chart->createImageMap();

    }

}