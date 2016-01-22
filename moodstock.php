<?php
!defined('SERVER_EXEC') && die('No access.');

// Required config keys
// $moodstockApiKey
// $moodstockApiSecret

class MoodstockHelper extends Helper
{
	private $baseurl = 'http://api.moodstocks.com/v2';
	private $resizeWidth = 480;
	private $resizeHeight = 480;

	private function getOptions($options = array())
	{
		$base = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
			CURLOPT_USERPWD => Config::$moodstockApiKey . ':' . Config::$moodstockApiSecret
		);

		return $base + $options;
	}

	private function execute($options)
	{
		$options = $this->getOptions($options);

		try {
			$curl = curl_init();

			curl_setopt_array($curl, $options);

			$rawResponse = curl_exec($curl);
			$response = json_decode($rawResponse);

			curl_close($curl);

			return $response;
		} catch (Exception $error) {
			return (object) array(
				'error' => true,
				'message' => $error->getMessage()
			);
		}
	}

	public function ping()
	{
		$options = array(
			CURLOPT_URL => $this->baseurl . '/echo'
		);

		$response = $this->execute($options);

		return $response;
	}

	private function resizeImage($file, $maxWidth, $maxHeight, $crop = false)
	{
		list($width, $height) = getimagesize($file);
		$ratio = $width / $height;

		if ($crop) {
			if ($width > $height) {
				$width = ceil($width - ($width * abs($ratio - $maxWidth / $maxHeight)));
			} else {
				$height = ceil($height - ($height * abs($ratio - $maxWidth / $maxHeight)));
			}
			$newWidth = $maxWidth;
			$newHeight = $maxHeight;
		} else {
			if ($maxWidth / $maxHeight > $ratio) {
				$newWidth = $maxHeight * $ratio;
				$newHeight = $maxHeight;
			} else {
				$newHeight = $maxWidth / $ratio;
				$newWidth = $maxWidth;
			}
		}

		$source = imagecreatefromjpeg($file);
		$newImage = imagecreatetruecolor($newWidth, $newHeight);
		imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

		return $newImage;
	}

	public function searchByFile($path)
	{
		$resizedImage = $this->resizeImage($path, $this->resizeWidth, $this->resizeHeight);

		if (!file_exists(Config::getBasePath() . '/' . TMP_FOLDER)) {
			mkdir(Config::getBasePath() . '/' . TMP_FOLDER, 0777, true);
		}

		error_reporting(E_ALL & ~E_DEPRECATED);

		$resizedFile = Config::getBasePath() . '/' . TMP_FOLDER . '/' . $this->resizeWidth . 'x' . $this->resizeHeight . '-' . basename($path);
		imagejpeg($resizedImage, $resizedFile, 100);

		$options = array(
			CURLOPT_POST => true,
			CURLOPT_SAFE_UPLOAD => false,
			CURLOPT_URL => $this->baseurl . '/search',
			CURLOPT_POSTFIELDS => array('image_file' => '@' . $resizedFile)
		);

		$response = $this->execute($options);

		return $response;
	}

	public function searchByUrl($url)
	{
		$options = array(
			CURLOPT_POST => true,
			CURLOPT_URL => $this->baseurl . '/search',
			CURLOPT_POSTFIELDS => array('image_url' => $url)
		);

		$response = $this->execute($options);

		return $response;
	}

	public function search($target)
	{
		$method = strpos($target, 'http') === 0 ? 'searchByUrl' : 'searchByFile';

		$response = $this->$method($target);

		if (!empty($response->error) || empty($response->found)) {
			return false;
		}

		// The file id has been precoded to be <name>-<index>, we can safely remove the index to find the match
		$segments = explode('-', $response->id);
		$index = array_pop($segments);
		$productName = implode('-', $segments);

		return $productName;
	}
}
