<?php

?><?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook("member_profile_end", "instaroid_profile");
$plugins->add_hook("index_start", "instaroid_index");
if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
	$plugins->add_hook("global_start", "instaroid_alerts");
}


function instaroid_info()
{
	global $lang;
	$lang->load('instaroid');
	
	return array(
		"name"			=> $lang->insta_name,
		"description"	=> $lang->insta_description,
		"website"		=> "https://github.com/its-sparks-fly",
		"author"		=> "sparks fly",
		"authorsite"	=> "https://sparks-fly.info",
		"version"		=> "1.0",
		"compatibility" => "18*"
	);
}

function instaroid_install()
{
    global $db;

    $db->query("CREATE TABLE ".TABLE_PREFIX."instaroid_img (
        `iid` int(11) NOT NULL AUTO_INCREMENT,
        `uid` int(11) NOT NULL,
        `name` varchar(155) NOT NULL,
        `desc` varchar(140) NOT NULL,
        PRIMARY KEY (`iid`),
        KEY `iid` (`iid`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1");

     $db->query("CREATE TABLE ".TABLE_PREFIX."instaroid_comments (
        `icd` int(11) NOT NULL AUTO_INCREMENT,
        `iid` int(11) NOT NULL,
        `uid` int(11) NOT NULL,
        `desc` varchar(140) NOT NULL,
        PRIMARY KEY (`icd`),
        KEY `icd` (`icd`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1");

     $db->query("CREATE TABLE ".TABLE_PREFIX."instaroid_img_tags (
        `itid` int(11) NOT NULL AUTO_INCREMENT,
        `iid` int(11) NOT NULL,
        `uid` int(11) NOT NULL,
        PRIMARY KEY (`itid`),
        KEY `itid` (`itid`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1");


     if(!file_exists(MYBB_ROOT.'uploads/instaroid')) {
        mkdir(MYBB_ROOT.'uploads/instaroid', 0777, true);
     }
     
     $disporder = 0;
     $highestDisporder = $db->simple_select('profilefields', 'disporder');
 
     while ($highetsDisporder = $db->fetch_array($highestDisporder)) {
         if ($disporder < $highetsDisporder['disporder']) {
             $disporder = $highetsDisporder['disporder'];
         }
     }
 
     // add profile field
     $instaname = array(
         'name' => 'Instaroid Name',
         'description' => 'Der Instaroid Social Name',
         'type' => 'text',
         'disporder' => $disporder + 1,
         'viewableby' => '-1',
         'editableby' => '-1',
         'maxlength' => 0,
         'viewableby' => '3,4',
         'editableby' => '3,4',
         'regex' => ''
     );
 
     $fid = $db->insert_query("profilefields", $instaname);
     $db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields ADD fid".$fid." TEXT DEFAULT NULL;");
}

function instaroid_is_installed()
{
	global $db;
	if($db->table_exists("instaroid_img"))
	{
		return true;
	}

	return false;
}

function instaroid_uninstall()
{
	global $db;

    // drop database tables
    $db->query("DROP TABLE ".TABLE_PREFIX."instaroid_img");
    $db->query("DROP TABLE ".TABLE_PREFIX."instaroid_img_tags");
    $db->query("DROP TABLE ".TABLE_PREFIX."instaroid_comments");

    // drop profile field
    $instaname = $db->fetch_field($db->simple_select("profilefields", "fid", "name LIKE '%instaroid%'"), "fid");
    $db->delete_query('profilefields', "name LIKE '%instaroid%'");
    $instaname = 'fid'.$instaname;
    if ($db->field_exists($instaname, 'userfields')) {
        $db->drop_column('userfields', $instaname);
    }

}

function instaroid_activate() {
    global $db, $cache;

    $instaroid = [
		'title'		=> 'instaroid',
		'template'	=> $db->escape_string('<html>
		<head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->insta}</title>
		{$headerinclude}</head>
		<body>
		{$header}
			<table width="100%" cellspacing="5" cellpadding="5" class="tborder">
				<tr>
					{$menu}
					<td></td>
				</tr>
			</table>
		{$footer}
		</body>
		</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid);

    $instaroid_feed = [
		'title'		=> 'instaroid_feed',
		'template'	=> $db->escape_string('<html>
		<head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->insta}</title>
		{$headerinclude}</head>
		<body>
		{$header}
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<table width="100%" cellspacing="5" cellpadding="5" class="tborder">
				<tr>
					{$menu}
					<td valign="top">
						{$multipage}
						<div id="instafeed">
						{$instaroid_feed_bit}
							<br style="clear: both;" />
						</div><br style="clear: both;" />
						{$multipage}
					</td>
				</tr>
			</table>
		{$footer}
		</body>
		</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed);

    $instaroid_feed_bit = [
		'title'		=> 'instaroid_feed_bit',
		'template'	=> $db->escape_string('<div class="img">
        <a href="#insta{$insta[\'iid\']}"><img src="uploads/instaroid/{$insta[\'name\']}" /></a>
        <div class="socialname"><strong><a href="member.php?action=profile&uid={$insta[\'uid\']}" target="_blank">@{$instauname}</a></strong></div>
    </div>
    
    <div id="insta{$insta[\'iid\']}" class="instapop">
      <div class="pop">
          <div class="insta-comments">
              {$instaroid_feed_bit_tagged}
              {$instaroid_feed_bit_comment}
          </div>
          <div class="insta-userinfo">
              <div class="insta-userpicture">
                  <img src="{$picture}" />
              <div class="insta-socialname"><a href="member.php?action=profile&uid={$insta[\'uid\']}" target="_blank">@{$instauname}</a></div>
              </div>
          </div>
          <div class="insta-picture">
              <img src="uploads/instaroid/{$insta[\'name\']}" />
          </div>
          <div class="insta-description">{$delete_link} <strong>{$instauname}</strong> &bull; {$insta[\'desc\']}</div>
          <div class="insta-comment">
          {$instaroid_feed_bit_addcomment}
          </div>
        </div><a href="#closepop" class="closepop"></a>
    </div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit);

    $instaroid_feed_bit_addcomment = [
		'title'		=> 'instaroid_feed_bit_addcomment',
		'template'	=> $db->escape_string('<center>
        <form method="post" action="instaroid.php?action=add_comment&iid={$insta[\'iid\']}">
            <input type="text" value="Kommentar verfassen"  onfocus="this.value=\'\'" name="entry" class="insta_input" /> 
            <input type="submit" value="Comment" class="insta_submit" />
        </form>
    </center>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit_addcomment);

    $instaroid_feed_bit_addcomment_empty = [
		'title'		=> 'instaroid_feed_bit_addcomment_empty',
		'template'	=> $db->escape_string('<center>{$lang->insta_socialname_note}</center>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit_addcomment_empty);


    $instaroid_feed_bit_comment = [
		'title'		=> 'instaroid_feed_bit_comment',
		'template'	=> $db->escape_string('<div class="insta_usercomment">
        {$delete_link} <strong><a href="member.php?action=profile&uid={$comment[\'uid\']}" target="_blank">{$commentinstaname}</a></strong> &bull; {$comment[\'desc\']} {$matches[\'2\']}
    </div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit_comment);

    $instaroid_feed_bit_comment_none = [
		'title'		=> 'instaroid_feed_bit_comment_none',
		'template'	=> $db->escape_string('<div style="width: 80%; margin: auto; margin-top: 100px;"><center>{$lang->insta_comment_none}</center></div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit_comment_none);

    $instaroid_feed_bit_tag = [
		'title'		=> 'instaroid_feed_bit_tag',
		'template'	=> $db->escape_string('<div class="insta-taguser">{$taguserlink}</div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit_tag);

    $instaroid_feed_bit_tagged = [
		'title'		=> 'instaroid_feed_bit_tagged',
		'template'	=> $db->escape_string('		  <div class="insta-tagged">
        <strong>Featured:</strong> {$instaroid_feed_bit_tag}
    </div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit_tagged);

    $instaroid_feed_bit_tagged = [
		'title'		=> 'instaroid_feed_bit_tagged',
		'template'	=> $db->escape_string('		  <div class="insta-tagged">
        <strong>Featured:</strong> {$instaroid_feed_bit_tag}
    </div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_feed_bit_tagged);

    $instaroid_index = [
		'title'		=> 'instaroid_index',
		'template'	=> $db->escape_string('<div class="thead"> {$lang->insta_hot} <a href="instaroid.php?action=feed">[ {$lang->insta_photofeed} ]</a></div>
        <div id="instaroid">{$instaroid_index_bit}<br style="clear: both;" /></div><br />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_index);

    $instaroid_index_bit = [
		'title'		=> 'instaroid_index_bit',
		'template'	=> $db->escape_string('<div class="img">
        <a href="instaroid.php?action=feed#insta{$insta[\'iid\']}"><img src="uploads/instaroid/{$insta[\'name\']}" /></a>
        <div class="socialname"><strong><a href="member.php?action=profile&uid={$insta[\'uid\']}">@{$instauname}</a></strong></div>
    </div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_index_bit);

    $instaroid_member_profile = [
		'title'		=> 'instaroid_member_profile',
		'template'	=> $db->escape_string('<div id="instafeed">{$instaroid_feed_bit}</div><br style="clear: both" />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_member_profile);

    $instaroid_nav = [
		'title'		=> 'instaroid_nav',
		'template'	=> $db->escape_string('<td width="20%" valign="top">
        <div class="thead"><strong>{$lang->insta_navigation}</strong></div>
        <div class="trow1"><a href="instaroid.php?action=feed">{$lang->insta_photofeed}</a></div>
        <div class="trow2"><a href="instaroid.php?action=upload">{$lang->insta_upload}</a></div>
        <div class="trow1"><a href="instaroid.php?action=socialname">{$lang->insta_socialname}</a></div>
    </td>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_nav);

    $instaroid_socialname = [
		'title'		=> 'instaroid_socialname',
		'template'	=> $db->escape_string('<html>
		<head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->insta_socialname}</title>
		{$headerinclude}</head>
		<body>
		{$header}
			<form enctype="multipart/form-data" action="instaroid.php" method="post">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<table width="100%" cellspacing="5" cellpadding="5" class="tborder">
				<tr>
					{$menu}
					<td valign="top">
		{$name_error}
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" width="100%">
			<tr>
				<td class="thead" colspan="2"><strong>{$lang->insta_socialname}</strong></td>
			</tr>
				<td class="trow1" width="40%" align="justify">
					<strong>{$lang->insta_socialname_name}</strong>
					<br /> <span class="smalltext">{$lang->insta_socialname_note}</span>
				</td>
				<td class="trow1" width="60%">
					<input type="text" class="textbox" name="instaname" id="tags" value="{$mybb->user[$instaname]}" size="40" maxlength="1155" style="min-width: 347px; max-width: 100%;" />
				</td>
			</tr>
		</table>

		<br />
		<div align="center">
			<input type="hidden" name="action" value="do_socialname" />
			<input type="submit" class="button" name="submit" value="{$lang->insta_save}" />
		</div>					
					</td>
				</tr>
			</table>
			</form>
		{$footer}
		</body>
		</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_socialname);

    $instaroid_upload = [
		'title'		=> 'instaroid_upload',
		'template'	=> $db->escape_string('<html>
		<head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->insta_upload}</title>
		{$headerinclude}</head>
		<body>
		{$header}
			<form enctype="multipart/form-data" action="instaroid.php" method="post">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<table width="100%" cellspacing="5" cellpadding="5" class="tborder">
				<tr>
					{$menu}
					<td valign="top">
		{$insta_error}
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}">
			<tr>
				<td class="thead" colspan="2"><strong>{$lang->insta_upload}</strong></td>
			</tr>
			<tr>
				<td class="trow1" colspan="2" align="justify">
					<span class="smalltext">{$lang->insta_upload_note}</span>
				</td>
			</tr>
			<tr>
				<td class="trow1" width="40%">
					<strong>{$lang->insta_upload_choose}</strong>
				</td>
				<td class="trow1" width="60%">
					<input type="file" name="instaupload" size="25" class="fileupload" />
				</td>
			</tr>
			<tr>
				<td class="trow1" width="40%" align="justify">
					<strong>{$lang->insta_upload_desc}</strong>
					<br /> <span class="smalltext">{$lang->insta_upload_desc_note}</span>
				</td>
				<td class="trow1" width="60%">
					<textarea name="desc"></textarea>
				</td>
			</tr>
			<tr>
				<td class="trow1" width="40%" align="justify">
					<strong>{$lang->insta_upload_tags}</strong>
					<br /> <span class="smalltext">{$lang->insta_upload_tags_note}</span>
				</td>
				<td class="trow1" width="60%">
					<input type="text" class="textbox" name="tags" id="tags" size="40" maxlength="1155" style="min-width: 347px; max-width: 100%;" />
				</td>
			</tr>
		</table>

		<br />
		<div align="center">
			<input type="hidden" name="action" value="do_upload" />
			<input type="submit" class="button" name="submit" value="{$lang->insta_upload}" />
		</div>					
					</td>
				</tr>
			</table>
			</form>
		{$footer}
		</body>
		</html>
	
	        <link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css?ver=1807">
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1806"></script>
        <script type="text/javascript">
        <!--
        if(use_xmlhttprequest == "1")
        {
            MyBB.select2();
            $("#tags").select2({
                placeholder: "{$lang->search_user}",
                minimumInputLength: 2,
                maximumSelectionSize: \'\',
                multiple: true,
                ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
                    url: "xmlhttp.php?action=get_users",
                    dataType: \'json\',
                    data: function (term, page) {
                        return {
                            query: term, // search term
                        };
                    },
                    results: function (data, page) { // parse the results into the format expected by Select2.
                        // since we are using custom formatting functions we do not need to alter remote JSON data
                        return {results: data};
                    }
                },
                initSelection: function(element, callback) {
                    var query = $(element).val();
                    if (query !== "") {
                        var newqueries = [];
                        exp_queries = query.split(",");
                        $.each(exp_queries, function(index, value ){
                            if(value.replace(/\\s/g, \'\') != "")
                            {
                                var newquery = {
                                    id: value.replace(/,\\s?/g, ","),
                                    text: value.replace(/,\\s?/g, ",")
                                };
                                newqueries.push(newquery);
                            }
                        });
                        callback(newqueries);
                    }
                }
            })
        }
        // -->
        </script>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
    ];
	$db->insert_query("templates", $instaroid_upload);


    if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('instaroid_upload'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);

		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('instaroid_tagged'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);

        $alertTypeManager->add($alertType);
        
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('instaroid_comment'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);
	}

    // edit templates
 	include MYBB_ROOT."/inc/adminfunctions_templates.php";
 	find_replace_templatesets("index", "#".preg_quote('{$forums}')."#i", '{$instaroid_index} {$forums}');
	find_replace_templatesets("member_profile", "#".preg_quote('{$awaybit}')."#i", '{$awaybit} {$instaroid_member_profile}');

       // CSS  
	   $css = array(
        'name' => 'instaroid.css',
        'tid' => 1,
        "stylesheet" => '#instaroid,
        #instafeed {
            box-sizing: border-box;
            position: relative;
            width: 100%;
            background: #f0f0f0;
            padding: 5px;
        }
        
        #instafeed {
            background: transparent; 	
        }
        
        #instaroid .img img,
        #instafeed .img img {
            width: 100%;
            opacity: .8;
            transition: .7s;
            z-index: 1;
        }
        
        #instaroid img:first-of-type,
        #instafeed img:first-of-type {
            width: 100%;
            z-index: 1;
        }
        
        #instaroid img:hover,
        #instafeed img:hover {
            transition: .7s;
            opacity: 1;
        }
        
        #instaroid .img {
             float: left; 
            width: 12%;
            margin: 4px 11px;
            position: relative;
        }
        
        #instafeed .img {
                 float: left; 
            width: 20%;
            margin: 4px 11px;
            position: relative;
        }
        
        #instaroid .img:first-of-type {
            float: left; 
            width: 25%;
            margin-top: 5px;
            position: relative;
        }
        
        #instaroid .img .socialname,
        #instafeed .img .socialname {
            display: inline-block;
            background: #0072BC;
            color: #fff;
            text-align: center;
            padding: 5px;
            font-size: 7px;
            letter-spacing: 1px;
            font-family: calibri;
            text-transform: uppercase;
            position: absolute;
            bottom: 15px;
            left: 5%;
            z-index: 2;
            border-radius: 3px;
        }
        
        #instaroid .img .socialname a:link,
        #instaroid .img .socialname a:hover,
        #instaroid .img .socialname a:visited,
        #instaroid .img .socialname a:active,
        #instafeed .img .socialname a:link,
        #instafeed .img .socialname a:hover,
        #instafeed .img .socialname a:visited,
        #instafeed .img .socialname a:active,
        .insta-socialname a:link,
        .insta-socialname a:hover,
        .insta-socialname a:visited,
        .insta-socialname a:active {
            color: #fff !important;
        }
        
        .instapop { position: fixed; top: 0; right: 0; bottom: 0; left: 0; background: hsla(0, 0%, 0%, 0.5); z-index: 50; opacity:0; -webkit-transition: .5s ease-in-out; -moz-transition: .5s ease-in-out; transition: .5s ease-in-out; pointer-events: none; } 
        
        .instapop:target { opacity:1; pointer-events: auto; } 
        
        .instapop > .pop { background: #dedede; width: 820px; position: relative; margin: 3% auto; padding: 25px; z-index: 51; border-radius: 10px; } 
        
        .closepop { position: absolute; right: -5px; top:-5px; width: 100%; height: 100%; z-index: 49; }
        
        .insta-picture > img {
            height: 400px !important;
            width: 400px !important;
            border-radius: 5px;
        }
        
        .insta-userinfo {
            box-sizing: border-box;
            width: 400px;
            background: rgba(255,255,255,.3);
            border: 1px solid rgba(0,0,0,.05);
            border-radius: 5px;
            margin-bottom: 10px;
                padding: 5px;
        }
        .insta-userpicture img {
            height: 40px !important;
            width: 40px !important;
            margin-top: 5px;
            margin-left: 20px;
            border-radius: 2px;
        }
        
        .insta-socialname {
            display: inline-block;
            background: #0072BC;
            color: rgba(255,255,255,.9);
            text-align: center;
            padding: 5px;
            font-size: 9px;
            letter-spacing: 1px;
            font-family: calibri;
            text-transform: uppercase;
            margin-left: 15px;
            border-radius: 3px;
            position: relative;
            font-weight: bold;
        letter-spacing: 2px;
                bottom: 15px;
        }
        
        .insta-socialname a:link,
        .insta-socialname a:active,
        .insta-socialname a:visited,
        .insta-socialname a:hover {
            color: #f1f1f1 !important;	
        }
        
        .insta-userinfo i {
            position: relative;
            bottom: 15px;
            margin-left: 15px;
        }
        
        .insta-description {
            box-sizing: border-box;
            margin-top: 10px;
            background: rgba(255,255,255,.3);
            border: 1px solid rgba(0,0,0,.05);
            padding: 10px;
            width: 400px;
            border-radius: 5px;
            font-family: Calibri, sans-serif; font-size: 13px; line-height: 1.2em; color: #6b6b6b; text-align: justify; 
        }
        
        .insta-comments {
            float: right;
            height: 520px;
            width: 400px;
            overflow: auto;
        }
        
        .insta_input {
            width: 80%;
            margin: auto;
            margin-right: 5px;
            border: none;
            border-radius: 3px;
            background: rgba(255,255,255,.3);
            border: 1px solid rgba(0,0,0,.05);
            padding: 10px;
            color: #6d6d6d;
        }
        
        .insta_submit {
            padding: 12px;
            border: none;
            border-radius: 3px;
            font-family: calibri, sans-serif;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 2px;
            position: relative;
            bottom: 2px;
            margin-right: 5px;
            color: #f9f9f9;
            background: #0072BC;
        }
        
        .insta-comment {
            margin-top: 15px;
        }
        
        .insta_usercomment {
            box-sizing: border-box;
            background: rgba(255,255,255,.3);
            border: 1px solid rgba(0,0,0,.05);
            padding: 10px 30px;
            border-radius: 5px;
            font-family: Calibri, sans-serif; font-size: 14px; line-height: 1.3em; color: #6b6b6b; text-align: justify; letter-spacing: 0.5px;
            margin: 2px auto;
        }
        
        .insta_usercomment a:link,
        .insta_usercomment a:active,
        .insta_usercomment a:hover,
        .insta_usercomment a:visited {
            color: #6b6b6b;
        }
        
        .insta-tagged {
            box-sizing: border-box;
            background: rgba(255,255,255,.3);
            border: 1px solid rgba(0,0,0,.05);
            padding: 10px 30px;
            border-radius: 5px;
            margin: 2px auto;
            font-size: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #6b6b6b;
        }
        
        .insta-taguser {
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 8px;
            padding: 5px;
            background: #dedede;
            font-weight: bold;
            border-radius: 2px;
        }
        
        #instaroid-postbit {
            margin: auto;
            width: 80%;
            margin-bottom: 10px;
        }
        
        .insta-postbit {
            float: left;
            margin: 3px;
        }
        
        .insta-postbit img {
            width: 95px;
            height: 95px;
            opacity: .4;
            transition: .7s;
            border-radius: 5px;
            z-index: 1;
        }
        
        .insta-postbit img:hover {
            transition: .7s;
            opacity: .7;
        }',
        'cachefile' => $db->escape_string(str_replace('/', '', 'instaroid.css')),
        'lastmodified' => time(),
        'attachedto' => ''
    );

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=".$sid), "sid = '".$sid."'", 1);

    $tids = $db->simple_select("themes", "tid");
    while($theme = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($theme['tid']);
    }
}

function instaroid_deactivate() {
    global $db, $cache;

    // drop templates
  	$db->delete_query("templates", "title LIKE '%instaroid%'");

    // edit templates
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("index", "#".preg_quote('{$instaroid_index}')."#i", '', 0);
    find_replace_templatesets("member_profile", "#".preg_quote('{$instaroid_member_profile}')."#i", '', 0);

    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('instaroid_upload');
        $alertTypeManager->deleteByCode('instaroid_tagged');
        $alertTypeManager->deleteByCode('instaroid_comment');

	}

    // drop css
    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    $db->delete_query("themestylesheets", "name = 'instaroid.css'");
    $query = $db->simple_select("themes", "tid");
    while($theme = $db->fetch_array($query)) {
        update_theme_stylesheet_list($theme['tid']);
    }
}

function instaroid_index() {
    global $db, $mybb, $templates, $lang, $instaroid_index;
    $lang->load('instaroid');

	$instaroid_index = "";
	$instaroid_index_bit = "";
    $instaname = $db->fetch_field($db->simple_select("profilefields", "fid", "name LIKE '%instaroid%'"), "fid");
    $instaname = 'fid'.$instaname;

	$query = $db->simple_select("instaroid_img", "*", "", ["order_by" => 'iid', "order_dir" => 'DESC', "limit" => 11]);
	while($insta = $db->fetch_array($query)) {
		$instauser = get_user($insta['uid']);
		$instauname = $db->fetch_field($db->simple_select("userfields", $instaname, "ufid = '{$insta['uid']}'"), $instaname);
		eval("\$instaroid_index_bit .= \"".$templates->get("instaroid_index_bit")."\";");
	}
	eval("\$instaroid_index = \"".$templates->get("instaroid_index")."\";");

}

function instaroid_profile() {
    global $db, $mybb, $memprofile, $templates, $instaroid_feed_bit, $instaroid_member_profile;
    
    $instaname = $db->fetch_field($db->simple_select("profilefields", "fid", "name LIKE '%instaroid%'"), "fid");
    $instaname = 'fid'.$instaname;

    $query = $db->query("SELECT *, ".TABLE_PREFIX."instaroid_img.iid, ".TABLE_PREFIX."instaroid_img.uid FROM ".TABLE_PREFIX."instaroid_img
    LEFT JOIN ".TABLE_PREFIX."instaroid_img_tags ON ".TABLE_PREFIX."instaroid_img.iid = ".TABLE_PREFIX."instaroid_img_tags.iid
    WHERE ".TABLE_PREFIX."instaroid_img.uid = {$memprofile['uid']}
	OR ".TABLE_PREFIX."instaroid_img_tags.uid = {$memprofile['uid']}
	GROUP BY ".TABLE_PREFIX."instaroid_img.iid
    ORDER BY ".TABLE_PREFIX."instaroid_img.iid DESC");
    while($insta = $db->fetch_array($query)) {
        $instauser = get_user($insta['uid']);
        $instauname = $db->fetch_field($db->simple_select("userfields", $instaname, "ufid = '{$insta['uid']}'"), $instaname);
        $picture = $instauser['avatar'];
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
				$delete_link = "<a href=\"instaroid.php?action=delete_comment&icd={$comment['icd']}\">[ x ]</a>";
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
            $delete_link = "<a href=\"instaroid.php?action=delete&iid={$insta['iid']}\">[x]</a>";
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
        if(!empty($mybb->user[$instaname])) {
         eval("\$instaroid_feed_bit_addcomment = \"".$templates->get("instaroid_feed_bit_addcomment")."\";");
        } else {
            eval("\$instaroid_feed_bit_addcomment = \"".$templates->get("instaroid_feed_bit_addcomment_empty")."\";");
        }
        eval("\$instaroid_feed_bit .= \"".$templates->get("instaroid_feed_bit")."\";");
    }
    eval("\$instaroid_member_profile = \"".$templates->get("instaroid_member_profile")."\";");
}

function instaroid_alerts() {
	global $mybb, $lang;
    $lang->load('instaroid');
    
	/**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_InstaroidUploadFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
	        return $this->lang->sprintf(
	            $this->lang->instaroid_upload_alert,
				$outputAlert['from_user'],
	            $outputAlert['dateline']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->lta) {
	            $this->lang->load('instaroid');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
	        return $this->mybb->settings['bburl'] . '/instaroid.php?action=feed#insta' . $alert->getObjectId();
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_InstaroidUploadFormatter($mybb, $lang, 'instaroid_upload')
		);
    }
    
	/**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_InstaroidCommentFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
	        return $this->lang->sprintf(
	            $this->lang->instaroid_comment_alert,
				$outputAlert['from_user'],
	            $outputAlert['dateline']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->lta) {
	            $this->lang->load('instaroid');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
	        return $this->mybb->settings['bburl'] . '/instaroid.php?action=feed#insta' . $alert->getObjectId();
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_InstaroidCommentFormatter($mybb, $lang, 'instaroid_comment')
		);
    }

	/**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_InstaroidTaggedFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
	        return $this->lang->sprintf(
	            $this->lang->instaroid_tagged_alert,
				$outputAlert['from_user'],
	            $outputAlert['dateline']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->lta) {
	            $this->lang->load('instaroid');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
	        return $this->mybb->settings['bburl'] . '/instaroid.php?action=feed#insta' . $alert->getObjectId();
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_InstaroidTaggedFormatter($mybb, $lang, 'instaroid_tagged')
		);
    }
    
}


?>