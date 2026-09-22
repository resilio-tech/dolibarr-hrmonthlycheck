<?php
/* Copyright (C) 2026 Resilio SA
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       class/ZulipNotifier.class.php
 * \ingroup    hrmonthlycheck
 * \brief      Send messages to Zulip using the Bot API.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

/**
 * Class ZulipNotifier
 *
 * Posts a message via a bot account (POST {site}/api/v1/messages with HTTP basic auth bot-email:api-key).
 */
class ZulipNotifier
{
	/** @var string Zulip site base URL (e.g. https://org.zulipchat.com) */
	private $site;

	/** @var string Bot email */
	private $botEmail;

	/** @var string Bot API key */
	private $apiKey;

	/** @var string Last error message */
	private $error = '';

	/**
	 * Constructor
	 *
	 * @param string $site     Zulip site base URL
	 * @param string $botEmail Bot email
	 * @param string $apiKey   Bot API key
	 */
	public function __construct(string $site, string $botEmail, string $apiKey)
	{
		$this->site = rtrim($site, '/');
		$this->botEmail = $botEmail;
		$this->apiKey = $apiKey;
	}

	/**
	 * Build a notifier from the module setup, or null if incomplete.
	 *
	 * @return ZulipNotifier|null
	 */
	public static function fromConf(): ?ZulipNotifier
	{
		$site = getDolGlobalString('HRMONTHLYCHECK_ZULIP_SITE');
		$email = getDolGlobalString('HRMONTHLYCHECK_ZULIP_BOT_EMAIL');
		$key = getDolGlobalString('HRMONTHLYCHECK_ZULIP_BOT_APIKEY');

		if ($site === '' || $email === '' || $key === '') {
			return null;
		}

		return new self($site, $email, dolDecrypt($key));
	}

	/**
	 * Send a message to a stream/topic.
	 *
	 * @param string $stream  Stream name
	 * @param string $topic   Topic
	 * @param string $content Message content (Zulip Markdown)
	 * @return bool True on success
	 */
	public function sendStream(string $stream, string $topic, string $content): bool
	{
		if ($stream === '') {
			$this->error = 'Zulip stream is empty';
			return false;
		}

		return $this->post(array('type' => 'stream', 'to' => $stream, 'topic' => $topic, 'content' => $content));
	}

	/**
	 * Send a direct message to a Zulip user.
	 *
	 * @param string $email   Zulip email of the recipient
	 * @param string $content Message content (Zulip Markdown)
	 * @return bool True on success
	 */
	public function sendDirect(string $email, string $content): bool
	{
		return $this->post(array('type' => 'private', 'to' => json_encode(array($email)), 'content' => $content));
	}

	/**
	 * Post a message to the Zulip API.
	 *
	 * @param array<string,string> $params Message parameters
	 * @return bool True on success
	 */
	private function post(array $params): bool
	{
		$this->error = '';

		$result = getURLContent(
			$this->site.'/api/v1/messages',
			'POST',
			http_build_query($params),
			1,
			array('Authorization: Basic '.base64_encode($this->botEmail.':'.$this->apiKey)),
			array('https'),
			2
		);

		$decoded = json_decode((string) $result['content'], true);
		if ((int) $result['http_code'] !== 200 || !is_array($decoded) || ($decoded['result'] ?? '') !== 'success') {
			$this->error = 'Zulip API error: '.(is_array($decoded) && isset($decoded['msg']) ? $decoded['msg'] : ($result['curl_error_msg'] ?: 'HTTP '.$result['http_code']));
			dol_syslog('HRMONTHLYCHECK: '.$this->error, LOG_ERR);
			return false;
		}

		return true;
	}

	/**
	 * Last error message.
	 *
	 * @return string
	 */
	public function getError(): string
	{
		return $this->error;
	}
}
