<?php
/**
* Copyright (c) 2016, Leon Sorokin
* All rights reserved. (MIT Licensed)
*
* MimeParser.php
* Minimal wrapper for https://pecl.php.net/package/mailparse
*/

function decodePart($input) {
	return iconv_mime_decode($input, 0, 'UTF-8');
}

class MimeParser {
	protected $mime;
	protected $struct;
	protected $parts = [];

	public function __get($name) {
		return $this->$name;
	}

	protected function procParts() {
		$this->struct = mailparse_msg_get_structure($this->mime);

		// todo? recursive build 1.1, 1.1.2
		foreach($this->struct as $num)	{
			$handle = mailparse_msg_get_part($this->mime, $num);
			$info = mailparse_msg_get_part_data($handle);

			$this->parts[$num] = [
				'info' => $info,
				'handle' => $handle,
			];
		}
	}

	public function header($name) {
		$header = $this->parts['1']['info']['headers'][$name] ?? '';
		if (is_array($header))
			$header = $header[0];
		return decodePart($header);
	}

	public function html() {
		foreach ($this->parts as $part)
			if ($part['info']['content-type'] == 'text/html')
				return mb_convert_encoding($this->getBody($part['handle']), "utf-8", $part['info']['charset']);
		return null;
	}

	public function text() {
		foreach ($this->parts as $part)
			if ($part['info']['content-type'] == 'text/plain')
				return mb_convert_encoding($this->getBody($part['handle']), "utf-8", $part['info']['charset']);
		return null;
	}

	// withInline?
	public function attachments() {
		return [];
	}

	public function saveAttachments($path = './') {
		foreach ($this->parts as $part) {
			$filename = $part['info']['disposition-filename'] ?? null;
			if ($filename)
				file_put_contents($path . decodePart($filename), $this->getBody($part['handle']));
		}
	}

	public function free() {
		mailparse_msg_free($this->mime);
	}
}

class FileParser extends MimeParser {
	protected $filename;

	public function __construct($filename) {
		$this->filename = $filename;
		$this->mime = mailparse_msg_parse_file($filename);
		$this->procParts();
	}

	protected function getBody($handle) {
		ob_start();
		mailparse_msg_extract_part_file($handle, $this->filename);
		return ob_get_clean();
	}
}

class MemParser extends MimeParser {
	protected $data;

	public function __construct($data) {
		$this->data = $data;
		$this->mime = mailparse_msg_create();
		mailparse_msg_parse($this->mime, $data);
		$this->procParts();
	}

	protected function getBody($handle) {
		ob_start();
		mailparse_msg_extract_part($handle, $this->data);
		return ob_get_clean();
	}
}