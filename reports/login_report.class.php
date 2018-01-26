<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

class block_reportsdash_login_report extends block_reportsdash_report
{

    const DEFAULT_PAGE_SIZE=50;

    static function services()
    {
        return array('reportsdash_all_logins_since'=>'Returns users who have logged in since (or not since) a particular timestamp');
    }

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*365)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('fullname',PARAM_TEXT,"User's name"),
        new Reportdash_field_info('lasttime',PARAM_INT,'Last login time')),

        'webservice'=>array(new Reportdash_field_info('uid',PARAM_INT,"User's Moodle id")),

        'inputs'=>array(new Reportdash_field_info('fromfilter',PARAM_INT,'Unix timestamp',$defaulttime),
        new Reportdash_field_info('onlynever',PARAM_INT,'1=Report users NOT logged in since stamp',0),
        new Reportdash_field_info('usergroup',PARAM_INT,'0=all users, 1=non-staff, 2=staff only',0)));
    }

    protected static function timeformat()
    {
        return get_string('strftimedatetime','langconfig');
    }

///Instance starts here

    protected $canviewdetail;

    function __construct()
    {
        global $SESSION, $USER;
        parent::__construct(static::column_names());

        $this->canviewdetail=block_reportsdash_useractivity_report::can_view_report($USER->id);

//Clear sub-report's filters
        unset($SESSION->reportsdashfilters['useractivity_report']);
    }

    function setSql($usesort=true)
    {
        global $CFG;

        $pfx=$CFG->prefix;

        $this->sql="select u.id as uid, concat(u.firstname,' ', u.lastname) as fullname, u.deleted, u.currentlogin as lasttime".
            " FROM {$pfx}user u ";

        $staffonly=$nonstaff=false;

        if(!empty($this->filters->usergroup))
            {
                $nonstaff=$this->filters->usergroup==1;
                $staffonly=!$nonstaff;
            }

        if($staffonly or $nonstaff)
            {
                $this->sql.=" LEFT JOIN (select userid, count(*) c
                            FROM mdl_role_assignments ra join mdl_block_reportsdash_staff rs on rs.roleid=ra.roleid
                           GROUP BY ra.userid ) ut
                              ON ut.userid=u.id ";
            }

        if(!empty($this->filters->onlynever))
            {
                $this->sql.=" where currentlogin < :cutoff";
                $this->params['cutoff']=$this->filters->fromfilter;
            }
        else
            {
                $this->sql.=" where currentlogin >= :cutoff";
                $this->params['cutoff']=$this->filters->fromfilter;
            }

        $this->sql.=' and deleted=:deleted';
        $this->params['deleted']=0;

        if($nonstaff)
            {
                $this->sql.=' and ut.userid is NULL';
                if($CFG->siteadmins)
                    $this->sql.=" and u.id not in ($CFG->siteadmins)";
            }
        elseif($staffonly)
            {
                $this->sql.=' and (ut.userid is NOT NULL';

                if($CFG->siteadmins)
                    $this->sql.=" or u.id in ($CFG->siteadmins)";

                $this->sql.=')';
            }


        if($usesort and $this->sort())
            {
                $this->sql.=' order by '.$this->sort();
            }
    }

    protected function defaultFilters()
    {
        $filters=new stdClass;
        foreach(static::fields()->inputs as $field)
            {
                $filters->{$field->name()}=$field->value();
            }
        return $filters;
    }

    protected function setColumnStyles()
    {
        parent::setColumnStyles();

        $this->table->column_style('fullname','text-align','left');
    }

    protected function exportname()
    {
        $t=$this->reportname.'-'.userdate($this->filters->fromfilter,get_string('strftimedaydate','langconfig'));
        if(isset($this->filters->onlynever))
            {
                $t.='-'.get_string('non-attended','block_reportsdash');
            }
        return $t;
    }

    protected function preprocessExport($rowdata)
    {
        $n=$rowdata->lasttime;

        if($n)
            {
                $rowdata->lasttime=userdate($rowdata->lasttime,static::timeformat());
            }
        else
            {
                $rowdata->lasttime=get_string('never');
            }
        return $rowdata;
    }

    protected function preprocessShow($rowdata)
    {
        global $CFG;

        $n=$rowdata->lasttime;

        if($n)
            {
                $rowdata->lasttime=userdate($n,static::timeformat());
                if($this->canviewdetail)
                    block_reportsdash::wrap($rowdata->lasttime,"$CFG->wwwroot/blocks/reportsdash/report.php?uid=$rowdata->uid&rptname=useractivity_report&timefrom={$this->filters->fromfilter}");
            }
        else
            {
                $rowdata->lasttime=get_string('never');
            }

        if(!$rowdata->deleted)
            {
                block_reportsdash::wrap($rowdata->fullname,"$CFG->wwwroot/user/view.php?id=$rowdata->uid");
                block_reportsdash::wrap($rowdata->lastname,"$CFG->wwwroot/user/view.php?id=$rowdata->uid");
            }


        return $rowdata;
    }

    function get_filter_form()
    {
        $this->filterform=new block_reportsdash_login_filter_form(null, array('rptname'=>$this->reportname,
        'filters'=>$this->filters),
        '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'sitestatistics';
    }
}
