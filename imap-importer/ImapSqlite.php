<?php
/**
* Copyright (c) 2016, Leon Sorokin
* All rights reserved. (MIT Licensed)
*
* ImapSqlite.php
* IMAP -> SQLite importer, attachment extractor
*/

function log2($msg, $repl = false) {
	$end = microtime(true);
	$diff = $end - START;
	$ms = parseMs($diff);

	$msg = gmdate("H:i:s.$ms - ", $diff) . $msg;

	echo $msg . ($repl ? "\r" : "\n");
}

function parseMs($floatSecs) {
	return str_pad(round(fmod($floatSecs, 1) * 1000), 3, "0", STR_PAD_LEFT);
}

function initDB($name, $opts) {
	// unlink(DB_NAME);

	$db = new PDO($name, null, null, $opts);

	$db->query("DROP TABLE IF EXISTS fts_sender");
	$db->query("DROP TABLE IF EXISTS fts_recipient");
	$db->query("DROP TABLE IF EXISTS fts_subject");
	$db->query("DROP TABLE IF EXISTS fts_content");
	$db->query("DROP TABLE IF EXISTS message");
	$db->query("VACUUM");

	$db->query("
		CREATE TABLE message (
			message_id  TEXT,
			hash        TEXT,
			date        INTEGER,
			received    INTEGER,
			subject     TEXT,
			sender      TEXT,
			[from]      TEXT,
			[to]        TEXT,
			cc          TEXT,
			bcc         TEXT,
			reply_to    TEXT,
			content     TEXT,
			embedded    INTEGER,
			attached    INTEGER,
			size        INTEGER,
			thread_root INTEGER,
			thread_lvl  INTEGER,
			thread_lft  INTEGER,
			thread_rgt  INTEGER,
			thread_par  INTEGER
		)
	");

	$db->query("CREATE INDEX message_id  ON message (message_id)");
	$db->query("CREATE INDEX hash        ON message (hash)");
	$db->query("CREATE INDEX received    ON message (received)");
	$db->query("CREATE INDEX date        ON message (date)");
	$db->query("CREATE INDEX size        ON message (size)");
	$db->query("CREATE INDEX attached    ON message (attached)");
	$db->query("CREATE INDEX embedded    ON message (embedded)");
	$db->query("CREATE INDEX thread_root ON message (thread_root)");
	$db->query("CREATE INDEX thread_lvl  ON message (thread_lvl)");
	$db->query("CREATE INDEX thread_lft  ON message (thread_lft)");
	$db->query("CREATE INDEX thread_par  ON message (thread_par)");

	return $db;
}

function fetchMail($db, $threads) {
	foreach (IMAP_BOXES as $box)
		fetchBox($box, $db, $threads, FETCH_QTY);
}

// this exists to reset the imap connection every $batch_size messages because imap_fetchbody leaks memory >:(
function fetchBoxPartial($path, $db, &$threads, &$uids, $idx, $batch_size, $full_qty) {
	$imap = imap_open(IMAP_ROOT . $path, IMAP_USER, IMAP_PASS);

	for ($j = 0; $j < $batch_size; $j++) {
		log2($idx + 1 . " / $full_qty (#$uids[$idx])", true);

		$raw = imap_fetchbody($imap, $uids[$idx++], "", FT_UID | FT_PEEK);

		$parser = new MemParser($raw);

		$msg_id = $parser->header('message-id');

		$hash = hash('md5', $msg_id);

		$parts = str_split($hash , 2);

		$path = __DIR__ . "/data/{$parts[0]}/$parts[1]/" . $hash;

		if (!file_exists($path)) {
			$size = mb_strlen($raw, '8bit');

			procMessage($parser, $db, $threads, $msg_id, $hash, $size);

			mkdir($path, 0777, true);

			$parser->saveAttachments($path . "/");

			file_put_contents("$path/raw.eml", $raw);
		}

	//	unset($raw);
	//	$parser->free();
	//	unset($parser);

		/*
		$header = imap_headerinfo($imap, $i);
		$raw_body = imap_body($imap, $i);
		print_r($header);
		*/
	}

	imap_close($imap);
}

function fetchBox($path, &$db, &$threads, $full_qty = 200) {
	$imap = imap_open(IMAP_ROOT . $path, IMAP_USER, IMAP_PASS);
	$uids = imap_sort($imap, SORTARRIVAL, 1, SE_UID | SE_NOPREFETCH);

	$total = imap_num_msg($imap);

	$full_qty = min($full_qty, $total);

	log2("Fetching last $full_qty msgs from $path ($total total)...");

	imap_close($imap);

	// download in batches with imap reconnects cause imap_fetchbody leaks mem
	$idx = 0;
	$batch_size = 100;

	while ($idx < $full_qty) {
		fetchBoxPartial($path, $db, $threads, $uids, $idx, $batch_size, $full_qty);
		$idx += $batch_size;
	}

	echo "\n";
}

function linkThreads($db, &$threads) {
	log2("Linking threads...");

	$db->query("BEGIN");
	foreach ($threads as $msg_id => &$root) {
		NestedSet::enumTree($root);
		setThreadFields($db, $root, $msg_id, $msg_id);
	}
	$db->query("COMMIT");
}

function buildIndices($db) {
	log2("Creating indices...");

	$db->query("CREATE VIRTUAL TABLE fts_subject USING fts5(subject, content='message', content_rowid='rowid')");
	$db->query("INSERT INTO fts_subject(fts_subject) VALUES('rebuild')");

	$db->query("CREATE VIRTUAL TABLE fts_content USING fts5(content, content='message', content_rowid='rowid')");
	$db->query("INSERT INTO fts_content(fts_content) VALUES('rebuild')");

	$db->query("CREATE VIRTUAL TABLE fts_sender USING fts5(`from`, sender, content='message', content_rowid='rowid')");
	$db->query("INSERT INTO fts_sender(fts_sender) VALUES('rebuild')");

	$db->query("CREATE VIRTUAL TABLE fts_recipient USING fts5(`to`, cc, bcc, content='message', content_rowid='rowid')");
	$db->query("INSERT INTO fts_recipient(fts_recipient) VALUES('rebuild')");

	$db->query("
		CREATE TRIGGER message_ai_fts AFTER INSERT ON message
		BEGIN
		  INSERT INTO fts_subject(rowid, subject) VALUES (new.rowid, new.subject);

		  INSERT INTO fts_content(rowid, content) VALUES (new.rowid, new.content);

		  INSERT INTO fts_sender(rowid, `from`, sender) VALUES (new.rowid, new.`from`, new.sender);

		  INSERT INTO fts_recipient(rowid, `to`, cc, bcc) VALUES (new.rowid, new.`to`, new.cc, new.bcc);
		END;
	");

	$db->query("
		CREATE TRIGGER message_ad_fts AFTER DELETE ON message
		BEGIN
		  INSERT INTO fts_subject(fts_subject, rowid, subject) VALUES('delete', old.rowid, old.subject);

		  INSERT INTO fts_content(fts_content, rowid, content) VALUES('delete', old.rowid, old.content);

		  INSERT INTO fts_sender(fts_sender, rowid, `from`, sender) VALUES ('delete', old.rowid, old.`from`, old.sender);

		  INSERT INTO fts_recipient(fts_recipient, rowid, `to`, cc, bcc) VALUES ('delete', old.rowid, old.`to`, old.cc, old.bcc);
		END;
	");

	$db->query("
		CREATE TRIGGER message_au_fts AFTER UPDATE ON message
		WHEN
		  new.subject	!= old.subject OR
		  new.content	!= old.content OR

		  new.`to`		!= old.`to` OR
		  new.cc		!= old.cc OR
		  new.bcc		!= old.bcc OR

		  new.`from`	!= old.`from` OR
		  new.sender	!= old.sender OR

		  new.rowid		!= old.rowid
		BEGIN
		  INSERT INTO fts_subject(fts_subject, rowid, subject) VALUES('delete', old.rowid, old.subject);
		  INSERT INTO fts_subject(rowid, subject) VALUES (new.rowid, new.subject);

		  INSERT INTO fts_content(fts_content, rowid, content) VALUES('delete', old.rowid, old.content);
		  INSERT INTO fts_content(rowid, content) VALUES (new.rowid, new.content);

		  INSERT INTO fts_sender(fts_sender, rowid, `from`, sender) VALUES ('delete', old.rowid, old.`from`, old.sender);
		  INSERT INTO fts_sender(rowid, `from`, sender) VALUES (new.rowid, new.`from`, new.sender);

		  INSERT INTO fts_recipient(fts_recipient, rowid, `to`, cc, bcc) VALUES ('delete', old.rowid, old.`to`, old.cc, old.bcc);
		  INSERT INTO fts_recipient(rowid, `to`, cc, bcc) VALUES (new.rowid, new.`to`, new.cc, new.bcc);
		END;
	");
}

function setThreadFields($db, $node, $root, $parent) {
	// TODO: pull out
	$stmt = $db->prepare('
		UPDATE
		  message
		SET
		  thread_root = (SELECT rowid FROM message WHERE message_id = :root),
		  thread_par = (SELECT rowid FROM message WHERE message_id = :parent),
		  thread_lvl = :lvl,
		  thread_lft = :lft,
		  thread_rgt = :rgt
		WHERE
		  message_id = :message_id
	');

	$stmt->execute([
		'root'		=> $root,
		'parent'	=> $parent,
		'lvl'		=> $node->lvl,
		'lft'		=> $node->lft,
		'rgt'		=> $node->rgt,
		'message_id'=> $node->message_id,
	]);

	foreach ($node->kids as $node2)
		setThreadFields($db, $node2, $root, $node->message_id);
}

function procMessage($parser, &$db, &$threads, $msg_id, $hash, $size) {
	$msg = [
	//	'rowid'		=> null,
		'message_id'=> $msg_id,
		'hash'		=> $hash,
		'date'		=> strtotime($parser->header('date')),
		'received'	=> null,
		'sender'	=> $parser->header('sender'),
	//	'return_path'=> $Parser->getHeader('return-path'),
	//	'in_reply_to'=> $Parser->getHeader('in-reply-to'),
		'from'		=> $parser->header('from'),
		'subject'	=> $parser->header('subject'),
		'to'		=> $parser->header('to'),
		'cc'		=> $parser->header('cc'),
		'bcc'		=> $parser->header('bcc'),
		'reply_to'	=> $parser->header('reply-to'),
		'content'	=> trim($parser->text()),
		'embedded'	=> null,
		'attached'	=> null,
		'size'		=> $size
	];

	// to externally?
	$attachments = $parser->attachments();		// include inline/embedded
	$num_attached = count($attachments);

	if ($num_attached > 0) {
		$msg['attached'] = $num_attached;

		// detach
	}

	// TODO: process embedded

	$smtp = strtok($parser->header('received'), ";");
	$msg['received'] = strtotime(trim(strtok(";")));

	if ($msg['content'] == '') {
		$conv = new \Html2Text\Html2Text($parser->html());
		$msg['content'] = trim($conv->getText());
	}

	// we're gonna store thread structures in memory during import
	// then resolve and hook them up at the end
	$refs = $parser->header('references');

	// create/update thread tree
	if ($refs != '') {
		$refs = explode(" ", $refs);
		$refs[] = $msg_id;

		$targ = &$threads;
		while ($key = array_shift($refs)) {
			$targ[$key] = $targ[$key] ?? (object)['message_id' => $key, 'kids' => []];
			$targ = &$targ[$key]->kids;
		}
	}

	// TODO: pull PREPARE code out of loop for query planner
	$flds = [];
	$plcs = [];
	foreach ($msg as $fld => &$val) {
		if ($val == '')
			$val = null;

		$flds[] = "`$fld`";
		$plcs[] = ":$fld";
	}

	$stmt = $db->prepare('INSERT INTO message (' . implode(",", $flds) . ') VALUES (' . implode(",", $plcs) . ')');

	if (!$stmt)
		print_r($db->errorInfo());
	else
		$stmt->execute($msg);
}