<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
global $CFG;
require_once("$CFG->dirroot/blocks/reportsdash/locallib.php");

//We need to make sure external_update_descriptions is in memory, but
//that automaticallty includes this file. Prevent infinite loop

$block_reportsdash_flag=true;
foreach(debug_backtrace() as $step)
{
//Have we been included via upgradelib?
   if(basename($step['file'])=='upgradelib.php')
   {
      $block_reportsdash_flag=false;
      break;
   }
}
if($block_reportsdash_flag)
{

   require_once("$CFG->libdir/upgradelib.php");

   external_update_descriptions('block_reportsdash');
}

$functions=array();
foreach(block_reportsdash::find_reports() as $path=>$name)
{
   if(method_exists($name,'services'))
   {
      $nservice=0;
      foreach($name::services() as $servicename=>$text)
      {
         $a=array('classname'=>$name);
         if($nservice ==0 )
         {
            $a['methodname']='webservice';
         }
         else
         {
            $a['methodname']="$webservice$nservice";
         }

         $a['classpath']='blocks/reportsdash/locallib.php';
         $a['description']=$text;
         $a['type']='read';

         $functions[$servicename]=$a;
         $nservice++;
      }
   }
}

$services = array(
   'reportsdash_export'=>array(
      'functions'=>array_keys($functions),
      'requiredcapability'=>'',
      'restrictedusers'=>0,
      'enabled'=>1))
;


//print_object($functions);