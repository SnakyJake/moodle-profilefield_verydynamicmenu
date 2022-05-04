<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Dynamic menu profile field definition.
 *
 * @package    profilefield_verydynamicmenu
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}, 2022 Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 require_once($CFG->dirroot."/user/profile/field/verydynamicmenu/locallib.php");

/**
 * Class profile_field_verydynamicmenu
 *
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}, 2022 Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_verydynamicmenu extends profile_field_base {
    /** @var array $options */
    public $options;

    private $datakey = "";

    /** @var  array @calls array indexed by @fieldid-$userid. It keeps track of recordset,
     * so that we don't do the query twice for the same field */
    private static $acalls = array();
    /**
     * Constructor method.
     *
     * Pulls out the options for the menu from the database and sets the the corresponding key for the data if it exists.
     *
     * @param int $fieldid
     * @param int $userid
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null){
        // First call parent constructor.
        parent::__construct($fieldid, $userid, $fielddata);
        // Only if we actually need data.
        if ($fieldid !== 0 && $userid !== 0) {
            $this->datakey = $fieldid.','.$userid; // It will always work because they are number, so no chance of ambiguity.
            if (array_key_exists($this->datakey , self::$acalls)) {
                $rs = self::$acalls[$this->datakey];
            } else {
                $sql = $this->field->param1;
                global $DB;
                if(verydynamicmenu_profilefield_fix_sql($sql, \core_user::get_user($userid))){
                    $rstmp = $DB->get_records_sql($sql);
                    $rs = [];
                    foreach($rstmp as $record){
                        $rs[$record->id] = new stdClass();
                        $rs[$record->id]->id = $record->id;
                        $rs[$record->id]->data = fullname($record);
                    }
                } else {
                    $rs = $DB->get_records_sql($sql);
                }
                
                self::$acalls[$this->datakey] = $rs;
            }
            $this->options = array();
            if ($this->field->required) {
                $this->options[''] = get_string('choose').'...';
            }
            foreach ($rs as $key => $option) {
                $this->options[$key] = $option->data;
            }
            if(is_string($this->data)){
                $this->data = json_decode($this->data, true);
                if(!empty($this->data) && is_string(reset($this->data))){
                    $this->data = array_keys($this->data);
                }
            }
        }
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function profile_field_verydynamicmenu($fieldid=0, $userid=0,$fielddata = null) {
        self::__construct($fieldid, $userid,$fielddata);
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     * @param moodleform $mform Moodle form instance
     */
    public function edit_field_add($mform) {
        $mform->addElement('select', $this->inputname, format_string($this->field->name), $this->options, array("size"=>10));
        $mform->setType( $this->inputname, PARAM_TEXT);
        $mform->getElement($this->inputname)->setMultiple(true);
        $mform->getElement($this->inputname)->setSelected($this->data);
    }

    /**
     * When passing the user object to the form class for the edit profile page
     * we should load the key for the saved data
     * Overwrites the base class method.
     *
     * @param   object   user object
     */
    public function edit_load_user_data($user)
    {
        if(!empty($this->data)){
            $result = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if(end($result)["function"] == "download_data"){
                $string = '';
                foreach($this->data as $id) {
                    if (array_key_exists($id, self::$acalls[$this->datakey])) {
                        $string .= ($string?"\n":"").self::$acalls[$this->datakey][$id]->data;
                    }
                }
                $user->{$this->inputname} = $string;
                return;
            }
        }
        $user->{$this->inputname} = $this->data;
    }

    /**
     * The data from the form returns the key. This should be converted to the
     * respective option string to be saved in database
     * Overwrites base class accessor method.
     *
     * @param mixed    $data       - the key returned from the select input in the form
     * @param stdClass $datarecord The object that will be used to save the record
     */
    public function edit_save_data_preprocess($data, $datarecord)
    {
        if(empty($data)){
            return "";
        }
        $savedata = [];
        foreach($data as $id) {
            if (array_key_exists($id, self::$acalls[$this->datakey])) {
                $savedata[intval($id)] = self::$acalls[$this->datakey][$id]->data;
            } else {
                $newuser_datakey = $this->fieldid.',-1';
                if (array_key_exists($id, self::$acalls[$newuser_datakey])) {
                    $savedata[intval($id)] = self::$acalls[$newuser_datakey][$id]->data;
                }
            }
        }
        return json_encode($savedata,JSON_NUMERIC_CHECK|JSON_UNESCAPED_UNICODE);
    }

    /**
     * HardFreeze the field if locked.
     * @param moodleform $mform instance of the moodleform class
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() and !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname, format_string($this->data));
        }
    }
    /**
     * Convert external data (csv file) from value to key for processing later by edit_save_data_preprocess
     *
     * not implemented
     * 
     * @param string $value one of the values in menu options.
     * @return int options key for the menu
     */
    public function convert_external_data($value) {
        global $DB;
        if(is_array($value)){
            return $value;
        } else {
            $sql = $this->field->param2;
            $data = explode("\n",str_ireplace(["\r\n","\r",'\r','\n'],"\n",$value));
            list($insql, $inparams) = $DB->get_in_or_equal($data);
            $ids = $DB->get_records_sql($sql." ".$insql, $inparams);
            return array_keys($ids);
        }
    }

    /**
     * Display the data for this field.
     */
    public function display_data() {
        if(!is_array($this->data)){
            return get_string("none");
        }

        $string = '';
        foreach($this->data as $id) {
            if (array_key_exists($id, self::$acalls[$this->datakey])) {
                $string .= ($string?"<br>":"").self::$acalls[$this->datakey][$id]->data;
            }
        }
        return $string?$string:get_string("none");
    }
}