<?php

# Include SimplePie if it doesn't exist
if ( !class_exists('SimplePie') ) {
	if ( function_exists('fetch_feed') ) {
		require_once (ABSPATH . WPINC . '/class-feed.php');
	} else {
		require_once('simplepie.inc');
	}
}

/**
 * Handles interactions with Google Analytics' Stat API
 *
 * @author Spiral Web Consulting
 **/
class GoogleAnalyticsStats
{
	
	# Class variables
	var $baseFeed = 'https://www.google.com/analytics/feeds';
	var $accountId;
	var $token = false;
	
	/**
	 * Constructor
	 *
	 * @param user - the google account's username
	 * @param pass - the google account's password
	 **/
	function GoogleAnalyticsStats($user, $pass)
	{	
		# Encode the login details for sending over HTTP
		$user = urlencode($user);
		$pass = urlencode($pass);
		
		# Request authentication with Google
		$response = $this->curl('https://www.google.com/accounts/ClientLogin', 'accountType=GOOGLE&Email=' . $user . '&Passwd=' . $pass);
		
		# Get the authentication token
		$this->token = substr(strstr($response, "Auth="), 5);
	}
	
	/**
	 * Connects over cURL to get data
	 *
	 * @param url - url to request
	 * @param post - post data to pass through curl
	 * @return the raw curl response
	 **/
	function curl($url, $post = false, $header = 1)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_HEADER, $header);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_URL, $url);
		
		# Include the authentication token if known
		if ( $this->token ) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: GoogleLogin auth=' . $this->token));
		}
		
		# Include optional post fields
		if ( $post ) {
			$post .= '&service=analytics&source=wp-google-stats';
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}
		
		return curl_exec($curl);
	}
	
	/**
	 * Checks if the username and password worked by looking at the token
	 *
	 * @return Boolean if the login details worked
	 **/
	function checkLogin()
	{
		if ( $this->token != false )
			return true;
		else
			return false;
	}
	
	/**
	 * Sets the account id to use for queries
	 *
	 * @param id - the account id
	 **/
	function setAccount($id)
	{
		$this->accountId = $id;
	}
	
	/**
	 * Get a list of Analytics accounts
	 *
	 * @return a list of analytics accounts
	 **/
	function getAnalyticsAccounts()
	{		
		# Request the list of accounts
		$response = $this->curl($this->baseFeed . '/accounts/default', false, '0');
		
		# Check if the response received exists, else stop processing now
		if ( $response == '' )
			return array();
		
		# Parse the XML using SimplePie
		$simplePie = new SimplePie();
		$simplePie->set_raw_data($response);
		$simplePie->init();
		$simplePie->handle_content_type();
		$accounts = $simplePie->get_items();
		
		# Make an array of the accounts
		$ids = array();
		foreach ( $accounts AS $account ) {
			$id = array();
			
			$item_info = $account->get_item_tags('http://schemas.google.com/analytics/2009', 'tableId');
			
			$id['title'] = $account->get_title();
			$id['id'] = $item_info[0]['data'];
			
			$ids[] = $id;
		}
		
		return $ids;
	}
	
	/**
	 * Get a specific data metric
	 *
	 * @param metric - the metric to get
	 * @param startDate - the start date to get
	 * @param endDate - the end date to get
	 * @return the specific metric
	 **/
	function getMetric($metric, $startDate, $endDate)
	{
		# Ensure the start date is after Jan 1 2005
		$startDate = $this->verifyStartDate($startDate);
		
		# Request the list of accounts
		$response = $this->curl($this->baseFeed . "/data?ids=$this->accountId&start-date=$startDate&end-date=$endDate&metrics=$metric", false, '0');
		
		# Parse the XML using SimplePie
		$simplePie = new SimplePie();
		$simplePie->set_raw_data($response);
		$simplePie->init();
		$simplePie->handle_content_type();
		$datas = $simplePie->get_items();
	
		# Read out the data until the metric is found
		foreach ( $datas AS $data ) {
			$data_tag = $data->get_item_tags('http://schemas.google.com/analytics/2009', 'metric');
		 	return $data_tag[0]['attribs']['']['value'];
		}
	}
	
	/**
	 * Checks the date against Jan. 1 2005 because GA API only works until that date
	 *
	 * @param date - the date to compare
	 * @return the correct date
	 **/
	function verifyStartDate($date)
	{
		if ( strtotime($date) > strtotime('2005-01-01') )
			return $date;
		else
			return '2005-01-01';
	}
	
} // END class

?>