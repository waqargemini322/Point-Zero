<?php

if ($_POST["status"] > -1) {
	$c = $_POST["field1"];
	$c = explode("|", $c);

	$uid = $c[0];
	$tm = $c[1];

	//---------------------------------------------------

	$paid_mem_date = get_user_meta($uid, "paid_mem_date" . $tm . $uid, true);

	if (empty($paid_mem_date)) {
		//$tm = current_time('timestamp',0);

		update_user_meta($uid, "membership_available", $tm + 24 * 30 * 3600);
		update_user_meta($uid, "paid_mem_date" . $tm . $uid, "1");

		$projectTheme_monthly_nr_of_bids = get_option("projectTheme_monthly_nr_of_bids");
		if (empty($projectTheme_monthly_nr_of_bids)) {
			$projectTheme_monthly_nr_of_bids = 10;
		}

		update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $projectTheme_monthly_nr_of_bids);

		//--------------------------------------------

		$projectTheme_monthly_nr_of_projects = get_option("projectTheme_monthly_nr_of_projects");
		if (empty($projectTheme_monthly_nr_of_projects)) {
			$projectTheme_monthly_nr_of_projects = 10;
		}

		update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $projectTheme_monthly_nr_of_projects);
	}
}

?>