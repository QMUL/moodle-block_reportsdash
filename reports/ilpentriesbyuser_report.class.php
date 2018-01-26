<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");


class block_reportsdash_ilpentriesbyuser_report extends block_reportsdash_report
{

    static function nevershow()
    {
        return true;
    }

    protected static $dependencies = array(
        'block_ilp' => 2014070100,
    );

    static function services()
    {
        return array('reportsdash_user_entries'=>'Returns user entries');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        return false;
    }

    protected $target_criteria;
    function __construct()
    {
        parent::__construct(array('username',
                                  'firstname',
                                  'Lastname',
                                  'Idnumber',
                                  'Department',
                                  'Status',
                                  'Entries',
                                  'timemodified'),true);
    }

    protected function setSql($usesort=true)
    {
        global $CFG;

        $pfx=$CFG->prefix;

        $f=$this->filters;

        $studentname = (!empty($f->studentfilter)? "and u.id='$f->studentfilter'": '');
        $coursename = (!empty($f->coursefilter)? "and c.shortname='$f->coursefilter'": '');
        $levelfilter = (!empty($f->levelfilter)&& ($f->levelfilter !=-1) ? "and cc1.id ='$f->levelfilter'": '');
        $tlcsql="substring_index(substring_index(cc1.path, '/', 2),'/',-1)";

        $fields = array(
            "id" => "u.id",
            "username" => "u.username",
            "firstname" => "u.firstname",
            "lastname" => "u.lastname",
            "idnumber" => "u.idnumber",
            "department" => "u.department",
            "status" => "sitem.name",
            "timemodified" => "ent.timemodified",
        );
        $columns = array("username", "firstname", "lastname", "idnumber", "department", "status", "timemodified");
        $headers = array("Username", "Firstname", "Surname", "ID number", "Department", "Status", "Last Update Date");

        $ilp_reports = $this->mydb->get_records_menu('block_ilp_report', array(), '', 'id,name');

        foreach ($ilp_reports as $id => $name)
        {
            $fields["ilp_report_$id"] = "COUNT(DISTINCT `$name`.id)";
            $headers[] = $name;
            $columns[] = "ilp_report_$id";
        }
        $this->setColumns($columns,$headers);

        $sqlfields = array();
        foreach ($fields as $alias => $field)
        {
            $sqlfields[] = "{$field} AS `$alias`";
        }

        //Courses which were made visible OR created in the given period.
        $sql="  SELECT DISTINCT ".implode(", ", $sqlfields)."
                   FROM {$pfx}user u
                   JOIN {$pfx}block_ilp_entry ent ON ent.user_id = u.id
                   LEFT JOIN {$pfx}user_enrolments ue ON ue.userid = ent.user_id
                   JOIN {$pfx}enrol e ON e.id = ue.enrolid
                   JOIN {$pfx}block_ilp_user_status us ON us.user_id = u.id
                   JOIN {$pfx}block_ilp_plu_sts_items sitem ON sitem.id = us.parent_id
                   LEFT JOIN {$pfx}course c ON c.id = e.courseid
                   JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                   JOIN {$pfx}block_reportsdash_regcats rc on rc.cid = $tlcsql
                   JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid and r.visible=1)
                   LEFT JOIN {$pfx}log l on (l.course=c.id and module='course' and action='show')";
        foreach ($ilp_reports as $id => $name)
        {
            $sql .= " LEFT JOIN {$pfx}block_ilp_entry `$name` ON `$name`.report_id = $id AND `$name`.user_id = u.id\n";
        }
        $sql .= "  WHERE ((l.time > :from1 and l.time < :to1) or
                   l.id is null and (ent.timemodified > :from2 and ent.timemodified < :to2))
                   $studentname
                   $coursename
                   $levelfilter
                   GROUP BY u.id, c.id, cc1.id";
        if(!empty($f->levelfilter)){
            if($f->levelfilter>0)
            {
                $filtercat=$this->mydb->get_record('course_categories',array('id'=>$f->levelfilter));
                if($filtercat->depth==1)
                {
                    $sql.=" and $tlcsql=$f->levelfilter";
                }
                else
                {
                    $bits=explode('/',$filtercat->path);
                    $tlc=$bits[1];
                    $sql.=" and $tlcsql=$tlc and (path like('%/$f->levelfilter/%') or path like('%/$f->levelfilter')) ";
                }
            }
            elseif($f->levelfilter<0)
            {
                $sql.=' and rid='.-$f->levelfilter;
            }
        }

        $this->params['from1']=$f->fromfilter;
        $this->params['from2']=$f->fromfilter;
        $this->params['to1']=$f->tofilter;
        $this->params['to2']=$f->tofilter;
        $this->sql=$sql;
    }

    protected function setColumnStyles()
    {
        parent::setColumnStyles();
        $this->table->setup();
        $this->table->column_style_all('text-align','left');
        $this->table->column_style('date','text-align','right');
        $this->table->column_style('coursename','padding-left','0');
    }

    protected function preprocessShow($rowdata)
    {
        global $CFG, $DB;

        static $tab = null;
        static $tabitems = null;

        $cf = (!empty($this->filters->coursefilter)? $this->filters->coursefilter :'');
        $course_param = $DB->get_record_sql("Select id from {course} where shortname = '$cf' ");
        $course_id = (!empty($course_param)? $course_param->id : 0);
        if ($tab == null)
        {
            $tab = $DB->get_record_sql("Select id from {block_ilp_dash_tab} where name = 'ilp_dashboard_reports_tab' ");
        }
        if ($tabitems == null)
        {
            $tabitems = $this->mydb->get_fieldset_select('block_ilp_report', 'id', null);
        }
        //$rowdata->date=userdate($rowdata->date,static::timeformat());
        block_reportsdash::wrap($rowdata->username,"$CFG->wwwroot/user/profile.php?id=$rowdata->id");
        block_reportsdash::wrap($rowdata->firstname,"$CFG->wwwroot/user/profile.php?id=$rowdata->id");
        block_reportsdash::wrap($rowdata->lastname,"$CFG->wwwroot/user/profile.php?id=$rowdata->id");
        block_reportsdash::wrap($rowdata->idnumber,"$CFG->wwwroot/user/profile.php?id=$rowdata->id");
        foreach ($tabitems as $tabitem)
        {
            $field = "ilp_report_$tabitem";
            block_reportsdash::wrap($rowdata->$field, "$CFG->wwwroot/blocks/ilp/actions/view_main.php?user_id=$rowdata->id&course_id=$course_id&selectedtab=$tab->id&tabitem=$tab->id:$tabitem");
        }

        return $rowdata;
    }

    protected function getData($usesort=true)
    {
        static::checkInstall();

        $this->setSql();

        $data=array();
        //Roll your own array as Moodle's fns assume there's an id, which there isn't
        foreach($this->mydb->get_recordset_sql($this->sql,$this->params,'','') as $r)
        {
            $r->timemodified =  date("d/m/Y H:i:s", $r->timemodified );
            $data[]=$r;
        }
        $this->records=count($data);

        if($this->sort())
        {
            static::external_sort($data,$this->sort());
        }
        $this->data=$data;

        return $this->data;
    }

    function get_filter_form()
    {
        $this->filterform= new block_reportsdash_entriesbyuser_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'ilp';
    }
}
