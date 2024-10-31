<?php
/*
Plugin Name: Obstcha
Plugin URI: http://www.mgvmedia.com/
Description: Decent comment verification to avoid spam without user interaction.
Version: 1.5
Author: georf
Author URI: http://www.mgvmedia.com
License: GPL3
*/

class Obstcha {
	// Background color
	private static $bgcolor = '444444';

	// Messages
	private static $messageCopy = 'Please copy the code of the image on the left side.';
	private static $messageError = 'Error: Please copy the captcha correct.';

	// directory for save the captcha codes
	private static $tempPath = 'temp/';

	// list of all avalible images with key
	private static $images = array(
		'a' => 'APFEL',
		'b' => 'BIRNE',
		'c' => 'BANANE',
		'd' => 'Dattel',
		'k' => 'Kirsche',
		'o' => 'ORANGE',
		'z' => 'Zitrone',
	);

	/**
	 * Add a field into the comment form
	 */
	public static function add_fields($fields) {
		if ( !is_user_logged_in() ) {

			$fields['Obstcha'] =
				'<noscript>'.
					'<p class="comment-form-captcha">'.
						'<label for="Obstcha_captcha"><img src="/security-image.444444.gif" alt=""/></label>'.
						'<input id="Obstcha_captcha" name="Obstcha_captcha" type="text" value=""/>'.
						'<small style="float:right;">'.self::$messageCopy.'</small>'.
					'</p>'.
				'</noscript>'.
				'<input id="Obstcha_captchaJs" name="Obstcha_captchaJs" type="hidden" value="NaN"/>'.
				'<script type="text/javascript">'."\n".
					'/* <!-- */'."\n".
						'document.getElementById(\'Obstcha_captchaJs\').value=(new Date).getFullYear()+\'.\'+((new Date).getMonth()+1);'."\n".
					'/* --> */'."\n".
				'</script>'."\n";
		}
		return $fields;
	}

	/**
	 * Checks the comment post for a correct captcha or javascript code
	 */
	public static function check_comment($commentdata) {

		if (defined('XMLRPC_REQUEST')) {
			return $commentdata;
		}

		if (!isset($commentdata['user_ID']) || $commentdata['user_ID'] == 0) {

			$captcha = false;

			$path = dirname(__FILE__).'/';
			$tempPath = $path.self::$tempPath;
			$filename = substr(md5($_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']), 0, 10).'.txt';

			if (is_file($tempPath.$filename)) {
				$captcha = file_get_contents($tempPath.$filename);
			} else {
				$captcha = false;
			}

			if (
				( isset($_POST['Obstcha_captcha']) && $_POST['Obstcha_captcha'] === $captcha )
			||
				( isset($_POST['Obstcha_captchaJs']) && $_POST['Obstcha_captchaJs'] == date('Y.n'))
			) {
				$commentdata['Obstcha_check'] = true;
				return $commentdata;
			} else {
				wp_die(self::$messageError);
			}
		}

		return $commentdata;
	}

	/**
	 * Set the comment to approved
	 */
	public static function change_approved($approved, $commentdata) {
		if (isset($commentdata['Obstcha_check'])) {
			return 1;
		}
		return $approved;
	}

	/**
	 * Generate the image and exit
	 */
	public static function security_image($color = null) {

		if (is_string($color) && strlen($color) === 6) {
			$frontcolor = ((hexdec($color)-8388607)<0)? 2 : 1;
		} else {
			$frontcolor=1;
			$color = 'FFFFFF';
		}

		// load random image with frontcolor
		$take_image = rand(0, count(self::$images));
		$i = 0;
		foreach (self::$images as $file => $key) {
			if ($take_image == $i) break;
			$i++;
		}

		$path = dirname(__FILE__).'/';
		$tempPath = $path.self::$tempPath;
		$pngPath = $path.'png/';

		$original_image = imageCreateFromPng($pngPath.$file.$frontcolor.'.png');
		$string_md5 = $key;

		//take size from image
		$original_image_width = imagesx( $original_image );
		$original_image_height = imagesy( $original_image );

		//create a new image
		$img = imagecreatetruecolor($original_image_width, $original_image_height);

		//create backgroundcolor from given $color
		$color = imagecolorallocate(
			$img,                         // handle
			hexdec(substr($color, 0, 2)), // red
			hexdec(substr($color, 2, 2)), // green
			hexdec(substr($color, 4, 2))  // blue
		);

		imagefill($img,0,0,$color);

		//copy random image on the given background
		imagecopy($img,$original_image,0,0,0,0,$original_image_width,$original_image_height);

		$radiusX = $original_image_width*0.4;
		$radiusY = $original_image_height*0.4;

		for ($i = 0,$max = rand(8,15); $i < $max; $i++) {

			$degree = rand(0,359);
			$x1 = sin(deg2rad($degree)) * $radiusX + $original_image_width/2;
			$y1 = cos(deg2rad($degree)) * $radiusY + $original_image_height/2;


			$degree = rand(0,359);
			$x2 = sin(deg2rad($degree)) * $radiusX + $original_image_width/2;
			$y2 = cos(deg2rad($degree)) * $radiusY + $original_image_height/2;

			$color = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
			imageline($img, $x1, $y1, $x2, $y2, $color);
		}

		//output image to browser
		header('Content-type: image/gif');
		imagegif($img);

		// write content in a temp-file
		file_put_contents(
			$tempPath.substr(md5($_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']), 0, 10).'.txt',
			$string_md5);

		// delete old files, to get free space on the server
		$v = opendir($tempPath);
		while ($file = readdir($v)) {
			if (is_file($tempPath.$file) && filemtime($tempPath.$file) < time()-7200) {
				unlink($tempPath.$file);
			}
		}
		closedir($v);
		exit();
	}
}


// wp-includes/comment-template.php:1532 in function comment_form
add_filter( 'comment_form_default_fields',	array( 'Obstcha', 'add_fields'));

// wp-includes/comment.php:1341 in function wp_new_comment
add_filter( 'preprocess_comment',			array( 'Obstcha', 'check_comment'));

// wp-includes/comment.php:642 in function wp_allow_comment
add_filter( 'pre_comment_approved',			array( 'Obstcha', 'change_approved'), 10, 2);


/*
 * Get all request like
 * ^/security-image.([0-9a-fA-F]{6}).gif$
 * ^/security-image.gif$
 * to our image
 */
if (strlen($_SERVER['REQUEST_URI']) > 16 && substr($_SERVER['REQUEST_URI'], 0, 16) === '/security-image.') {

	if ('/security-image.gif' === $_SERVER['REQUEST_URI']) {
		Obstcha::security_image();
	} elseif (preg_match('|^/security-image.([0-9a-fA-F]{6}).gif$|', $_SERVER['REQUEST_URI'], $return)) {
		Obstcha::security_image($return[1]);
	}
}

?>
