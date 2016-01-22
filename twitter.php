<?php
!defined('SERVER_EXEC') && die('No access.');

// Required config keys
// $twitterApiKey
// $twitterApiSecret
// $twitterSearchHashtag
// $twitterSearchMode
// $twitterSearchCount

class TwitterHelper extends Helper
{
	public $accesstoken;

	public function init()
	{
		if (empty($this->accesstoken)) {
			$this->authenticate();
		}
	}

	public function authenticate()
	{
		$apikey = Config::$twitterApiKey;
		$apisecret = Config::$twitterApiSecret;
		$api = base64_encode($apikey . ':' . $apisecret);

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			'Authorization: Basic ' . $api,
			'Content-Length: 29'
		);

		$postdata = 'grant_type=client_credentials';

		$ch = curl_init('https://api.twitter.com/oauth2/token');

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);

		$output = curl_exec($ch);
		curl_close($ch);

		if ($output === false) {
			return array();
		}

		$response = json_decode($output);

		if (isset($response->errors)) {
			return false;
		}

		if (!isset($response->token_type) || $response->token_type !== 'bearer') {
			return false;
		}

		$this->accesstoken = $response->access_token;

		return true;
	}

	public function search($options = array())
	{
		$default = array(
			'q' => Config::$twitterSearchHashtag,
			'result_type' => Config::$twitterSearchMode,
			'count' => Config::$twitterSearchCount
		);

		$options = array_merge($default, $options);

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			'Authorization: Bearer ' . $this->accesstoken
		);

		$apiurl = 'https://api.twitter.com/1.1/search/tweets.json';

		$ch = curl_init($apiurl . '?' . http_build_query($options));

		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$output = curl_exec($ch);
		curl_close($ch);

		if ($output === false) {
			return array();
		}

		$response = json_decode($output);

		if (!isset($response->statuses)) {
			return array();
		}

		return $response->statuses;
	}

	public function embed($id, $options = array())
	{
		$default = array(
			'maxwidth' => 550,
			'hide_media' => true,
			'hide_thread' => true,
			'omit_script' => false
		);

		$options = array_merge($default, $options);

		$options['id'] = $id;

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			'Authorization: Bearer ' . $this->accesstoken
		);

		$apiurl = 'https://api.twitter.com/1.1/statuses/oembed.json';

		$ch = curl_init($apiurl . '?' . http_build_query($options));

		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$output = curl_exec($ch);
		curl_close($ch);

		if ($output === false) {
			return array();
		}

		$response = json_decode($output);

		if (!isset($response->html)) {
			return array();
		}

		return $response;
	}

	public function resolve($url)
	{
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_exec($ch);

		return curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	}
}
