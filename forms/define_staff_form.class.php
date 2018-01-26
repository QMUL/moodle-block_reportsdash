<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_define_staff_form extends moodleform
{
   function definition(){
      global $CFG,$DB;

      $mform =& $this->_form;

      $roles = $DB->get_records('role',array(),'sortorder','id,name,shortname,archetype');

      $staffroles=$DB-> get_records('block_reportsdash_staff',null,'','roleid');
      $context=context_system::instance();
      if(!empty($roles) ){
         foreach($roles as $role){
            if( $role->shortname != 'guest' ){
               if(!$role->name)
               {
//Classic Moodle team work: make role_get_name compulsory part
//way through the 2.x lifetime when it wasn't even defined before 2.4.
//Idiots.
                  if(function_exists('role_get_name'))
                  {
                     $role->name=role_get_name($role,$context);
                  }
                  else
                  {
                     $role->name=ucwords($role->shortname);
                  }
               }
               $mform->addElement( 'checkbox','staff_'.$role->shortname.'_'.$role->id, '',"&nbsp;$role->name", null, array(0,1));
            }

            if( isset($staffroles[$role->id] )) {
               $mform->setDefault('staff_'.$role->shortname.'_'.$role->id,'1');
            }
         }
      }


      $this->add_action_buttons();
//      $mform->addElement( 'submit','regcat_submit', get_string('regions_parentcatbut','block_reportsdash') );

      return $mform;
   }
}