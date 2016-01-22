<?php
!defined('SERVER_EXEC') && die('No access.');

// Required config keys
// $instagramAccessToken
// $instagramSearchCount
// instagramSearchHashtag

class InstagramHelper extends Helper
{
	public $accesstoken;

	public function authenticate()
	{
		if (!empty($accesstoken)) {
			$this->accesstoken = $accesstoken;
			return;
		}

		$code = $cookie->get('instagram-code');

		$returnUri = Lib::url('instagram', array('controller' => 'instagram'), true);

		if (empty($code)) {
			$url = 'https://api.instagram.com/oauth/authorize/?client_id=' . Config::$instagramClientId . '&scope=public_content&redirect_uri=' . urlencode($returnUri) . '&response_type=code';

			Lib::redirect($url, array(), true);
		}


		$fields = array(
			'client_id' => Config::$instagramClientId,
			'client_secret' => Config::$instagramClientSecret,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $returnUri,
			'code' => $code
		);

		$curl = curl_init('https://api.instagram.com/oauth/access_token');
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$output = curl_exec($curl);
		curl_close($curl);

		$data = json_decode($output);

		$cookie->delete('instagram-code');
		$cookie->set('instagram-accesstoken', $data->access_token);
		$this->accesstoken = $data->access_token;
	}

	public function getMeta($url)
	{
		$url = 'https://www.instagram.com/publicapi/oembed/?url=' . $url;

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);

		$data = json_decode($output);

		return $data;
	}

	public function search($options = array())
	{
		$fields = array_merge(array(
			'access_token' => Config::$instagramAccessToken,
			'count' => Config::$instagramSearchCount
		), $options);

		$url = 'https://api.instagram.com/v1/tags/' . Config::$instagramSearchHashtag . '/media/recent';

		$curl = curl_init($url . '?' . http_build_query($fields));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);

		$data = json_decode($output);

		return $data;
	}
}
