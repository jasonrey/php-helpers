<?php
!defined('SERVER_EXEC') && die('No access.');

class SlackHelper extends Helper
{
	private static $apiBase = 'https://slack.com/api';
	private static $hookBase = 'https://hooks.slack.com/services';

	public static function getApiLink($action)
	{
		return self::$apiBase . '/' . $action . '?token=' . Config::$slackApiToken;
	}

	public static function getHookLink()
	{
		return self::$hookBase . '/' . Config::$slackHookToken;
	}

	public function getUsers()
	{
		$target = self::getApiLink('users.list');

		$curl = curl_init($target);

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($curl);

		curl_close($curl);

		$data = json_decode($result);

		if (empty($data->ok)) {
			$this->error = $data->error;
			return false;
		}

		$users = array();

		foreach ($data->members as $user) {
			if (empty($user->profile->email)) {
				continue;
			}

			$users[] = (object) array(
				'id' => $user->id,
				'team_id' => $user->team_id,
				'name' => $user->name,
				'email' => $user->profile->email
			);
		}

		return $users;
	}

	public function send($data = array())
	{
		$message = $this->newMessage();

		foreach ($data as $key => $value) {
			$message->$key = $value;
		}

		return $message->send();
	}

	public function newMessage()
	{
		$interface = new SlackMessage;
		return $interface;
	}
}

class SlackMessage
{
	/*
	$options = array(
		'channel' => '',
		'username' => '',
		'text' => '',
		'icon_emoji' => '',
		'icon_url' => '',
		'attachments' => array()
	);

	$attachment = array(
		'fallback' => '',
		'pretext' => '',
		'color' => '',
		'fields' => array(
			array(
				'title' => '',
				'value' => '',
				'short' => false
			)
		)
	);
	*/
	public $channel;
	public $username;
	public $text;
	public $icon_emoji;
	public $icon_url;
	public $attachments = array();

	public function to($userid)
	{
		$userTable = Lib::table('user');
		$userTable->load($userid);

		$table = Lib::table('slackuser');
		if ($table->load(array('email' => $userTable->email))) {
			$this->channel = '@' . $table->name;
		}
	}

	public function message($text)
	{
		$this->text = $text;
	}

	public function send()
	{
		$curl = curl_init(SlackHelper::getHookLink());

		$data = array();

		$childClass = get_called_class();
		$newObject = new $childClass;
		$allowedKeys = array_keys(get_object_vars($newObject));

		foreach ($allowedKeys as $key) {
			if (!empty($this->$key)) {
				$data[$key] = $this->$key;
			}
		}

		$fields = array(
			'payload' => json_encode($data)
		);

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));

		if (version_compare(PHP_VERSION, '5.5.0') < 0) {
			curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
		}

		$output = curl_exec($curl);

		return true;
	}

	public function newAttachment($options = array())
	{
		$attachment = new SlackAttachment($options);

		$this->attachments[] = $attachment;

		return $attachment;
	}
}

class SlackAttachment
{
	public $fallback;
	public $color;
	public $pretext;

	public $author_name;
	public $author_link;
	public $author_icon;

	public $title;
	public $title_link;

	public $text;

	public $fields = array();

	public $image_url;
	public $thumb_url;

	public function newField($title = null, $value = null, $short = false)
	{
		$field = new SlackAttachmentField($title, $value, $short);

		$this->fields[] = $field;

		return $field;
	}
}

class SlackAttachmentField
{
	public $title;
	public $value;
	public $short;

	public function __construct($title = null, $value = null, $short = false)
	{
		if (isset($title)) {
			$this->title = $title;
		}

		if (isset($value)) {
			$this->value = $value;
		}

		if (isset($short)) {
			$this->short = $short;
		}
	}
}
