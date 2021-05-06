<?php

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'instaroid.php');

require_once "./global.php";
$lang->load('instaroid');

add_breadcrumb($lang->insta, "instaroid.php");

// Breadcrump Navigation
switch($mybb->input['action'])
{
    case "upload":
        add_breadcrumb($lang->insta_upload);
        break;
}

if(!$mybb->user['uid']) {
    error_no_permission();

}

$instaname = $db->fetch_field($db->simple_select("profilefields", "fid", "name LIKE '%instaroid%'"), "fid");
$instaname = 'fid'.$instaname;

eval("\$menu = \"".$templates->get("instaroid_nav")."\";");

// Landing Page
if(!$mybb->input['action'])
{
    eval("\$page = \"".$templates->get("instaroid")."\";");
    output_page($page);
}

if($mybb->input['action'] == "do_upload") {

    require_once MYBB_ROOT."inc/functions_upload.php";
    $insta_error = "";

    $name = $_FILES["instaupload"]["name"];
    $imageFileType = end((explode(".", $name)));
    $target_dir = MYBB_ROOT . "uploads/instaroid/";
    $filename = $mybb->user['uid'] . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . basename($filename);
    move_uploaded_file($_FILES["instaupload"]["tmp_name"], $target_file);

    $img_dimensions = @getimagesize($target_dir . $filename);
	if(!is_array($img_dimensions))
	{
		delete_uploaded_file($target_dir . $filename);
    }
    
    $width = $img_dimensions[0];
    $height = $img_dimensions[1];

    if($width / $height != 1) {
        delete_uploaded_file($target_dir . $filename);
        $insta_error = $lang->insta_error_even;
    }

    if($width < 400 || $height < 400) {
        delete_uploaded_file($target_dir . $filename);
        $insta_error = $lang->insta_error_small;
    }
    if(empty($instaname)) {
        delete_uploaded_file($target_dir . $filename);
        $insta_error = $lang->insta_no_name;
    }
	if(empty($insta_error))
	{
        $insert_array = [
            "uid" => (int)$mybb->user['uid'],
            "name" => $db->escape_string($filename),
            "desc" => $db->escape_string($mybb->get_input('desc'))
        ];

        $iid = $db->insert_query("instaroid_img", $insert_array);

        if(!empty($mybb->get_input('tags'))) {
            $tags = explode(",", $mybb->get_input('tags'));
            $tags = array_map("trim", $tags);
            foreach($tags as $tag) {
                $db->escape_string($tag);
                $tag_uid = $db->fetch_field($db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username = '$tag'"), "uid");
                $new_record = [
                    "iid" => (int)$iid,
                    "uid" => (int)$tag_uid
                ];
                $db->insert_query("instaroid_img_tags", $new_record);

                if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('instaroid_tagged');
                    if ($alertType != NULL && $alertType->getEnabled()) {
                        $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$tag_uid, $alertType, (int)$iid);
                        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                    }
                }	

            }
        }

		$query = $db->simple_select("follow", "fromid", "toid='{$mybb->user['uid']}'");
		while($follower = $db->fetch_array($query)) {
			if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
				$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('instaroid_upload');
				if ($alertType != NULL && $alertType->getEnabled()) {
					$alert = new MybbStuff_MyAlerts_Entity_Alert((int)$follower['fromid'], $alertType, (int)$iid);
					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				}
			}			
        }
    

		redirect("instaroid.php?action=feed");
	}
	else
	{
		$mybb->input['action'] = "upload";
		$insta_error = inline_error($insta_error);
    }   
}

if($mybb->input['action'] == "upload") {

    if(!isset($insta_error))
	{
		$insta_error = "";
	}

    eval("\$page = \"".$templates->get("instaroid_upload")."\";");
    output_page($page);
}

if($mybb->input['action'] == "socialname") {

    eval("\$page = \"".$templates->get("instaroid_socialname")."\";");
    output_page($page);
}

if($mybb->input['action'] == "do_socialname") {

    (int)$uid = $mybb->user['uid'];
     
    $insert_array = [
      $instaname => $db->escape_string($mybb->get_input('instaname'))
    ];

    $db->update_query("profilefields", $insert_array, "ufid = '{$uid}'");
    redirect("instaroid.php?action=socialname");
}

if($mybb->input['action'] == "feed") {
	
		// MULTIPAGE
		$query = $db->simple_select("instaroid_img", "COUNT(*) AS numinstas");
		$usercount = $db->fetch_field($query, "numinstas");
		$perpage = 15;
		$page = intval($mybb->input['page']);
		if($page) {
			$start = ($page-1) *$perpage;
		}
		else {
			$start = 0;
			$page = 1;
		}
		$end = $start + $perpage;
		$lower = $start+1;
		$upper = $end;
		if($upper > $usercount) {
			$upper = $usercount;
		}
		$multipage = multipage($usercount, $perpage, $page, $_SERVER['PHP_SELF']."?action=feed");
	 
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."instaroid_img WHERE uid in(SELECT uid FROM ".TABLE_PREFIX."users) ORDER BY iid DESC LIMIT $start, $perpage");

    #$query = $db->simple_select("instaroid_img", "*", "", ["order_by" => 'iid', "order_dir" => 'DESC']);
    while($insta = $db->fetch_array($query)) {
        $instauser = get_user($insta['uid']);
        $instaname = $db->fetch_field($db->simple_select("userfields", $instaname, "ufid = '{$insta['uid']}'"), $instaname);
        $query_2 = $db->simple_select("instaroid_comments", "*", "iid = '{$insta['iid']}' AND uid in(SELECT uid FROM ".TABLE_PREFIX."users)");
        $instaroid_feed_bit_comment  = "";
        while($comment = $db->fetch_array($query_2)) {
            $commentinstaname = $db->fetch_field($db->simple_select("userfields", $instaname, "ufid = '{$comment['uid']}'"), $instaname);
            $pattern = "/.*?@([a-zA-Z._\-]*).*?/";
            preg_match_all($pattern, $comment['desc'], $matches);
            foreach($matches[1] as $match) {
                $useruid = $db->fetch_field($db->simple_select("userfields", "ufid", $instaname . " = '{$match}'"), "ufid");
                $taggeduser = get_user($useruid);
                $taggedusername = format_name($match, $taggeduser['usergroup'], $taggeduser['displaygroup']);
                $taggeduserlink = build_profile_link($taggedusername, $useruid, "_blank");   
                $searchpattern = "/{$match}/";
                $comment['desc'] = preg_replace($searchpattern, $taggeduserlink, $comment['desc']);             
            }
			$delete_link = "";
			if($mybb->user['uid'] == $comment['uid'] || $mybb->usergroup['cancp'] == 1) {
				$delete_link = "<a href=\"instaroid.php?action=delete_comment&icd={$comment['icd']}\"><i class=\"fas fa-trash-alt\"></i></a>";
			}
            eval("\$instaroid_feed_bit_comment .= \"".$templates->get("instaroid_feed_bit_comment")."\";");   
        }
        if(!mysqli_num_rows($query_2)) {
            eval("\$instaroid_feed_bit_comment = \"".$templates->get("instaroid_feed_bit_comment_none")."\";");
        }
        $query_3 = $db->simple_select("instaroid_img_tags", "uid", "iid = '{$insta['iid']}' AND uid in(SELECT uid FROM ".TABLE_PREFIX."users)");
        $instaroid_feed_bit_tag = "";
        while($tag = $db->fetch_array($query_3)) {
            $taguser = get_user($tag['uid']);
            $tagusername = format_name($taguser['username'], $taguser['usergroup'], $taguser['displaygroup']);
            $taguserlink = build_profile_link($tagusername, $tag['uid'], "_blank");
            eval("\$instaroid_feed_bit_tag .= \"".$templates->get("instaroid_feed_bit_tag")."\";"); 
        }

        $instaroid_feed_bit_tagged = ""; 
        if(mysqli_num_rows($query_3)) {
            eval("\$instaroid_feed_bit_tagged = \"".$templates->get("instaroid_feed_bit_tagged")."\";"); 
        }

        $delete_link = "";
        if($insta['uid'] == $mybb->user['uid'] || $mybb->usergroup['cancp'] == "1") {
            $delete_link = "<a href=\"instaroid.php?action=delete&iid={$insta['iid']}\"><i class=\"fas fa-trash-alt\"></i></a>";
        }

        $pattern = "/.*?@([a-zA-Z._\-]*).*?/";
        preg_match_all($pattern, $insta['desc'], $matches);
        foreach($matches[1] as $match) {
            $useruid = $db->fetch_field($db->simple_select("userfields", "ufid", $instaname . "= '{$match}'"), "ufid");
            $taggeduser = get_user($useruid);
            $taggedusername = format_name($match, $taggeduser['usergroup'], $taggeduser['displaygroup']);
            $taggeduserlink = build_profile_link($taggedusername, $useruid, "_blank");   
            $searchpattern = "/{$match}/";
            $insta['desc'] = preg_replace($searchpattern, $taggeduserlink, $insta['desc']);             
        }

        eval("\$instaroid_feed_bit .= \"".$templates->get("instaroid_feed_bit")."\";");
    }
    eval("\$page = \"".$templates->get("instaroid_feed")."\";");
    output_page($page);
}

if($mybb->input['action'] == "add_comment") {

    $iid = $mybb->get_input('iid');
    $instauser = $db->fetch_field($db->simple_select("instaroid_img", "uid", "iid = '{$iid}'"), "uid");
    $entry = $db->escape_string($mybb->get_input('entry'));
    $uid = $mybb->user['uid'];

    $insert_array = [
        "iid" => (int)$iid,
        "desc" => $entry,
        "uid" => (int)$uid
    ];

    $db->insert_query("instaroid_comments", $insert_array);

    $pattern = "/.*?@([a-zA-Z._\-]*).*?/";
    preg_match_all($pattern, $entry, $matches);
    foreach($matches[1] as $match) {
        $useruid = $db->fetch_field($db->simple_select("userfields", "ufid", $instaname . "= '{$match}'"), "ufid");
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('instaroid_tagged');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$useruid, $alertType, (int)$iid);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }
    }

    if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('instaroid_comment');
        if ($alertType != NULL && $alertType->getEnabled()) {
            $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$instauser, $alertType, (int)$iid);
            MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
        }
    }			


    redirect("instaroid.php?action=feed#insta{$iid}");
}

if($mybb->input['action'] == "delete") {
    $iid = (int)$mybb->input['iid'];
    $uid = $db->fetch_field($db->simple_select("instaroid_img", "uid", "iid = '{$iid}'"), "uid");

    if($uid == $mybb->user['uid'] || $mybb->usergroup['cancp'] == 1) {
        $db->delete_query("instaroid_img", "iid = '$iid'");
        $db->delete_query("instaroid_img_tags", "iid = '$iid'");
        $db->delete_query("instaroid_comments", "iid = '$iid'");

        redirect("instaroid.php?action=feed");
    }
    else {
        error_no_permission();
    }
}

if($mybb->input['action'] == "delete_comment") {
    $icd = (int)$mybb->input['icd'];
    $uid = $db->fetch_field($db->simple_select("instaroid_comments", "uid", "icd = '{$icd}'"), "uid");

    if($uid == $mybb->user['uid'] || $mybb->usergroup['cancp'] == 1) {
        $db->delete_query("instaroid_comments", "icd = '$icd'");

        redirect("instaroid.php?action=feed");
    }
    else {
        error_no_permission();
    }
}

?>