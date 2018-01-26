<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_define_courseleader_form extends moodleform
{
   function definition(){
      global $CFG,$DB;

      $mform =& $this->_form;

      $roles = $DB->get_records('role',array(),'sortorder','id,name,shortname,archetype');

      $leaderrole=get_config('block_reportsdash','leaderroles');

      $radioarray=array();

      $context=context_system::instance();
      
      if(!empty($roles) ){
         foreach($roles as $role){
            if( $role->shortname != 'guest' ){
               if(!$role->name)
               {
//Classic Moodle Team work: make role_get_name compulsory part
//way through the 2.x lifetime AND change how it works at the
//same time.
                  if(function_exists('role_get_name'))
                  {
                     $role->name=role_get_name($role,$context);
                  }
                  else
                  {
                     $role->name=ucwords($role->shortname);
                  }
               }
               $mform->addElement( 'radio','leaderrole', '',"&nbsp;$role->name", $role->id);
            }
         }
         $mform->addElement( 'radio','leaderrole', '',"&nbsp;None", 0);
         $mform->setDefault('leaderrole',$leaderrole);
      }


      $this->add_action_buttons();
//      $mform->addElement( 'submit','regcat_submit', get_string('regions_parentcatbut','block_reportsdash') );

      return $mform;
   }
}