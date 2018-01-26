<?php
/**
 * Version details
 *
 * @package reportsdash
 * @copyright 2015 ULCC, University of London
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//Default filter block
class block_reportsdash_coursetotalgrades_filter_form extends moodleform{

    function definition()    {
        global $DB;
        $imports = (object)$this->_customdata;
        $L = 'block_reportsdash'; //Default language file

        $mform =& $this->_form;
        $mform->addElement('hidden','rptname',$imports->rptname);

        //department
        $departments = $DB->get_recordset_sql("SELECT DISTINCT(department) FROM {user} ORDER BY department");
        $selectdepartment = $mform->addElement('select','departmentfilter',get_string('department',$L));
        $selectdepartment->addOption(get_string('department',$L),'all',array());
        foreach($departments as $department=>$value){
            $selectdepartment->addOption($department,$value->department,array());
        }

        //course category
        $categories = $DB->get_recordset_sql("SELECT id, name FROM {course_categories} ORDER BY name");
        $selectcategory = $mform->addElement('select','categoryfilter',get_string('coursecategory',$L));
        $selectcategory->addOption(get_string('coursecategory',$L),'',array());
        foreach($categories as $category){
            $selectcategory->addOption($category->name,$category->id,array());
        }

        //course
        $courses = $DB->get_recordset_sql("SELECT id, fullname FROM {course} ORDER BY fullname");
        $selectcourse = $mform->addElement('select','coursefilter',get_string('allcourses',$L));
        $selectcourse->addOption(get_string('allcourses',$L),'',array());
        foreach($courses as $course){
            $selectcourse->addOption($course->fullname,$course->id,array());
        }
        
        //user
        $selectuser = $mform->addElement('select','userfilter',get_string('user',$L));
        $selectuser->addOption(get_string('user',$L),'',array());
        foreach($DB->get_recordset_sql("SELECT id, concat(firstname, ' ', lastname) as name FROM {user} where confirmed and not suspended and not deleted ORDER BY lastname") as $user){
            $selectuser->addOption($user->name,$user->id,array());
        }
        
        //grade from
        $selectgradefrom = $mform->addElement('select','gradefromfilter',get_string('gradefrom',$L));
        $selectgradefrom->addOption(get_string('gradefrom',$L),'',array());
        for ($i=0;$i<=100;$i++){
            $selectgradefrom->addOption($i,$i,array());
        }

        //grade to
        $selectgradeto = $mform->addElement('select','gradetofilter',get_string('gradeto',$L));
        $selectgradeto->addOption(get_string('gradeto',$L),'',array());
        for ($i=100;$i>=0;$i--){
            $selectgradeto->addOption($i,$i,array());
        }

        //last graded from
        $mform->addElement('date_selector','lastgradedfromfilter',get_string('lastgradedfrom',$L));
        //last graded to
        $mform->addElement('date_selector','lastgradedtofilter',get_string('lastgradedto',$L));

        //buttons
        $buttonarray[] = & $mform->createElement('submit','submitbutton',get_string('filter',$L));
        $mform->addGroup($buttonarray, 'buttonar','',array(' '),false);
        $mform->closeHeaderBefore('buttonar');        
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['fromfilter']) && isset($data['tofilter']) && $data['fromfilter'] > $data['tofilter']){
            $errors['daterange'] = get_string('daterangeerror', 'block_reportsdash');
        }
        return $errors;
    }
}