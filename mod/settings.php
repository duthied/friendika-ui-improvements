<?php


function settings_init(&$a) {
	if(local_user()) {
		profile_load($a,$a->user['nickname']);
	}

	$a->page['htmlhead'] .= "<script> var ispublic = '" . t('everybody') . "';" ;

	$a->page['htmlhead'] .= <<< EOT

	$(document).ready(function() {

		$('#contact_allow, #contact_deny, #group_allow, #group_deny').change(function() {
			var selstr;
			$('#contact_allow option:selected, #contact_deny option:selected, #group_allow option:selected, #group_deny option:selected').each( function() {
				selstr = $(this).text();
				$('#jot-perms-icon').removeClass('unlock').addClass('lock');
				$('#jot-public').hide();
			});
			if(selstr == null) { 
				$('#jot-perms-icon').removeClass('lock').addClass('unlock');
				$('#jot-public').show();
			}

		}).trigger('change');

	});

	</script>
EOT;


}


function settings_post(&$a) {

	if(! local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	if(count($a->user) && x($a->user,'uid') && $a->user['uid'] != local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	if(($a->argc > 1) && ($a->argv[1] == 'addon')) {
		call_hooks('plugin_settings_post', $_POST);
		return;
	}

	call_hooks('settings_post', $_POST);

	if((x($_POST,'npassword')) || (x($_POST,'confirm'))) {

		$newpass = $_POST['npassword'];
		$confirm = $_POST['confirm'];

		$err = false;
		if($newpass != $confirm ) {
			notice( t('Passwords do not match. Password unchanged.') . EOL);
			$err = true;
		}

		if((! x($newpass)) || (! x($confirm))) {
			notice( t('Empty passwords are not allowed. Password unchanged.') . EOL);
			$err = true;
		}

		if(! $err) {
			$password = hash('whirlpool',$newpass);
			$r = q("UPDATE `user` SET `password` = '%s' WHERE `uid` = %d LIMIT 1",
				dbesc($password),
				intval(local_user())
			);
			if($r)
				info( t('Password changed.') . EOL);
			else
				notice( t('Password update failed. Please try again.') . EOL);
		}
	}

	$theme            = ((x($_POST,'theme'))      ? notags(trim($_POST['theme']))        : '');
	$username         = ((x($_POST,'username'))   ? notags(trim($_POST['username']))     : '');
	$email            = ((x($_POST,'email'))      ? notags(trim($_POST['email']))        : '');
	$timezone         = ((x($_POST,'timezone'))   ? notags(trim($_POST['timezone']))     : '');
	$defloc           = ((x($_POST,'defloc'))     ? notags(trim($_POST['defloc']))       : '');
	$openid           = ((x($_POST,'openid_url')) ? notags(trim($_POST['openid_url']))   : '');
	$maxreq           = ((x($_POST,'maxreq'))     ? intval($_POST['maxreq'])             : 0);
	$expire           = ((x($_POST,'expire'))     ? intval($_POST['expire'])             : 0);

	$allow_location   = (((x($_POST,'allow_location')) && (intval($_POST['allow_location']) == 1)) ? 1: 0);
	$publish          = (((x($_POST,'profile_in_directory')) && (intval($_POST['profile_in_directory']) == 1)) ? 1: 0);
	$net_publish      = (((x($_POST,'profile_in_netdirectory')) && (intval($_POST['profile_in_netdirectory']) == 1)) ? 1: 0);
	$old_visibility   = (((x($_POST,'visibility')) && (intval($_POST['visibility']) == 1)) ? 1 : 0);
	$page_flags       = (((x($_POST,'page-flags')) && (intval($_POST['page-flags']))) ? intval($_POST['page-flags']) : 0);
	$blockwall        = (((x($_POST,'blockwall')) && (intval($_POST['blockwall']) == 1)) ? 0: 1); // this setting is inverted!

	$hide_friends = (($_POST['hide-friends'] == 1) ? 1: 0);
	$hidewall = (($_POST['hidewall'] == 1) ? 1: 0);




	$mail_server      = ((x($_POST,'mail_server')) ? $_POST['mail_server'] : '');
	$mail_port        = ((x($_POST,'mail_port')) ? $_POST['mail_port'] : '');
	$mail_ssl         = ((x($_POST,'mail_ssl')) ? strtolower(trim($_POST['mail_ssl'])) : '');
	$mail_user        = ((x($_POST,'mail_user')) ? $_POST['mail_user'] : '');
	$mail_pass        = ((x($_POST,'mail_pass')) ? trim($_POST['mail_pass']) : '');
	$mail_replyto     = ((x($_POST,'mail_replyto')) ? $_POST['mail_replyto'] : '');
	$mail_pubmail     = ((x($_POST,'mail_pubmail')) ? $_POST['mail_pubmail'] : '');


	$mail_disabled = ((function_exists('imap_open') && (! get_config('system','imap_disabled'))) ? 0 : 1);
	if(get_config('system','dfrn_only'))
		$mail_disabled = 1;

	if(! $mail_disabled) {
		$r = q("SELECT * FROM `mailacct` WHERE `uid` = %d LIMIT 1",
			intval(local_user())
		);
		if(! count($r)) {
			q("INSERT INTO `mailacct` (`uid`) VALUES (%d)",
				intval(local_user())
			);
		}
		if(strlen($mail_pass)) {
			$pass = '';
			openssl_public_encrypt($mail_pass,$pass,$a->user['pubkey']);
			q("UPDATE `mailacct` SET `pass` = '%s' WHERE `uid` = %d LIMIT 1",
					dbesc(bin2hex($pass)),
					intval(local_user())
			);
		}
		$r = q("UPDATE `mailacct` SET `server` = '%s', `port` = %d, `ssltype` = '%s', `user` = '%s',
			`mailbox` = 'INBOX', `reply_to` = '%s', `pubmail` = %d WHERE `uid` = %d LIMIT 1",
			dbesc($mail_server),
			intval($mail_port),
			dbesc($mail_ssl),
			dbesc($mail_user),
			dbesc($mail_replyto),
			intval($mail_pubmail),
			intval(local_user())
		);
		$r = q("SELECT * FROM `mailacct` WHERE `uid` = %d LIMIT 1",
			intval(local_user())
		);
		if(count($r)) {
			$eacct = $r[0];
			require_once('include/email.php');
			$mb = construct_mailbox_name($eacct);
			if(strlen($eacct['server'])) {
				$dcrpass = '';
				openssl_private_decrypt(hex2bin($eacct['pass']),$dcrpass,$a->user['prvkey']);
				$mbox = email_connect($mb,$mail_user,$dcrpass);
				unset($dcrpass);
				if(! $mbox)
					notice( t('Failed to connect with email account using the settings provided.') . EOL);
			}
		}
	}

	$notify = 0;

	if(x($_POST,'notify1'))
		$notify += intval($_POST['notify1']);
	if(x($_POST,'notify2'))
		$notify += intval($_POST['notify2']);
	if(x($_POST,'notify3'))
		$notify += intval($_POST['notify3']);
	if(x($_POST,'notify4'))
		$notify += intval($_POST['notify4']);
	if(x($_POST,'notify5'))
		$notify += intval($_POST['notify5']);

	$email_changed = false;

	$err = '';

	$name_change = false;

	if($username != $a->user['username']) {
		$name_change = true;
		if(strlen($username) > 40)
			$err .= t(' Please use a shorter name.');
		if(strlen($username) < 3)
			$err .= t(' Name too short.');
	}

	if($email != $a->user['email']) {
		$email_changed = true;
        if(! valid_email($email))
			$err .= t(' Not valid email.');
		if((x($a->config,'admin_email')) && (strcasecmp($email,$a->config['admin_email']) == 0)) {
			$err .= t(' Cannot change to that email.');
			$email = $a->user['email'];
		}
	}

	if(strlen($err)) {
		notice($err . EOL);
		return;
	}

	if($timezone != $a->user['timezone']) {
		if(strlen($timezone))
			date_default_timezone_set($timezone);
	}

	$str_group_allow   = perms2str($_POST['group_allow']);
	$str_contact_allow = perms2str($_POST['contact_allow']);
	$str_group_deny    = perms2str($_POST['group_deny']);
	$str_contact_deny  = perms2str($_POST['contact_deny']);

	$openidserver = $a->user['openidserver'];

	// If openid has changed or if there's an openid but no openidserver, try and discover it.

	if($openid != $a->user['openid'] || (strlen($openid) && (! strlen($openidserver)))) {
		$tmp_str = $openid;
		if(strlen($tmp_str) && validate_url($tmp_str)) {
			logger('updating openidserver');
			require_once('library/openid.php');
			$open_id_obj = new LightOpenID;
			$open_id_obj->identity = $openid;
			$openidserver = $open_id_obj->discover($open_id_obj->identity);
		}
		else
			$openidserver = '';
	}

	$r = q("UPDATE `user` SET `username` = '%s', `email` = '%s', `openid` = '%s', `timezone` = '%s',  `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s', `notify-flags` = %d, `page-flags` = %d, `default-location` = '%s', `allow_location` = %d, `theme` = '%s', `maxreq` = %d, `expire` = %d, `openidserver` = '%s', `blockwall` = %d, `hidewall` = %d  WHERE `uid` = %d LIMIT 1",
			dbesc($username),
			dbesc($email),
			dbesc($openid),
			dbesc($timezone),
			dbesc($str_contact_allow),
			dbesc($str_group_allow),
			dbesc($str_contact_deny),
			dbesc($str_group_deny),
			intval($notify),
			intval($page_flags),
			dbesc($defloc),
			intval($allow_location),
			dbesc($theme),
			intval($maxreq),
			intval($expire),
			dbesc($openidserver),
			intval($blockwall),
			intval($hidewall),
			intval(local_user())
	);
	if($r)
		info( t('Settings updated.') . EOL);

	$r = q("UPDATE `profile` 
		SET `publish` = %d, 
		`net-publish` = %d,
		`hide-friends` = %d
		WHERE `is-default` = 1 AND `uid` = %d LIMIT 1",
		intval($publish),
		intval($net_publish),
		intval($hide_friends),
		intval(local_user())
	);


	if($name_change) {
		q("UPDATE `contact` SET `name` = '%s', `name-date` = '%s' WHERE `uid` = %d AND `self` = 1 LIMIT 1",
			dbesc($username),
			dbesc(datetime_convert()),
			intval(local_user())
		);
	}		

	if($old_visibility != $net_publish) {
		// Update global directory in background
		$url = $_SESSION['my_url'];
		if($url && strlen(get_config('system','directory_submit_url')))
			proc_run('php',"include/directory.php","$url");
	}

	$_SESSION['theme'] = $theme;
	if($email_changed && $a->config['register_policy'] == REGISTER_VERIFY) {

		// FIXME - set to un-verified, blocked and redirect to logout

	}

	goaway($a->get_baseurl() . '/settings' );
	return; // NOTREACHED
}
		

if(! function_exists('settings_content')) {
function settings_content(&$a) {

	$o = '';
	$o .= '<script>	$(document).ready(function() { $(\'#nav-settings-link\').addClass(\'nav-selected\'); });</script>';

	if(! local_user()) {
		notice( t('Permission denied.') . EOL );
		return;
	}
	
	$tabtpl = get_markup_template("settings_tabs.tpl");
	$tabs = replace_macros($tabtpl, array(
		'$account' => array( t('Account settings'), $a->get_baseurl().'/settings'),
		'$plugins' => array( t('Plugin settings'), $a->get_baseurl().'/settings/addon')
	));
		
	

	if(($a->argc > 1) && ($a->argv[1] === 'addon')) {
		$settings_addons = "";
		
		$r = q("SELECT * FROM `hook` WHERE `hook` = 'plugin_settings' ");
		if(! count($r))
			$settings_addons = t('No Plugin settings configured');

		call_hooks('plugin_settings', $settings_addons);
		
		
		$tpl = get_markup_template("settings_addons.tpl");
		$o .= replace_macros($tpl, array(
			'$title'	=> t('Plugin Settings'),
			'$tabs'		=> $tabs,
			'$settings_addons' => $settings_addons
		));
		return $o;
	}
		
	require_once('include/acl_selectors.php');

	$p = q("SELECT * FROM `profile` WHERE `is-default` = 1 AND `uid` = %d LIMIT 1",
		intval(local_user())
	);
	if(count($p))
		$profile = $p[0];

	$username = $a->user['username'];
	$email    = $a->user['email'];
	$nickname = $a->user['nickname'];
	$timezone = $a->user['timezone'];
	$notify   = $a->user['notify-flags'];
	$defloc   = $a->user['default-location'];
	$openid   = $a->user['openid'];
	$maxreq   = $a->user['maxreq'];
	$expire   = ((intval($a->user['expire'])) ? $a->user['expire'] : '');
	$blockwall = $a->user['blockwall'];

	if(! strlen($a->user['timezone']))
		$timezone = date_default_timezone_get();


	$mail_disabled = ((function_exists('imap_open') && (! get_config('system','imap_disabled'))) ? 0 : 1);
	if(get_config('system','dfrn_only'))
		$mail_disabled = 1;

	if(! $mail_disabled) {
		$r = q("SELECT * FROM `mailacct` WHERE `uid` = %d LIMIT 1",
			local_user()
		);
	}
	else {
		$r = null;
	}

	$mail_server  = ((count($r)) ? $r[0]['server'] : '');
	$mail_port    = ((count($r) && intval($r[0]['port'])) ? intval($r[0]['port']) : '');
	$mail_ssl     = ((count($r)) ? $r[0]['ssltype'] : '');
	$mail_user    = ((count($r)) ? $r[0]['user'] : '');
	$mail_replyto = ((count($r)) ? $r[0]['reply_to'] : '');
	$mail_pubmail = ((count($r)) ? $r[0]['pubmail'] : 0);
	$mail_chk     = ((count($r)) ? $r[0]['last_check'] : '0000-00-00 00:00:00');

	$pageset_tpl = get_markup_template('pagetypes.tpl');
	$pagetype = replace_macros($pageset_tpl,array(
		'$page_normal' 	=> array('page-flags', t('Normal Account'), PAGE_NORMAL, 
									t('This account is a normal personal profile'), 
									($a->user['page-flags'] == PAGE_NORMAL)),
								
		'$page_soapbox' 	=> array('page-flags', t('Soapbox Account'), PAGE_SOAPBOX, 
									t('Automatically approve all connection/friend requests as read-only fans'), 
									($a->user['page-flags'] == PAGE_SOAPBOX)),
									
		'$page_community'	=> array('page-flags', t('Community/Celebrity Account'), PAGE_COMMUNITY, 
									t('Automatically approve all connection/friend requests as read-write fans'), 
									($a->user['page-flags'] == PAGE_COMMUNITY)),
									
		'$page_freelove' 	=> array('page-flags', t('Automatic Friend Account'), PAGE_FREELOVE, 
									t('Automatically approve all connection/friend requests as friends'), 
									($a->user['page-flags'] == PAGE_FREELOVE)),
	));

	$noid = get_config('system','no_openid');

	if($noid) {
		$openid_field = false;
	}
	else {
		$openid_field = array('openid_url', t('OpenID:'),$openid, t("\x28Optional\x29 Allow this OpenID to login to this account."));
	}


	$opt_tpl = get_markup_template("field_yesno.tpl");
	if(get_config('system','publish_all')) {
		$profile_in_dir = '<input type="hidden" name="profile_in_directory" value="1" />';
	}
	else {
		$profile_in_dir = replace_macros($opt_tpl,array(
			'$field' 	=> array('profile_in_directory', t('Publish your default profile in your local site directory?'), $profile['publish'], '', array(t('No'),t('Yes'))),
		));
	}

	if(strlen(get_config('system','directory_submit_url'))) {
		$profile_in_net_dir = replace_macros($opt_tpl,array(
			'$field' 	=> array('profile_in_netdirectory', t('Publish your default profile in the global social directory?'), $profile['net-publish'], '', array(t('No'),t('Yes'))),
		));
	}
	else
		$profile_in_net_dir = '';


	$hide_friends = replace_macros($opt_tpl,array(
			'$field' 	=> array('hide-friends', t('Hide your contact/friend list from viewers of your default profile?'), $profile['hide-friends'], '', array(t('No'),t('Yes'))),
	));

	$hide_wall = replace_macros($opt_tpl,array(
			'$field' 	=> array('hidewall',  t('Hide profile details and all your messages from unknown viewers?'), $a->user['hidewall'], '', array(t('No'),t('Yes'))),

	));


	$invisible = (((! $profile['publish']) && (! $profile['net-publish']))
		? true : false);

	if($invisible)
		info( t('Profile is <strong>not published</strong>.') . EOL );

	
	$default_theme = get_config('system','theme');
	if(! $default_theme)
		$default_theme = 'default';
	
	$themes = array();
	$files = glob('view/theme/*');
	if($files) {
		foreach($files as $file) {
			$f = basename($file);
			$theme_name = ((file_exists($file . '/experimental')) ?  sprintf("%s - \x28Experimental\x29", $f) : $f);
			$themes[$f]=$theme_name;
		}
	}
	$theme_selected = (!x($_SESSION,'theme')? $default_theme : $_SESSION['theme']);


	$subdir = ((strlen($a->get_path())) ? '<br />' . t('or') . ' ' . $a->get_baseurl() . '/profile/' . $nickname : '');

	$tpl_addr = get_markup_template("settings_nick_set.tpl");

	$prof_addr = replace_macros($tpl_addr,array(
		'$desc' => t('Your Identity Address is'),
		'$nickname' => $nickname,
		'$subdir' => $subdir,
		'$basepath' => $a->get_hostname()
	));

	$stpl = get_markup_template('settings.tpl');

	$celeb = ((($a->user['page-flags'] == PAGE_SOAPBOX) || ($a->user['page-flags'] == PAGE_COMMUNITY)) ? true : false);

	

	$o .= replace_macros($stpl,array(
		'$tabs' 	=> $tabs,
		'$ptitle' 	=> t('Account Settings'),

		'$submit' 	=> t('Submit'),
		'$baseurl' => $a->get_baseurl(),
		'$uid' => local_user(),
		
		'$nickname_block' => $prof_addr,
		'$uexport' => t('Export Personal Data'),
		
		
		'$h_pass' 	=> t('Password Settings'),
		'$password1'=> array('npassword', t('New Password:'), '', ''),
		'$password2'=> array('confirm', t('Confirm:'), '', t('Leave password fields blank unless changing')),
		'$oid_enable' => (! get_config('system','no_openid')),
		'$openid'	=> $openid_field,
		
		'$h_basic' 	=> t('Basic Settings'),
		'$username' => array('username',  t('Full Name:'), $username,''),
		'$email' 	=> array('email', t('Email Address:'), $email, ''),
		'$timezone' => array('timezone_select' , t('Your Timezone:'), select_timezone($timezone), ''),
		'$defloc'	=> array('defloc', t('Default Post Location:'), $defloc, ''),
		'$allowloc' => array('allow_location', t('Use Browser Location:'), ($a->user['allow_location'] == 1), ''),
		'$theme'	=> array('theme', t('Display Theme:'), $theme_selected, '', $themes),



		'$h_prv' 	=> t('Security and Privacy Settings'),

		'$maxreq' 	=> array('maxreq', t('Maximum Friend Requests/Day:'), $maxreq ,t("\x28to prevent spam abuse\x29")),
		'$permissions' => t('Default Post Permissions'),
		'$permdesc' => t("\x28click to open/close\x29"),
		'$visibility' => $profile['net-publish'],
		'$aclselect' => populate_acl($a->user,$celeb),

		'$blockwall'=> array('blockwall', t('Allow friends to post to your profile page:'), !$blockwall, ''),
		'$expire'	=> array('expire', t("Automatically expire posts after days:"), $expire, t('If empty, posts will not expire. Expired posts will be deleted')),

		'$profile_in_dir' => $profile_in_dir,
		'$profile_in_net_dir' => $profile_in_net_dir,
		'$hide_friends' => $hide_friends,
		'$hide_wall' => $hide_wall,
		
		
		
		'$h_not' 	=> t('Notification Settings'),
		'$lbl_not' 	=> t('Send a notification email when:'),
		'$notify1'	=> array('notify1', t('You receive an introduction'), ($notify & NOTIFY_INTRO), NOTIFY_INTRO, ''),
		'$notify2'	=> array('notify2', t('Your introductions are confirmed'), ($notify & NOTIFY_CONFIRM), NOTIFY_CONFIRM, ''),
		'$notify3'	=> array('notify3', t('Someone writes on your profile wall'), ($notify & NOTIFY_WALL), NOTIFY_WALL, ''),
		'$notify4'	=> array('notify4', t('Someone writes a followup comment'), ($notify & NOTIFY_COMMENT), NOTIFY_COMMENT, ''),
		'$notify5'	=> array('notify5', t('You receive a private message'), ($notify & NOTIFY_MAIL), NOTIFY_MAIL, ''),
		
		
		
		'$h_imap' => t('Email/Mailbox Setup'),
		'$imap_desc' => t("If you wish to communicate with email contacts using this service \x28optional\x29, please specify how to connect to your mailbox."),
		'$imap_lastcheck' => array('imap_lastcheck', t('Last successful email check:'), $imap_last_check,''),
		'$mail_disabled' => (($mail_disabled) ? t('Email access is disabled on this site.') : ''),
		'$mail_server'	=> array('mail_server',  t('IMAP server name:'), $mail_server, ''),
		'$mail_port'	=> array('mail_port', 	 t('IMAP port:'), $mail_port, ''),
		'$mail_ssl'		=> array('mail_ssl', 	 t('Security:'), strtoupper($mail_ssl), '', array( ''=>t('None'), 'TSL'=>'TSL', 'SSL'=>'SSL')),
		'$mail_user'	=> array('mail_user',    t('Email login name:'), $mail_user, ''),
		'$mail_pass'	=> array('mail_pass', 	 t('Email password:'), '', ''),
		'$mail_replyto'	=> array('mail_replyto', t('Reply-to address:'), '', 'Optional'),
		'$mail_pubmail'	=> array('mail_pubmail', t('Send public posts to all email contacts:'), $mail_pubmail, ''),
		
		
		
		
		'$h_advn' => t('Advanced Page Settings'),
		'$pagetype' => $pagetype,
		

		
		

		




		

	));

	call_hooks('settings_form',$o);

	$o .= '</form>' . "\r\n";

	return $o;

}}

