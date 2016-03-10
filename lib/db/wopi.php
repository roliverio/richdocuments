<?php

/**
 * ownCloud - Richdocuments App
 *
 * @author Ashod Nakashian
 * @copyright 2016 Ashod Nakashian ashod.nakashian@collabora.co.uk
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments\Db;

use \OCA\Richdocuments\Download;
use \OCA\Richdocuments\DownloadResponse;

/**
 * @method string generateFileToken()
 * @method string getPathForToken()
 */

class Wopi extends \OCA\Richdocuments\Db{

	const DB_TABLE = '`*PREFIX*richdocuments_wopi`';

	// Tokens expire after this many seconds (not defined by WOPI specs).
	const TOKEN_LIFETIME_SECONDS = 30 * 60;

	protected $tableName  = '`*PREFIX*richdocuments_wopi`';

	protected $insertStatement  = 'INSERT INTO `*PREFIX*richdocuments_wopi` (`uid`, `fileid`, `path`, `token`, `expiry`)
			VALUES (?, ?, ?, ?, ?)';

	protected $loadStatement = 'SELECT * FROM `*PREFIX*richdocuments_wopi` WHERE `token`= ?';

	/*
	 * Given a fileId, generates a token
	 * and stores in the database.
	 * Returns the token.
	 */
	public function generateFileToken($fileId){
		$user = \OC_User::getUser();
		$view = new \OC\Files\View('/' . $user . '/');
		$path = $view->getPath($fileId);

		if (!$view->is_file($path)) {
			throw new \Exception('Invalid fileId.');
		}

		$token = \OC::$server->getSecureRandom()->getMediumStrengthGenerator()->generate(32,
					\OCP\Security\ISecureRandom::CHAR_LOWER . \OCP\Security\ISecureRandom::CHAR_UPPER .
					\OCP\Security\ISecureRandom::CHAR_DIGITS);

		\OC::$server->getLogger()->debug('Issuing token for {user} file {fileId}: {token}',
										 [ 'user' => $user, 'fileId' => $fileId, 'token' => $token ]);

		$wopi = new \OCA\Richdocuments\Db\Wopi([
			$user,
			$fileId,
			$path,
			$token,
			time() + self::TOKEN_LIFETIME_SECONDS
		]);

		if (!$wopi->insert()){
			throw new \Exception('Failed to add wopi token into database');
		}

		return $token;
	}

	/*
	 * Given a token, validates it and
	 * constructs and validates the path.
	 * Returns the path, if valid, else false.
	 */
	public function getPathForToken($fileId, $token){

		$wopi = new Wopi();
		$row = $wopi->loadBy('token', $token)->getData();
		\OC::$server->getLogger()->debug('Loaded WOPI Token record: {row}.', [ 'row' => $row ]);

		//TODO: validate.
		if ($row['expiry'] > time() || $row['fileid'] !== $fileId){
			// Expired token!
			//$wopi->deleteBy('id', $row['id']);
			//return false;
		}

		$user = $row['uid'];
		$view = new \OC\Files\View('/' . $user . '/');
		$path = $row['path'];

		if (!$view->is_file($path)) {
			throw new \Exception('Invalid file path.');
		}

		return array('user' => $user, 'path' => $path);
	}
}
