<?php

/**
 * Utility classes/autoloader for Reports Dashboard
 *
 * @copyright &copy; 2013 University of London Computer Centre
 * @author http://www.ulcc.ac.uk, http://moodle.ulcc.ac.uk
 * @author Thomas Worthington
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ReportsDash
 * @version 1.0
 */

require_once(__DIR__.'/../../config.php');
require_once("$CFG->libdir/externallib.php");

//These have to be values that never become term ids
define('YEAR_START',0);
define('YEAR_END',-1);

function block_reportsdash_autoloader($class)
{
    // This needs to bring CFG into scope for the included files,
    // even though its not used directly here.
    global $CFG;

    if($class=='block_reportsdash')
    {
        include(__DIR__.'/block_reportsdash.php');
    }
    elseif($class=='moodleform')
    {
        include("$CFG->libdir/formslib.php");
    }
    elseif(substr($class,0,18)=='block_reportsdash_')
    {
        if(substr($class,-5)=='_form')
        {
            include(__DIR__.'/forms/'.substr($class,18).'.class.php');
        }
        else
        {
            include(__DIR__.'/reports/'.substr($class,18).'.class.php');
        }
    }
}

spl_autoload_register('block_reportsdash_autoloader');

// A slightly more sophisticated language handling object.
// On creation, takes a list of language modules (to which it adds 'moodle')
// and a list of strings to prefetch. Searches for the given strings
// in the language modules.

//eg: $W=new String_Container(array('heading1','heading2'...),array('mylang1'...));
//     print "<h1>$W->heading1</h1><h2>$W->heading2</h2>";
//etc.

class String_Container
{

    //Get the language strings for the given array of strings,
    //handing back an object which can be used as an accessor:

    /// Instance

    protected $modules;

    function __construct($strings,$modules)
    {

        global $CFG;

        if(!is_array($modules))
        {
            $modules=array($modules);
        }

        $this->modules=$modules;

        foreach($strings as $item)
        {
            $this->fetch($item);
        }
    }

    protected function fetch($item,$dropin='')
    {
        global $CFG;

        if(isset($this->$item))
            return $this->$item;

        //Look in all the given language modules
        //Stop Moodle complaining about missing lang strings in these

        $olddebug=$CFG->debug;
        $newdebug=$olddebug & ~E_NOTICE;

        $CFG->debug = $newdebug;

        foreach($this->modules as $module)
        {
            if(substr(@$translation=get_string($item,$module,$dropin),0,4+strlen($item))!="[[$item]]")
            {
                $this->$item=$translation;
                return $translation;
            }
        }

        //Complain if we still can't find it.
        $CFG->debug=$olddebug;

        return $this->$item=get_string($item,'moodle',$dropin);
    }

    //Take an array of (or a single) strings and return an array of
    //translated strings (or a single string).
    function translate($strings,$dropin='')
    {
        if(is_array($strings))
        {
            $r=array();
            foreach($strings as $string)
            {
                @$t=$this->fetch($string,$dropin);
                $r[]=$t;
            }
            return $r;
        }
        else
        {
            return $this->fetch($strings,$dropin);
        }
    }

    function translate_and_strip($strings)
    {
        if(is_array($strings))
        {
            $r=array();
            foreach($strings as $string)
            {
                $r[]=strip_tags($this->translate($string));
            }
            return $r;
        }
        else
        {
            return array(strip_tags($this->translate($strings)));
        }
    }

    //Used mainly for headings taken from the DB for which we have no origin module
    function ucwords($strings,$lookup=false)
    {
        if(is_array($strings))
        {
            $r=array();
            foreach($strings as $string)
            {
                if($lookup)
                {
                    $r[]=ucwords($this->translate($string));
                }
                else
                {
                    if(isset($this->$string))
                    {
                        $r[]=ucwords($this->$string);
                    }
                    else
                    {
                        $r[]=ucwords($string);
                    }
                }
            }
            return $r;
        }
        else
        {
            return array(ucwords($this->translate($strings)));
        }
    }
}

class Reportdash_field_info
{
    protected $name;
    protected $type;
    protected $description;
    protected $default;
    protected $validation;
    protected $value;
    protected $suppress; //Do not show on webservice

    function __construct($name,$type,$description='',$default=null,$validation=false,$suppress=false)
    {
        if(!$description)
        {
            $description="A field of type $type";
        }

        $this->name=$name;
        $this->type=$type;
        $this->description=$description;
        $this->default=$default;
        $this->value=$default;
        $this->validation=$validation;
        $this->suppress=$suppress;
    }

    function name()
    {
        return $this->name;
    }

    function description()
    {
        return $this->description;
    }

    function suppress()
    {
        return $this->suppress;
    }

    function type()
    {
        return $this->type;
    }

    function required()
    {
        return $this->default===null;
    }

    function value()
    {
        return $this->value;
    }

    function valid($test=null)
    {
        if(!isset($test))
        {
            if(isset($this->value))
            {
                $test=$this->value;
            }
            else
            {
                return false;
            }
        }

        $t2=clean_param($test,$this->type);

        if($t2!==$test)
            return false;

        if($this->validation!==false)
        {
            return preg_match($this->validation,$test);
        }
        return true;
    }

    function set($value=null)
    {
        if(!isset($value))
        {
            $value=$this->default;
        }

        if(!$this->valid($value))
        {
            $v=print_r($value,true);
            throw new Exception("Value $v out of range");
        }

        $this->value=$value;

        return $value;
    }
}



function mylog($text,$append=true)
{
    global $CFG;
    file_put_contents("$CFG->dataroot/log.log",$text,FILE_APPEND*$append);
}

/**
 * @Name: update_categories
 * @Description: event function that gets called when a course is created or updated
 * to keep the centers list up to date.
 * @param $eventid
 * @return bool
 *
 */

function update_categories($eventid=null){
    global $DB;

    // get unassigned id in regions table
    $unassigned = $DB->get_record('block_reportsdash_region',array('name'=>'Unassigned'),'id');
    $category = $DB->get_record('course_categories',array('parent'=>0, 'id'=>$eventid),'id,name');
    if($eventid  ){

        // check category exists
        $cat = $DB->get_record('block_reportsdash_regcats',array('cid'=>$eventid));

        // add new category or move sub to top
        if(empty($cat) && $category != false){

            $dataobject = new stdClass();
            $dataobject->cid = $eventid;
            $dataobject->rid = $unassigned->id;

            $DB->insert_record('block_reportsdash_regcats', $dataobject);
        } else {    // update record
            // move top to sub (delete from block_reportsdash_regcats )
            $id = $DB->get_field('block_reportsdash_regcats', 'id', array('cid'=>$eventid));
            $DB->delete_records('block_reportsdash_regcats', array('id'=>$id));
        }

    }else{

        $categories = $DB->get_records('course_categories',array('parent'=>0,'visible'=>1),'id,name');

        if(!empty($categories)){

            foreach($categories as $cat){

                $regcat = $DB->get_record('block_reportsdash_regcats',array('cid'=>$cat->id),'id');

                if(!$regcat){

                    $dataobject = new stdClass();
                    $dataobject->cid = $cat->id;
                    $dataobject->rid = $unassigned->id;

                    $DB->insert_record('block_reportsdash_regcats', $dataobject);
                }

            }
        }
    }
}

/**
 * @param $cid
 * @return bool
 */
function update_deleted_cids($eventid=null){
    global $DB;

    $sql = 'SELECT cc.id as categoryid, cc.parent
            FROM {course_categories} cc
            WHERE id in (SELECT cid FROM {block_reportsdash_regcats})';

    $regcats = $DB->get_records_sql($sql);

    if(!empty($eventid)){

        $id = $DB->get_field('block_reportsdash_regcats', 'id', array('cid'=>$eventid));

        return $DB->delete_records('block_reportsdash_regcats', array('id'=>$id));
    }else{

        return rd_update_delcats($regcats);
    }

}

/**
 * @param $regcats
 * @return bool
 */
function rd_update_delcats($regcats){
    global $DB;

    if( !empty( $regcats ) ){

        foreach( $regcats as $cat ){

            if( $cat->parent > 0 ){

                $id = $DB->get_field('block_reportsdash_regcats','id',array('cid'=>$cat->categoryid));

                return $DB->delete_records('block_reportsdash_regcats', array('id'=>$id));
            }
        }
    }
}