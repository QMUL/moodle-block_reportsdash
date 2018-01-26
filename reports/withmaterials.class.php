<?php

/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class block_reportsdash_withmaterials extends block_reportsdash_report {
    /**
     * Based on code in course/lib.php (print_section_add_menus)
     * Returns two arrays of resources and activities with the
     * names embedded in single quotes for use in SQL.
     *
     * @return array
     * @throws coding_exception
     */
    static function classify_activities_resources() {
        global $DB, $CFG;

        $modules = $DB->get_records('modules');

        $resources = array();
        $activities = array();

        foreach ($modules as $modid => $module) {

            $modname = $module->name;

            $libfile = "$CFG->dirroot/mod/$modname/lib.php";
            if (!file_exists($libfile)) {
                continue;
            }
            include_once($libfile);
            $gettypesfunc = $modname . '_get_types';
            if (function_exists($gettypesfunc) and $modname !== 'assignment') {
                //Ignore subtypes unless we really, really have to use them

            } else {
                $archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
                if ($archetype == MOD_ARCHETYPE_RESOURCE) {
                    $resources[$modid] = "'$modname'";
                } else {
                    // all other archetypes are considered activity
                    $activities[$modid] = "'$modname'";
                }
            }
        }

        return array('resources' => $resources,
                     'activities' => $activities);
    }

    /**
     * @return bool
     */
    static function heavy() {
        return true;
    }

// Instance

    abstract protected function getClassifications();

    /**
     * @param array $columns
     * @param bool $dynamiccols
     * @param string $langfiles
     * @throws coding_exception
     */
    function __construct($columns = array(), $dynamiccols = false, $langfiles = 'block_reportsdash') {
        parent::__construct($columns, $dynamiccols, $langfiles);

        if ($singlemode = optional_param('singlemode', 0, PARAM_INT) and $courseid =
                optional_param('courseid', 0, PARAM_INT)
        ) {
            $this->updateBaseUrl('singlemode', 1);
            $this->updateBaseUrl('courseid', $courseid);
        }
    }

    /**
     * @param $columns
     */
    function setColumns($columns,$headers=false) {
        $this->columns = $columns;

        $this->table->define_columns($columns);
        $this->table->define_headers($this->translator->ucwords(array_keys($this->table->columns)));

        $this->setColumnStyles();
    }

    /**
     * @param bool $usesort
     * @throws coding_exception
     */
    function setSql($usesort = true) {
        global $CFG;

        $pfx = $CFG->prefix;

        $classifications = $this->getClassifications();

        $cols = array('subject',
                      'startdate');

        $sqlsel = "select c.id as cid, c.startdate, c.shortname as subject, cc1.path, r.id as rid, r.visible as rvis,
                    substring_index(substring_index(cc1.path, '/', 2),'/',-1) as tlcat";

        $sqlfrom = "({$pfx}course c join {$pfx}course_categories cc1 on cc1.id=c.category
                          JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring_index(substring_index(cc1.path, '/', 2),'/',-1)
                          JOIN {$pfx}block_reportsdash_region r on r.id=rc.rid)";

        $totalcols = array();

        foreach ($classifications as $modid => $activity) {
            $name = substr($activity, 1, -1);
            $sqlsel .= ", ifnull(r{$modid}.tot,0) as $activity";
            if ($name == 'forum') {
                $sqlfrom .= "left join (select course as cid, count(course) as tot from {$pfx}$name where type<>'news' group by course) r{$modid} on r{$modid}.cid=c.id ";
            } else {
                $sqlfrom .= "left join (select course as cid, count(course) as tot from {$pfx}$name group by course) r{$modid} on r{$modid}.cid=c.id ";
            }
            $cols[] = $name;
            $totalcols[$modid] = "ifnull(r{$modid}.tot,0)";
        }

        $totalcol = implode('+', $totalcols);
        $cols[] = 'total';

        $this->setColumns($cols);

        $sql = "$sqlsel, ($totalcol) as total FROM $sqlfrom";

        $filters = $this->filters;

        $from = min($filters->tofilter, $filters->fromfilter);
        $to = max($filters->tofilter, $filters->fromfilter);

        $from--;
        $to++;

        $singlemode = optional_param('singlemode', 0, PARAM_INT);
        $courseid = optional_param('courseid', 0, PARAM_INT);

        if ($singlemode and $courseid) {
            $sql .= " where c.id=:courseid";
            $this->params['courseid'] = $courseid;
        } else {
            if (!empty($filters->opencourses)) {
                $sql .= " where ((c.startdate>:from and c.startdate<:to) or c.startdate=0)";
            } else {
                $sql .= " where (c.startdate>:from and c.startdate<:to)";
            }

            $this->params['from'] = $from;
            $this->params['to'] = $to;

            $sql .= ' having rvis=1';
            if (!empty($filters->levelfilter)) {
                if ($filters->levelfilter > 0) {
                    $filtercat = $this->mydb->get_record('course_categories', array('id' => $filters->levelfilter));

                    if ($filtercat->depth == 1) {
                        $sql .= " and tlcat=$filters->levelfilter";
                    } else {
                        $bits = explode('/', $filtercat->path);
                        $tlc = $bits[1];
                        $sql .= " and tlcat=$tlc and (path like('%/$filters->levelfilter/%') or path like('%/$filters->levelfilter')) ";
                    }
                    $this->params['levelfilter'] = $filters->levelfilter;
                } else {
                    $sql .= " and rid=" . -$filters->levelfilter;
                    $this->params['levelfilter'] = -$filters->levelfilter;
                }
            }
        }

        if ($usesort and $this->sort()) {
            $sql = "$sql order by " . $this->sort();
        }

        $this->sql = $sql;
    }

    /**
     * @return object|stdClass
     */
    protected function checkFilters() {
        $filters = parent::checkFilters();

        if (empty($filters->fromfilter)) {
            return $this->defaultFilters();
        }
        return $filters;
    }

    protected function setColumnStyles() {
        parent::setColumnStyles();
        $this->table->column_style('subject', 'text-align', 'left');
    }

    /**
     * @return string
     * @throws coding_exception
     */
    protected function exportname() {
        return $this->reportname . '_' . userdate($this->filters->fromfilter,
                                                  get_string('strftimedaydate', 'langconfig')) . ' - ' .
        userdate($this->filters->tofilter, get_string('strftimedaydate', 'langconfig'));
    }

    /**
     * @param $rowdata
     * @return mixed
     */
    protected function preprocessExport($rowdata) {
        if ($rowdata->startdate ) {
            $rowdata->startdate = userdate($rowdata->startdate, static::timeformat());
        } else {
            $rowdata->startdate = '-';
        }
        return $rowdata;
    }

    /**
     * @param $rowdata
     * @return mixed
     */
    protected function preprocessShow($rowdata) {
        global $CFG;

        if ($rowdata->startdate) {
            $rowdata->startdate = userdate($rowdata->startdate, static::timeformat());
        } else {
            $rowdata->startdate = '-';
        }

        block_reportsdash::wrap($rowdata->subject, "$CFG->wwwroot/course/view.php?id=$rowdata->cid");

        $tip = static::full_path($this->mydb, $rowdata->cid, false);
        $rowdata->subject = html_writer::tag('span', $rowdata->subject, array('title' => $tip));

        return $rowdata;
    }

    /**
     * @return array
     */
    protected function activities() {
        /**
         * @var array $activities
         */
        extract(static::classify_activities_resources());
        $clean_names = array();
        foreach ($activities as $activitiy_with_quotes) {
            $clean_names[] = substr($activitiy_with_quotes, 1, -1);
        }
        return $clean_names;
    }

    /**
     * @return array
     */
    protected function resources() {
        /**
         * @var array $resources
         */
        extract(static::classify_activities_resources());
        $clean_names = array();
        foreach ($resources as $resource_with_quotes) {
            $clean_names[] = substr($resource_with_quotes, 1, -1);
        }
        return $clean_names;
    }

    /**
     * @return string "'link', 'page', etc..."
     */
    protected function activity_names_with_single_quotes() {
        /**
         * @var array $activities
         */
        extract(static::classify_activities_resources());
        return implode(', ', $activities);
    }

    /**
     * @return string "'link', 'page', etc..."
     */
    protected function resource_names_with_single_quotes() {
        /**
         * @var array $resources
         */
        extract(static::classify_activities_resources());
        return implode(', ', $resources);
    }

    /**
     * @return int
     * @throws coding_exception
     */
    protected function course_id() {
        return optional_param('courseid', 0, PARAM_INT);
    }
}