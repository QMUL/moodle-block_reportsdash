<?php
/**
 * Block class for the Reports Dashboard
 *
 * @copyright &copy; 2013 University of London Computer Centre
 * @author http://www.ulcc.ac.uk, http://moodle.ulcc.ac.uk
 * @author Thomas Worthington
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ReportsDash
 * @version 1.0
 */

require_once(__DIR__.'/../../config.php');
global $CFG;

require_once("$CFG->dirroot/blocks/moodleblock.class.php");
require_once("$CFG->dirroot/blocks/reportsdash/locallib.php");

class block_reportsdash extends block_base {

//Various utility functions used in this block

   static function version($v)
          {
             $version=explode(' ',$v);
             return explode('.',$version[0]);
          }

   static function versionAtLeast($vmin)
          {
             global $CFG;
             $v1=static::version($vmin);
             $v2=static::version($CFG->release);

             return ($v2[0]>$v1[0] or
                     ($v2[0]==$v1[0] and $v2[1]>$v1[1]) or
                     ($v2[1]==$v1[1] and $v2[2]>=$v1[2]));
          }

   static function versionUnder($vmax)
          {
             return !static::versionAtLeast($vmax);
          }

//A general DB query with one or more straight joins. The on clause is
//defined by the second and later tables. Tables are used as indexes
//eg: block_reportsdash::get_join_set(array('block_reportsdash_regcats rc'=>'',
//                                          'course_categories cc'=>'cc.id=rc.cid'));
   static function get_join_set($tables,$where='1',$fields='*',$orderby='')
          {
             global $DB,$CFG;
             $sql='';
             $pfx=$CFG->prefix;

             foreach($tables as $table=>$join)
             {
                if(empty($sql))
                {
                   $sql="select $fields from {$pfx}$table ";
                }
                else
                {
                   $sql.="JOIN {$pfx}$table on $join\n";
                }
             }

             $sql.=" WHERE $where\n";
             if($orderby)
                $sql.="ORDER BY $orderby";

             return $DB->get_recordset_sql($sql);
          }

//As above but result is an array, and optionally indexed by a particular field $idx
   static function get_join($tables,$idx='id',$where='1',$fields='*',$orderby='')
          {
             if($idx)
             {
                return static::reindex(static::get_join_set($tables,$where,$fields,$orderby),$idx);
             }
             return static::rstoarray(static::get_join_set($tables,$where,$fields,$orderby));
          }

//Is there anything in this block that the user can view?
   static function can_view_any()
          {
             global $USER;

             $context=context_system::instance();

             foreach(static::find_reports() as $path=>$report)
             {
                if($report::can_view_report($USER->id))
                {
                   return true;
                }
             }

             return false;
          }

// Wrap some text in a link tag
// No return - alters the actual text object
   static function wrap(&$text,$url,$attr=array('target'=>'_blank'))
          {
             $text=html_writer::link($url,$text,$attr);
          }

   static function detect($array,$function,$e=null)
          {
             foreach($array as $item)
             {
                if($function($item))
                {
                   return $item;
                }
             }

             if(isset($e))
             {
                throw new Exception($e);
             }

             return null;
          }

   static function instant_message($from,$to,$subject,$messagetext,$component='moodle',$name='instantmessage')
          {
             global $CFG, $DB, $SITE;
             include_once($CFG->libdir.'/messagelib.php');

             (is_numeric($from) and $from=$DB->get_record('user',array('id'=>$from)));
             (is_numeric($to) and $to=$DB->get_record('user',array('id'=>$to)));

             if($from and $to and trim($subject) and trim($messagetext) and $component and $name)
             {
                $data=new stdClass;
                $data->name=$name;
                $data->component=$component;
                $data->userfrom=$from;
                $data->userto=$to;
                $data->subject=trim($subject);
                $data->fullmessagehtml=str_replace("\n",'<br>',$messagetext);
                $data->fullmessage=strip_tags($messagetext);
                $data->fullmessageformat=FORMAT_HTML;
                $data->smallmessage=strip_tags($messagetext);

                $s = new stdClass();
                $s->sitename = $SITE->shortname;
                $s->url = $CFG->wwwroot.'/message/index.php?user='.$to->id.'&id='.$from->id;

                if (!empty($data->fullmessage)) {
                   $emailtagline = get_string_manager()->get_string('emailtagline', 'message', $s, $to->lang);
                   $data->fullmessage .= "\n\n---------------------------------------------------------------------\n".$emailtagline;
                }

                if (!empty($data->fullmessagehtml)) {
                   $s->url="<a href='$s->url'>{$s->url}</a>";
                   $emailtagline = get_string_manager()->get_string('emailtagline', 'message', $s, $to->lang);
                   $data->fullmessagehtml .= "<br /><br />---------------------------------------------------------------------<br />".$emailtagline;
                }

                $data->timecreated     = time();

                return message_send($data);
             }
             else
             {
                return 0;
             }

          }

// Send a plain text email directly to an address or a set of addresses.

   static function quickmail($userfrom,$addressto,$subject,$message)
          {
             global $DB,$CFG;

             if(!empty($CFG->noemailever))
             {
                return true; //fake it.
             }

             if(!$mail=get_mailer())
             {
                return false;
             }

             (is_numeric($userfrom) and $userfrom=$DB->get_record('user',array('id'=>$userfrom)));

             $mail->From=$userfrom->email;
             $mail->Sender=$userfrom->email;

             $mail->FromName="$userfrom->firstname $userfrom->lastname";
             if(!is_array($addressto))
             {
                $addressto=array($addressto);
             }

             foreach($addressto as $address)
             {
                if(is_numeric($address))
                {
                   $t=$DB->get_record('user',array('id'=>$address));
                   $mail->AddAddress($t->email);
                }
                else
                {
                   $mail->AddAddress($address);
                }
             }

             $mail->Subject=$subject;

             $mail->Body=strip_tags(str_replace("\n",'<br>',$message));

             return $mail->send();
          }

   //Each occurrance of newidx should be unique, or it will be overwritten
   static function reindex($data,$newidx)
          {
             $result=array();

             foreach($data as $item)
             {
                $result[$item->$newidx]=$item;
             }

             return $result;
          }

// Faster than using above fn to convert a recordset
   static function rstoarray($rs)
          {
             $result=array();

             foreach($rs as $item)
             {
                $result[]=$item;
             }
             return $result;
          }


//Each value of newidx may occur more than once
   static function reindex2d($data,$newidx)
          {
             $result=array();

             foreach($data as $idx=>$item)
             {
                $result[$item->$newidx][$idx]=$item;
             }

             return $result;
          }

   static function sort_objects(&$result,$sorting=null)
          {
             global $DB;

             if(!$sorting)
                return true;

             reset($sorting);

             $sort1=key($sorting);
             if($sort1=='displaytime')
                $sort1='time';

             $direction1=array_shift($sorting);

             $sort2=key($sorting);
             if($sort2=='displaytime')
                $sort2='time';

             $direction2=array_shift($sorting);

             if(!$sort2)
             {
                return usort($result,function($a,$b) use($sort1,$direction1){return block_reportsdash::cmp($a->$sort1,$b->$sort1,$direction1);});
             }

             return usort($result,function($a,$b) use($sort1,$sort2,$direction1,$direction2)
                          {
                             if($a->$sort1==$b->$sort1)
                             {
                                return block_reportsdash::cmp($a->$sort2,$b->$sort2,$direction2);
                             }
                             else
                             {
                                return block_reportsdash::cmp($a->$sort1,$b->$sort1,$direction1);
                             }
                          }
                );
          }

   static function cmp($a,$b,$asc=SORT_ASC)
          {
             if($a==$b)
                return 0;

             if(is_numeric($a))
             {
                if($asc==SORT_ASC)
                {
                   return ($a<$b)? -1 : 1 ;
                }
                else
                {
                   return ($a>$b)? -1 : 1 ;
                }
             }
             else
             {
                if($asc==SORT_ASC)
                {
                   return strcasecmp($a,$b);
                }
                else
                {
                   return strcasecmp($b,$a);
                }
             }
          }

    static function get_dashboard()
    {
        global $CFG;

        $L='block_reportsdash'; // Language

        echo "<div class='accordion'>";
        echo "<ul class='accordion_wrapper'>";

        $all_reports = static::find_reports();
        $categorised_reports = static::categorise_reports($all_reports);
        ksort($categorised_reports); // sort categories in alphabetical order

        if (!empty($categorised_reports)) {
            foreach ($categorised_reports as $cat => $rep) {
                $cat_name = get_string($cat, $L);
                $cat_desc = get_string("{$cat}_desc", $L);
                echo "<li class = 'accordion_item category-headline-$cat'><input type='checkbox' checked='checked'><span class='entypo-up-open-big'></span>";
                echo "<h2>$cat_name</h2>";
                echo "<p class ='c-abstract'>$cat_desc</p>";
                echo " <div class='panel'><ul class='horizontal-list'>";
                foreach ($rep as $path => $classname) {
                    include_once($path);
                    echo "<li><a href='{$CFG->wwwroot}/blocks/reportsdash/report.php?rptname=".$classname::reportname()."'><h3>
                          <div class='headline'>".get_string($classname::reportname(),$L)."</div></h3>
                          <p class='abstract'>".$classname::display_report_description()."</p></a></li>";
                }
                echo "</ul>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            return get_string('noreports',$L);
        }
    }

//Return an array of report class names keyed by their includepath
   static function find_reports()
          {
             global $CFG;

             $result=array();

             $dirname = "$CFG->dirroot/blocks/reportsdash/reports/";

             if(PHP_VERSION_ID<50400)
             {
                $r=scandir($dirname);
             }
             else
             {
                $r=scandir($dirname,SCANDIR_SORT_NONE); //Slow enough without an extra sort
             }

             if($r===FALSE)
                return $result;

             foreach($r as $filename)
             {
                if (preg_match('/^([^\.]+_report).class.php$/', $filename, $matches)) {
                   $classname = "block_reportsdash_{$matches[1]}";
                   $result[$dirname.$filename]=$classname;
                }
             }

             return $result;
          }


    static function categorise_reports($reports){
        global $USER;
        $categories = array();

        foreach ($reports as $path=>$classname){
            // check if a user has a capability to view the report
            if (!$classname::nevershow() and $classname::can_view_report($USER->id)) {
                $category_name = $classname::get_report_category();
                $category_name = (empty($category_name)) ? 'uncategorised' : $category_name;
                $categories[$category_name][$path] = $reports[$path];
            }
        }

        return $categories;
    }


   static function get_regions($where=array())
          {
             global $DB;
             return $DB->get_records('block_reportsdash_region', $where,'name');
          }

   static function get_regions_category_list(){
             global $DB;

             if($r=$DB->get_recordset_sql("select r.name as regionname,r.id as rid, cid as cid, cc.*
                                             from {course_categories} cc
                                             join {block_reportsdash_regcats} rc on (cc.id=rc.cid)
                                             join {block_reportsdash_region} r on r.id=rc.rid
                                         where r.visible=1
                                         order by regionname,cc.sortorder"))
             {
                return $r;
             }

             return array();
          }


/**
 * @Name: get_regions_names
 * @Description: Calls the get_regions function to return only the name ( not the shortname ) of the region
 *               may be need for form option items for example.
 * @return array
 *
 */
   static function get_regions_names( $where = array() ){

             $regions = static::get_regions($where);

             $reg_names = array();

             foreach($regions as $reg){
                $reg_names[]= $reg->name;
             }

             return $reg_names;
          }

//// Instance

   function init()
   {
      global $USER;
      $this->title                      = get_string('block_title', 'block_reportsdash');
      $this->reportdash_url             = new moodle_url('/blocks/reportsdash/index.php?sesskey='.$USER->sesskey, array('id'=>0,'inpopup'=>1) );
      $this->reportdash_settings_url    = new moodle_url('/blocks/reportsdash/report_settings.php?sesskey='.$USER->sesskey );
   }

   function get_content()
   {
      global $DB, $USER;
      $context  = context_system::instance();

      if ($this->content !== NULL) {
         return $this->content;
      }

      if (empty($this->instance)) {
         $this->content = '';
         return $this->content;
      }

      $this->content = new stdClass;

      $this->content->text = ''; //Keep Naseem happy

      if(static::can_view_any())
      {
          $this->content->text .= '<ul>';
          $this->content->text .= '<li>';
          $this->content->text .= html_writer::tag('a', get_string( 'dash_link', 'block_reportsdash' ), array( 'href'=>$this->reportdash_url, 'class'=>'block-link') );
         $this->content->text .= '</li>';
      }

      if(has_capability('block/reportsdash:configure_reportsdash', $context))
      {
          if(empty($this->content->text))
          {
              $this->content->text .= '<ul>';
          }

          $this->content->text .= '<li>';
          $this->content->text .= html_writer::tag('a', strip_tags(get_string( 'controlpanellink', 'block_reportsdash' )), array( 'href'=>$this->reportdash_settings_url, 'class'=>'block-link') );
          $this->content->text .= '</li>';
      }

      if(!empty($this->content->text))
      {
          $this->content->text .= '</ul>';
      }

      $this->content->footer = '';
   }

   function cron()
   {
      mtrace('Checking for reports');
//If a report throws up, make sure the error is caught so that we can clear its semaphore

      $oldhandler=set_error_handler(function($errno,$emessage)
      {
          if(!(error_reporting() & $errno))
          {
              return;
          }
          throw new Exception($emessage,$errno);
      });

      foreach(static::find_reports() as $path=>$report)
      {
         mtrace("Found $report");

         if($report::has_cron())
         {
            mtrace("--Has cron");

            if(get_config('block_reportsdash',"block_reportsdash_cron_$report")!=1)
            {
               try
               {
                  set_config("block_reportsdash_cron_$report",1,'block_reportsdash');
                  $report::cron();
               }
               catch(Exception $e)
               {
                  set_config("block_reportsdash_cron_$report",0,'block_reportsdash');
                  mtrace('--FAILED:');
                  mtrace($e->getMessage());
               }
               set_config("block_reportsdash_cron_$report",0,'block_reportsdash');
            }
            else
            {
               mtrace("--Previous cron still running");
            }
         }
         mtrace("Done $report");
      }

      set_error_handler($oldhandler); //Clear our handler
      mtrace("End of reportdash cron");
      return true;
   }

   function has_config()
   {
      return true;
   }
}
