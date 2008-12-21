<?php

function my_page_header_prefix($page_title, $display_online_list)
{
	echo 'TEEST';
	phpbb::$template->assign_var('MY_TITLE', 'TEST');
}

function my_page_header_login($u_login_logout)
{
	echo "#".$u_login_logout."#";
	$u_login_logout = 2;
	echo 'HELLO33';
}

?>