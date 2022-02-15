<?php

// false if no fullname wanted
// true if [fullname] is in sql
function profilefield_fix_sql(&$sql,$user){
    global $DB,$USER;
    //on creating a new user, $user will be empty, so set it to $USER
    if(!is_object($user)){
        $user = $USER;
    }
    //profile_get_user_fields_with_data($userid)
    $pfsql = "SELECT shortname,data from {user_info_field} f left join {user_info_data} d on d.userid=:userid and d.fieldid=f.id";
    $profilefields = $DB->get_records_sql_menu($pfsql,array('userid'=>$user->id));
    preg_match_all('/\[([a-z][a-z0-9_]*)\]/', $sql, $matches);

    $wants_fullname = false;

    foreach($matches[1] as &$field){
        $profile_field_len = strlen("profile_field_");
        if(substr($field,0,$profile_field_len)=="profile_field_"){
            $name = substr($field,$profile_field_len, strlen($field)-$profile_field_len);
            $field = $profilefields[$name];
        } else {
            if($field == "fullname"){
                $wants_fullname = true;
                // before moodle 3.11: 
                // $field = implode(get_all_user_name_fields(),",");
                $field = implode(\core_user\fields::for_name()->get_required_fields(),",");
            } else {
                $field = $user->$field;
            }
        };
    }
    $matches[0] = str_replace(array("[","]"),array("/\[","\]/"),$matches[0]);

    $sql = preg_replace($matches[0],$matches[1],$sql);
    return $wants_fullname;
}