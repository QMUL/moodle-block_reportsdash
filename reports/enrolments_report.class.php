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



class block_reportsdash_enrolments_report extends block_reportsdash_report
{
    static function services()
    {
        return array('reportsdash_enrolments'=>'Report all courses with users enroled with a given enrolment method.');
    }

    //Define the input and output fields for this report, whether webservice or not
    //webservice is array of fields only output for the webservice
    protected static function fields()
    {
        return (object)array('outputs'=>array(new Reportdash_field_info('course',PARAM_TEXT,"Course name"),
                                              new Reportdash_field_info('parent',PARAM_TEXT,'Parent category name'),
                                              new Reportdash_field_info('students',PARAM_INT,'Number of enrolled students')),

                             'webservice'=>array(new Reportdash_field_info('cid',PARAM_INT,"Course id"),
                                                 new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category")),

                             'exports'=>array(new Reportdash_field_info('cid',PARAM_INT,'Course id'),
                                              new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category")),

                             'inputs'=>array(new Reportdash_field_info('item',PARAM_INT,"If < 0: Top-level category id; 0: all courses; >0: Second level category id"),
                                             new Reportdash_field_info('enrolfilter',PARAM_ALPHANUMEXT,"Name of enrolment method to show",'')
                             ));
    }

    //Instance

    protected $totalStudents;

    function __construct()
    {
        parent::__construct(static::column_names(),false);
        $this->totalStudents=0;
    }

    protected function setSql($usesort=true)
    {
        global $CFG;

        $pfx=$CFG->prefix;

        $f=$this->filters;

        if(!empty($f->enrolfilter))
        {
            $enrolbit="and e.enrol='$f->enrolfilter'";
        }
        else
        {
            $f->enrolfilter='';
            $enrolbit='';
        }

        $sql="SELECT ifnull(count(distinct userid),0) students, fullname as course, c.id cid,
                      cc1.id as parentid, cc1.name as parent, r.id rid
                    FROM {$pfx}course c
                    JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                    JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring_index(substring_index(cc1.path, '/', 2),'/',-1)
                    JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid)
                    JOIN {$pfx}context cx on (cx.instanceid=c.id and cx.contextlevel=:courselv)
                    LEFT JOIN ({$pfx}enrol e
                         JOIN {$pfx}user_enrolments ue on ue.enrolid=e.id
                         JOIN {$pfx}user u on u.id=ue.userid) on (e.courseid=c.id $enrolbit)
                    WHERE (u.deleted=0 or u.id is null) and r.visible=1";

        if(!empty($f->levelfilter)){
            if($f->levelfilter>0)
            {
                $filtercat=$this->mydb->get_record('course_categories',array('id'=>$f->levelfilter));
                if($filtercat->depth==1)
                {
                    $sql.=" and substring_index(substring_index(cc1.path, '/', 2),'/',-1)=$f->levelfilter";
                }
                else
                {
                    $bits=explode('/',$filtercat->path);
                    $tlc=$bits[1];
                    $sql.=" and substring_index(substring_index(cc1.path, '/', 2),'/',-1)=$tlc and (cc1.path like('%/$f->levelfilter/%') or cc1.path like('%/$f->levelfilter')) ";
                }
            }
            elseif($f->levelfilter<0)
            {
                $sql.=' and rid='.-$f->levelfilter;
            }
        }

        $this->params['courselv']=CONTEXT_COURSE;

        $sql.="      GROUP BY c.id
                    WITH ROLLUP";

        $this->sql=$sql;
    }

    protected function setColumnStyles()
    {
        parent::setColumnStyles();
        $this->table->column_style('course','text-align','left');
        $this->table->column_style('parent','text-align','left');
    }

    //We can't sort the data in the db and still have the roll up
    //so we do it here. Unfortunately, this means we can't use a
    //recordset and we have to completely override the parent
    //function because of that, so no parent:: call here
    protected function getData($usesort=true)
    {
        static::checkInstall();

        $this->setSql();

        $data=array();
        //Roll your own array as Moodle's fns assume there's an id, which there isn't
        foreach($this->mydb->get_recordset_sql($this->sql,$this->params) as $r)
        {
            $data[]=$r;
        }

        ((!empty($data) and $this->totalStudents=array_pop($data)->students) or $this->totalStudents=0);

        $this->records=count($data);

        if($this->sort())
        {
            static::external_sort($data,$this->sort());
        }

        $this->data=$data;

        return $this->data;
    }

    protected function reportFooter($ex=false)
    {
        parent::reportFooter($ex);
        if(!empty($ex))
        {
            $ex->add_data(array(get_string('total'),'',$this->totalStudents));
        }
    }

    protected function reportHeader($ex=false)
    {
        parent::reportHeader($ex);
        if(empty($ex))
        {
            global $OUTPUT,$CFG;

            $heading=$OUTPUT->heading(get_string('total').': '.$this->totalStudents,3);
            if(!empty($this->totalStudents))
            {
                $temp=-abs($this->filters->levelfilter);
                block_reportsdash::wrap($heading,"$CFG->wwwroot/blocks/reportsdash/report.php?rptname=enroldetail_report&item={$temp}&enrolfilter=&clearfilters=1");
            }
            print $heading;
        }
        else
        {
            $headers=array(static::display_name());

            if(@$f=$this->filters->levelfilter)
            {
                $headers[]=$this->mydb->get_field('course_categories','name',array('id'=>abs($f)));
            }

            $ex->output_headers($headers);
        }
    }

    protected function preprocessShow($rowdata)
    {
        global $CFG;
        (!isset($this->filters->enrolfilter) and $this->filters->enrolfilter='');
        block_reportsdash::wrap($rowdata->course,"$CFG->wwwroot/course/view.php?id=$rowdata->cid");
        block_reportsdash::wrap($rowdata->parent,"$CFG->wwwroot/course/category.php?id=$rowdata->parentid");
        block_reportsdash::wrap($rowdata->students,"$CFG->wwwroot/blocks/reportsdash/report.php?rptname=enroldetail_report&item=$rowdata->cid&enrolfilter={$this->filters->enrolfilter}&clearfilters=1");

        $tip=static::full_path($this->mydb,$rowdata->cid,false);
        $rowdata->course=html_writer::tag('span',$rowdata->course,array('title'=>$tip));

        return $rowdata;
    }

    protected function preprocessWebservice($rowdata)
    {
        if($rowdata->students==0)
        {
            return null;
        }
        return $rowdata;
    }


    function get_filter_form()
    {
        $this->filterform=new block_reportsdash_enrolments_filter_form(null, array('rptname'=>$this->reportname(),
                                                                                   'db'=>$this->mydb),
                                                                       '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'coursesetup';
    }

    function reportGraph($dataStart = 0, $dataEnd = 0)  {

        echo $this->display_graph_label();
        $this->chart = new block_reportdash_graph_pie();

        $f=$this->filters;

        $sql = "SELECT e.id, ifnull(count(distinct userid),0) students,CONCAT(UCASE(LEFT(e.enrol,1)),LCASE(SUBSTRING(e.enrol,2))) as enrolmethod
                    FROM mdl_course c
                    JOIN mdl_course_categories cc1 on cc1.id=c.category
                    JOIN mdl_block_reportsdash_regcats rc on rc.cid=substring_index(substring_index(cc1.path, '/', 2),'/',-1)
                    JOIN mdl_block_reportsdash_region r on (r.id=rc.rid)
                    JOIN mdl_context cx on (cx.instanceid=c.id and cx.contextlevel=:courselv)
                    LEFT JOIN (mdl_enrol e
                         JOIN mdl_user_enrolments ue on ue.enrolid=e.id
                         JOIN mdl_user u on u.id=ue.userid) on (e.courseid=c.id )
                    WHERE (u.deleted=0 or u.id is null) and r.visible=1  and e.enrol IS NOT NULL
                    ";


        if(!empty($f->levelfilter)){
            if($f->levelfilter>0)
            {
                $filtercat=$this->mydb->get_record('course_categories',array('id'=>$f->levelfilter));
                if($filtercat->depth==1)
                {
                    $sql.=" and substring_index(substring_index(cc1.path, '/', 2),'/',-1)=$f->levelfilter";
                }
                else
                {
                    $bits=explode('/',$filtercat->path);
                    $tlc=$bits[1];
                    $sql.=" and substring_index(substring_index(cc1.path, '/', 2),'/',-1)=$tlc and (cc1.path like('%/$f->levelfilter/%') or cc1.path like('%/$f->levelfilter')) ";
                }
            }
            elseif($f->levelfilter<0)
            {
                $sql.=' and rid='.-$f->levelfilter;
            }
        }
        $sql .=" GROUP BY  enrolmethod";

        $this->params['courselv']=CONTEXT_COURSE;

        $data = $this->mydb->get_records_sql($sql,$this->params);

        $graphid = time();

        $this->chart->getReportData($data);
        $this->chart->addGraphPoints(array('students'), 'data1', 'piedata');
        $this->chart->addGraphPoints(array('enrolmethod'), '', '', false, true);

        $this->chart->setGraphPosition(160, 180, 320, 320);
        $this->chart->createGraph();
        $this->chart->setLegend(20,20,true);


        $this->chart->setToolTipDescMask('%s of enrolments');

        $this->chart->displayGraph();
        $this->chart->createImageMap();



    }


}