<?php
/**
*
* @author PoPoutdoor
*
* @package RSS_input
* @version $Id:$
* @copyright (c) 2008-2011 PoPoutdoor
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package functions_find
*/
function prepare_xml($url, $recode, $feedname, &$msg)
{
	global $user;

	if (function_exists('simplexml_load_file') && ini_get('allow_url_fopen'))
	{
		//libxml_use_internal_errors(true);
		$xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
	}
	// no supporting methods, issue error message
	else
	{
		$msg['err'][] = $user->lang['NO_PHP_SUPPORT'];
		return false;
	}


// Debug
		// check compliance
		$is_rss = isset($xml->channel) ? TRUE : FALSE;

		$validate = ($is_rss) ? ( !is_null($xml->channel->title) && !is_null($xml->channel->description) && !is_null($xml->channel->link) ) : ( !is_null($xml->id) && !is_null($xml->title) && !is_null($xml->updated) );

		if (!$validate)
		{
			// Not validate, issue error
			$msg['err'][] = sprintf($user->lang['NO_CHANNEL'], $feedname);
//			continue;
return false;
		}

		// check source timestamp
		$now = time();

		if ($is_rss)
		{
			$ts = ( isset($xml->channel->lastBuildDate) ) ? $xml->channel->lastBuildDate : $xml->channel->pubDate;
		}
		else
		{
			// RFC3339 to RFC 822
			// convert atom style timestamp (format: '2006-12-27T22:00:00-04:00')
			$ts = preg_replace('/([0-9]{4}-[0-9]{2}-[0-9]{2})T(.*)([+|-][0-9]{2}):/is', "$1 $2 $3", $xml->updated);
		}

		$epoch = strtotime($ts);
		// previous to PHP 5.1.0, strtotime() error is -1
		$ts = ($epoch === false) ? $now : $epoch;

		// RSS ttl support
		$ttl = ( isset($xml->channel->ttl) ) ? $ts + $xml->channel->ttl * 60 : 0;

		if ( $ts <= $last_update || $ttl >= $now)
		{
			// source not updated, skip to next
			$msg['skip'][] = sprintf($user->lang['FEED_NONE'], $feedname);
//			continue;
return false;
		}
//var_dump($is_rss);
		$feed = ($is_rss) ? $xml->xpath('//item') : $xml->entry;

		$i = 0;
		$items = array();

		foreach ($feed as $item)
		{
			// Respect item limit setting
			if ($item_limit > 0 AND $i++ === $item_limit)
			{
				break;
			}

			$items[] = (array) $item;
		}

/* BBC Chinese: 
		[id] => tag:www.bbcchinese.com,2013-07-27:26109504 
		[category] => Array	(
			[0] => SimpleXMLElement Object			(
				[@attributes] => Array				(
					[term] => chinese_traditional 
					[label] => chinese_traditional ) ) 
			[1] => SimpleXMLElement Object			(
				[@attributes] => Array				(
					[term] => world 
					[label] => 國際 ) ) ) 
		[link] => Array	(
			[0] => SimpleXMLElement Object			(
				[@attributes] => Array			(
					[rel] => alternate 
					[type] => text/html 
					[title] => story 
					[href] => http://www.bbc.co.uk/zhongwen/trad/world/2013/07/130727_singer_weibo.shtml ) ) 
			[1] => SimpleXMLElement Object			(
				[@attributes] => Array			(
					[rel] => related 
					[type] => text/html 
					[title] => story 
					[href] => http://www.bbc.co.uk/zhongwen/trad/china/2013/07/130726_huanqiu_comments_control.shtml ) )
			[2] => SimpleXMLElement Object			(
				[@attributes] => Array			(
					[rel] => related 
					[type] => text/html 
					[title] => story 
					[href] => http://www.bbc.co.uk/zhongwen/trad/press_review/2013/07/130723_press_china.shtml ) ) 
			[3] => SimpleXMLElement Object			(
				[@attributes] => Array			(
					[rel] => related 
					[type] => text/html 
					[title] => story 
					[href] => http://www.bbc.co.uk/zhongwen/trad/china/2013/07/130722_iv_beijing_aiport_violence.shtml ) ) ) )
*/
			// Loop through the list of items, up to the limit.
//			for ($i; $i >= 0; $i--)
//			{
/*
			$this->_item['title']			= $this->_filter($this->_item['title']);
			$this->_item['link']				= $this->_item['link'];
			$this->_item['description']	= $this->_filter($this->_item['description'], true);
			$this->_item['author']			= $this->_filter($this->_item['author']);
			$this->_item['category']		= $this->_filter($this->_item['category']);
			$this->_item['comments']		= $this->_item['comments'];
			$this->_item['pubDate']			= $this->_date2unix($this->_item['pubDate']);
			$this->_item['update']			= $this->_date2unix($this->_item['update']);
*/
				// ATOM only
				$msg['err'][] = $items[$i]['updated'];
				$msg['err'][] = $items[$i]['published'];
				$msg['err'][] = $items[$i]['summary'];
				$msg['err'][] = $items[$i]['content'];
				// RSS only
				$msg['err'][] = $items[$i]['pubDate'];
				$msg['err'][] = $items[$i]['description'];
				// Both
				$msg['err'][] = $items[$i]['title'];
				$msg['err'][] = $items[$i]['link'];
//			}


//print_r($items);
$msg['err'][] = 'debug';
return false;

	// null page?
	if (empty($xml))
	{
		$msg['err'][] = sprintf($user->lang['FILE_NULL'], $feedname, $url);
		return false;
	}

	// Validate feed, try to fix xml header if not validated.
	// xml 1.0 spec, encoding: http://www.w3.org/TR/REC-xml/#dt-docent
	if (!preg_match('#^<\?xml\s+version=["\']1\.[0-9]+["\'][^>]*>#is', $xml, $valid_xml_tag))
	{
		if (empty($recode))
		{
			$message = sprintf($user->lang['NOT_VALID_XML'], $feedname);
			$error = true;
		}
		// Force recode, patch xml header
		elseif (!preg_match('#^<\?xml\s[^>]*>#is', $xml, $xml_header))
		{
			$message = sprintf($user->lang['NO_XML_TAG'], $feedname);
			$xml = '<?xml version="1.0" encoding="UTF-8"?><!-- XML header added by RSS Import //-->' . "\n$xml";
		}
		else
		{
			$xml = str_replace($xml_header[0], '<?xml version="1.0" encoding="UTF-8"?><!-- XML header patched by RSS Import //-->' . "\n", $xml);
		}
	}
	else
	{
		if (!preg_match('#encoding=["\'](.*?)["\']#is', $valid_xml_tag[0], $match))
		{
			if (empty($recode))
			{
				$message = sprintf($user->lang['NO_ENCODINGS'], $feedname);
				$error = true;
			}
			else
			{
				$xml = str_replace($valid_xml_tag[0], '<?xml version="1.0" encoding="UTF-8"?><!-- XML header replaced by RSS Import //-->' ."\n", $xml);
			}
		}
		elseif (empty($recode))
		{
			$encoding = strtolower($match[1]);
		}

		$xml = str_replace($match[0], 'encoding="UTF-8"', $xml);
	}

	//	source related error, view source only in ACP
	if (isset($message))
	{
		$msg['err'][] = $message . "\n\n";
		$msg['err'][] = (class_exists('acp_find')) ? utf8_htmlspecialchars(utf8_substr($xml, 0, 800) . $user->lang['TRUNCATE']) . "\n\n" : '';
		if (isset($error))
		{
			return false;
		}
	}

	if (!empty($recode))
	{
		$encoding = $recode;
	}

	// Convert source to UTF-8
	if ($encoding != 'utf-8')
	{
		// should check for unsupport encodings
		$xml = utf8_recode($xml, $encoding);
	}

//--- $xml in UTF-8 from here ---//

	// normalize and strip all control characters but "\n"
	$xml = str_replace("\n", '[\n]', $xml);
	$xml = preg_replace('#(?:[\x00-\x1F\x7F]+|(?:\xC2[\x80-\x9F])+)#', '', $xml);
	$xml = utf8_normalize_nfc($xml);
	$xml = str_replace('[\n]', "\n", $xml);

	// try to convert some ATOM tags to RSS format
	if (strpos($xml, '<feed') !== false)
	{
		switch ($url)
		{
			// BBC Chinese - http://www.bbcchinese.com
			case 'http://www.bbc.co.uk/zhongwen/trad/index.xml':

				if (preg_match_all('#<category[^>]*(?:label="(?!chinese_traditional).*?([^"]+)").+?>#uis', $xml, $match, PREG_PATTERN_ORDER))
				{
					$i = 0;
					foreach ($match[0] as $not_used => $cat)
					{
						$xml = str_replace($cat, '<category>' . $match[1][$i] . '</category>', $xml);
						$i++;
					}
				}

				// convert the <link...rel="alternate"...href="url"...>...</link> tag to <link>url</link>
				if (preg_match_all('#<link[^>]*(?:rel="alternate").+?(?:href="(https?[^"]+)").+?</link>#is', $xml, $match, PREG_PATTERN_ORDER))
				{
					$i = 0;
					foreach ($match[0] as $not_used => $link)
					{
						$xml = str_replace($link, '<link>' . $match[1][$i] . '</link>', $xml);
						$i++;
					}
				}
			break;

			default:
				// Do conversion which safe for all or do nothing;
			break;
		}
	}

	return $xml;
}

function get_rss_content($sql_ids = '')
{
	global $phpbb_root_path, $phpEx, $db, $user;
	global $is_cjk;	// This pass local vars to called functions

	$user->add_lang('find');

	if (empty($sql_ids))
	{
		trigger_error('NO_IDS');
	}

	include($phpbb_root_path . 'includes/rss_parser.'.$phpEx);

	// init return messages
	$msg = array('ok' => '', 'skip' => '', 'err' => '');

	$sql = 'SELECT f.*, u.user_colour, u.user_lang, b.bot_active, b.bot_name, b.bot_ip, n.forum_name
		FROM ' . FIND_TABLE . ' f, '  . USERS_TABLE . ' u, ' . BOTS_TABLE . ' b,' . FORUMS_TABLE . " n 
		WHERE f.feed_id $sql_ids
			AND u.user_id = b.user_id
			AND f.bot_id = b.user_id
			AND n.forum_id = f.post_forum 
		ORDER BY f.post_forum ASC";
	$result = $db->sql_query($sql);

	// fetch news from selected feeds
	while ($row = $db->sql_fetchrow($result))
	{
		$feedname = $row['feedname'];

		// feed not active, skipped to next
		if (!$row['status'])
		{
			$msg['skip'][] = sprintf($user->lang['NOT_ACTIVE'], $feedname);
			continue;
		}

		// strip off bot name identification string
		$bot_name = trim(str_replace(FIND_BOT_ID, '', $row['bot_name']));

		// Bot not active, skipped to next feed
		if (!$row['bot_active'])
		{
			$msg['skip'][] = sprintf($user->lang['BOT_NOT_ACTIVE'], $bot_name);
			continue;
		}

		// Set CJK flag by bot language
		$is_cjk = false;
		if (defined('FIND_CJK') && function_exists('cjk_tidy'))
		{
			$ary = explode(',', FIND_CJK);
			$is_cjk = (in_array($row['user_lang'] , $ary)) ? true : false;
		}

		// prepare some vars
		$feed_id				= $row['feed_id'];
		$feedname_topic	= $row['feedname_topic'];
		$last_update		= $row['last_import'];
		$item_limit			= $row['post_items'];
		$post_limit			= $row['post_contents'];
		$inc_channel		= $row['inc_channel'];
		$inc_cat				= $row['inc_cat'];
		$inc_html			= $row['feed_html'];
		$topic_ttl			= $row['topic_ttl'];
		$forum_id			= $row['post_forum'];
		$forum_name			= $row['forum_name'];

		$user->ip							= $row['bot_ip'];
		$user->data['user_id']			= $row['bot_id'];	// also used for update forum tracking
		$user->data['username']			= $bot_name;
		$user->data['user_colour']		= $row['user_colour'];
		$user->data['is_registered']	= 1;	// also used for update forum tracking

		$xml = prepare_xml($row['url'], $row['encodings'], $feedname, $msg);
		if ($xml === false)
		{
			continue;
		}

		$parser = new rss_parser();
		if ($parser->parse($xml))
		{
			// check compliance
			if (empty($parser->channel['title']) && empty($parser->channel['desc']) && empty($parser->channel['link']))
			{
				// Not validate, issue error
				$msg['err'][] = sprintf($user->lang['NO_CHANNEL'], $feedname);
				continue;
			}

			$now = time();

			// Use channel timestamp for feed update check
			$channel_date	= $parser->channel['date'];
			$channel_update = $parser->channel['update'];

			if (!empty($channel_date) && !empty($channel_update))
			{
				$channel_ts = ($channel_update >= $channel_date) ? $channel_update : 0;
			}
			else
			{
				$channel_ts = (!empty($channel_update)) ? $channel_update : ((!empty($channel_date)) ? $channel_date : $now );
			}

			// source/server clock not in sync, diff +30 seconds
			if (empty($channel_ts) || $channel_ts > ($now + 30))
			{
					$msg['err'][] = sprintf($user->lang['CLOCK_ISSUE'], $feedname);
					continue;
			}

			// skip feed if TTL not expires
			$channel_ttl = (!empty($channel_date)) ? $parser->channel['ttl'] * 60 : 0;
			if (($channel_ts + $channel_ttl) >= ($now + 30))
			{
				$msg['skip'][] = sprintf($user->lang['FEED_NONE'], $feedname);
				continue;
			}

			// Determine number of news items to process
			$item_ary = $parser->items;
			$i = sizeof($item_ary);
			if (empty($i))
			{
				$msg['err'][] = sprintf($user->lang['NO_ITEMS'], $feedname);
				continue;
			}

			// Respect item limit setting
			if (!empty($item_limit) && $item_limit < $i)
			{
				$i = $item_limit;
			}
			$i--;

			// Simple test to determind sort by 'pubDate' or 'update' timestamp
			if (!empty($item_ary[0]['update']))
			{
				// Atom: What to do if some entry got update but some not?
				usort($item_ary, '_update_desc');
				$latest_ts = (int) $item_ary[0]['update'];
			}
			else
			{
				usort($item_ary, '_pubDate_desc');
				$latest_ts = (int) $item_ary[0]['pubDate'];
			}

			// message body vars
			$processed = $skipped = 0;
			if (empty($topic_ttl))
			{
				$post_ary = array();
			}
			else
			{
				$contents = '';
			}

			// Loop through the list of items, up to the limit.
			for ($i; $i >= 0; $i--)
			{
				// Check if any updates.
				$item_date = $item_ary[$i]['pubDate'];
				$item_update = $item_ary[$i]['update'];
				if (!empty($item_date) && !empty($item_update))
				{
					$item_ts = ($item_update >= $item_date) ? $item_update : 0;
				}
				else
				{
					$item_ts = (!empty($item_update)) ? $item_update : ( (!empty($item_date)) ? $item_date : $now );
				}

				if (empty($item_ts) || $item_ts > ($now + 30))
				{
					$msg['err'][] = sprintf($user->lang['CLOCK_ISSUE'], $feedname);
					continue;
				}

				// skip to next item if outdated
				if ($last_update >= $item_ts)
				{
					$skipped++;
					continue;
				}

				// We use Bot user language from here
				$user->lang_name = $row['user_lang'];
				$user->add_lang('find_posting');

				$title = $item_ary[$i]['title'];
				$desc = $item_ary[$i]['description'];
				if (empty($title) && empty($desc))
				{
					// Not validate, issue error
					$msg['err'][] = sprintf($user->lang['NO_ITEM_INFO'], $feedname);
					continue;
				}

				// Check to see if we have a subject - some atom feeds like blogspot provide an empty title.
				$item_title = (!empty($title)) ? utf8_tidy($title) : utf8_tidy($desc);
				$item_title = truncate_string($item_title, 60, 255, false, $user->lang['TRUNCATE']);

				// prepare the message text
				$message = bb_message(
					$item_ary[$i],
					$post_limit,
					$inc_html,
					$inc_cat
				);

				if (empty($topic_ttl))
				{
					$post_ary[] = array($item_title, $message);
				}
				else
				{
					$contents .= "\n" . sprintf($user->lang['BB_TITLE'], $item_title) . $message;
				}

				$processed++;
			} // end process items

			if ($processed)
			{
				$heading = $feed_info = '';

				// should we include the channel info
				if (!empty($inc_channel))
				{
					$channel			= utf8_tidy($parser->channel['title']);
					$channel_desc	= utf8_tidy($parser->channel['desc']);
					$channel_link	= fix_url($parser->channel['link']);
					$image_url		= fix_url($parser->img_url);
					$image_link		= fix_url($parser->img_link);

					$heading .= (!empty($image_url)) ? sprintf($user->lang['BB_URL'], $image_link, "[img]${image_url}[/img]") : (!empty($channel_link) ? sprintf($user->lang['BB_URL'], $channel_link, sprintf($user->lang['BB_CHANNEL'], $channel)) : sprintf($user->lang['BB_CHANNEL'], $channel));
					$heading .= "\n";

					if (!empty($channel_desc) && $channel_desc != $channel)
					{
						$heading .= sprintf($user->lang['BB_CHANNEL_DESC'], $channel_desc);
					}
				}

				// Always show the copyright notice if provided
				$channel_rights	= utf8_tidy($parser->channel['rights']);
				$feed_info .= (!empty($channel_rights)) ? sprintf($user->lang['BB_COPYRIGHT'], $channel_rights) : '';

				if (!empty($channel_date) && !empty($channel_update))
				{
					$feed_info .= sprintf($user->lang['BB_CHANNEL_DATE'], $user->format_date($channel_date)) . $user->lang['TAB'] . sprintf($user->lang['BB_CHANNEL_UPDATE'], $user->format_date($channel_update)) . $user->lang['HR'];
				}
				else
				{
					$feed_info .= (!empty($channel_date)) ? sprintf($user->lang['BB_CHANNEL_DATE'], $user->format_date($channel_date)) . $user->lang['HR'] : ((!empty($channel_update)) ? sprintf($user->lang['BB_CHANNEL_UPDATE'], $user->format_date($channel_update)) . $user->lang['HR'] : '');
				}

				// submit each item as new post or reply
				if (empty($topic_ttl))
				{
					foreach ($post_ary as $not_used => $data)
					{
						list($subject, $post_contents) = $data;
						// If subject already post, make this as reply
						$sql = 'SELECT topic_id 
							FROM ' . TOPICS_TABLE . "
							WHERE topic_title = '" . $db->sql_escape($subject) . "'
								AND forum_id = " . (int) $forum_id . "
							ORDER BY topic_id DESC LIMIT 1";
						$query = $db->sql_query($sql);
						$topic_id = $db->sql_fetchfield('topic_id');
						$db->sql_freeresult($query);

						$mode = (empty($topic_id))? 'post' : 'reply';
						rss_autopost($forum_id, $forum_name, $mode, $subject, $heading . $feed_info . $post_contents);
					}
				}
				// pack all items and additional info in one post
				else
				{
					// should we use the acp feedname as subject?
					$subject = $feedname;
					if (empty($feedname_topic))
					{
						$subject = (!empty($channel)) ? $channel : ((!empty($channel_desc)) ? $channel_desc : $feedname);
					}
					$subject = truncate_string($subject, 60, 255, false, $user->lang['TRUNCATE']);

					// Get topic id and topic time
					$sql = 'SELECT topic_id, topic_time
						FROM ' . TOPICS_TABLE . "
						WHERE topic_title = '" . $db->sql_escape($subject) . "'
							AND forum_id = " . (int) $forum_id . "
						ORDER BY topic_id DESC LIMIT 1";
					$query		= $db->sql_query($sql);
					$topic_row	= $db->sql_fetchrow($query);

					$topic_id	= $topic_row['topic_id'];
					$topic_time	= $topic_row['topic_time'];

					$db->sql_freeresult($query);

					// New topic if first import
					if (empty($topic_id))
					{
						$mode = 'post';
					}
					else
					{
						if ($topic_ttl == 1)	// new topic on each day
						{
							$mode = ($user->format_date($latest_ts, 'd', true) == $user->format_date($topic_time, 'd', true)) ? 'reply' : 'post';
						}
						else	// new topic on each week/month
						{
							$format = ($topic_ttl == 30) ? 'm' : 'W';
							$mode = ($user->format_date($latest_ts, $format, true) <= $user->format_date($topic_time, $format, true)) ? 'reply' : 'post';
						}
					}

					$contents = $feed_info . $contents;

					// include headings for new post
					if ($mode == 'post')
					{
						$contents = $heading . $contents;
					}

					rss_autopost($forum_id, $forum_name, $mode, $subject, $contents, $topic_id);
				}

				// update feed last visit time
				$sql = 'UPDATE ' . FIND_TABLE . '
					SET last_import = ' . $latest_ts . "
					WHERE feed_id = $feed_id";
				$update_result = $db->sql_query($sql);
				$db->sql_freeresult($update_result);

				$msg['ok'][] = sprintf($user->lang['FEED_OK'], $feedname, $processed);
			}

			// message for skipped items
			if ($skipped)
			{
				$msg['skip'][] = sprintf($user->lang['FEED_SKIP'], $feedname, $skipped);
			}
		}
		else
		{
			$error = $parser->parser_error;
			if (!empty($error))
			{
				$msg['err'][] = sprintf($user->lang['FEED_ERR'], $feedname, $error[0], $error[1], $error[2], $error[3], $error[4]);
			}
		}

		$parser->destroy();
		//unset($parser);
	}

	$db->sql_freeresult($result);

	return $msg;
}

/*
	usort functions for rss_get_contents()
*/
// Order item array in DESC by key 'update'
function _update_desc($a, $b)
{
	if ($a['update'] == $b['update'])
	{
		return 0;
	}
	return (intval($a['update']) > intval($b['update']))? -1 : 1;
}
// Order item array in DESC by key 'update'
function _pubDate_desc($a, $b)
{
	if ($a['pubDate'] == $b['pubDate'])
	{
		return 0;
	}
	return (intval($a['pubDate']) > intval($b['pubDate']))? -1 : 1;
}



/**
*	Convert html tags to BBCode, supported tags are: strong,b,u,em,i,ul,ol,li,img,a,p (11)
*/
function html2bb(&$html)
{
	// <strong>...</strong>, <b>...</b>, <u>...</u>, <em>...</em>, <i>...</i>, <p>...</p>, <li>...</li>, <ul>...</ul>, </ol>
	//	to [b]...[/b], [b]...[/b], [u]...[/u], [i]...[/i], [i]...[/i], \n\n...\n\n, [*]...\n, [list]...[/list], [/list]
	$search = array('<strong>', '</strong>', '<b>', '</b>', '<u>', '</u>', '<em>', '</em>', '<i>', '</i>',
		'<p>', '</p>', '<li>', '</li>', '<ul>', '</ul>', '</ol>',
	);
	$replace = array('[b]', '[/b]', '[b]', '[/b]', '[u]', '[/u]', '[i]', '[/i]', '[i]', '[/i]',
		"\n\n", "\n\n", '[*]', "\n", '[list]', '[/list]', '[/list]',
	);
	$html = str_replace($search, $replace, $html);
	$html = preg_replace('#<ul\s.*?>#is', '[list]', $html);	// <ul ...>
	$html = preg_replace('#<ol\s+type="(\w+)">#is', '[list=\\1]', $html);	// <ol ...> to [list=...]
	$html = preg_replace('#<ol\s+style=".*?decimal">#is', '[list=1]', $html);	//detect <ol style="list-style-type: decimal">
	$html = preg_replace('#<p\s.*?>#is', "\n\n", $html);	// <p ...>
	$html = preg_replace('#<li\s.*?>#is', '[*]', $html);	// <li ...>

	// process <img> tags
	if (preg_match_all('#<img[^>]*(?:src="(http[^"]+\.(?:gif|jp[2g]|png|xbm))).+?>#is', $html, $tag, PREG_PATTERN_ORDER))
	{
		global $config;

		$i = 0;
		foreach ($tag[0] as $not_used => $img_tag)
		{
			$url = fix_url($tag[1][$i]);
			$bbcode = '[img]' . $url . '[/img]';
			// Note: This is from function bbcode_img()
			// if the embeded image exceeds limits, return $url
			if (($config['max_post_img_height'] || $config['max_post_img_width']) && ini_get('allow_url_fopen'))
			{
				$stats = @getimagesize($url);

				if ($stats !== false)
				{
					if ($config['max_post_img_height'] && $config['max_post_img_height'] < $stats[1]
						 || $config['max_post_img_width'] && $config['max_post_img_width'] < $stats[0])
					{
						$bbcode = $url;
					}
				}
			}

			$html = str_replace($img_tag, $bbcode, $html);
			$i++;
		}
	}

	// process <a> tags
	if (preg_match_all('#<a[^>]*(?:href="(http[^"]+)).+? >(.+?)</a>#is', $html, $tag, PREG_PATTERN_ORDER))
	{
		$i = 0;
		foreach ($tag[0] as $not_used => $a_tag)
		{
			$url = fix_url($tag[1][$i]);
			$txt = str_replace(array(' ', "\n"), '', $tag[2][$i]);

			if (empty($txt))	// [url]link[/url]
			{
				$bbcode = "[url]${url}[/url]";
			}
			else	// [url=link]text[/url]
			{
				$bbcode = "[url=${url}]${txt}[/url]";
			}

			$html = str_replace($a_tag, $bbcode, $html);
			$i++;
		}
	}

	$html = preg_replace("#\n{3,}#", "\n\n", $html);

	return;
}



/**
*	URL filter
*/
function fix_url($url)
{
	$url = trim($url);

	// Strip yahoo redirect links
	$pos = strpos($url, '*http://');
	if ($pos !== false)
	{
		$url = substr($url, $pos+1);
	}

	// Additional filters below
	$url = str_replace(' ', '%20', $url);

	return $url;
}



/**
*	Format text with supported html tags to BBCode
*/
function bb_message($item, $limits, $is_html, $is_cat)
{
	global $user, $is_cjk;

	$message = '';

	$category	= utf8_tidy($item['category']);
	$pubDate		= $item['pubDate'];
	$update		= $item['update'];
	$author		= utf8_tidy($item['author']);
	$contents	= $item['description'];
	$link			= fix_url($item['link']);
	$comments	= fix_url($item['comments']);

	if ($is_cat && !empty($category))
	{
		$message .= sprintf($user->lang['BB_CAT'], $category);
	}

	// Check to see if publish/update date have been provided.
	if (!empty($pubDate) && !empty($update))
	{
		$message .= ($update > $pubDate) ? sprintf($user->lang['BB_POST_AT'], $user->format_date($pubDate)) . $user->lang['TAB'] . sprintf($user->lang['BB_UPDATE_AT'], $user->format_date($update)) . "\n" : sprintf($user->lang['BB_POST_AT'], $user->format_date($pubDate)) . "\n";
	}
	else
	{
		$message .= (!empty($pubDate)) ? sprintf($user->lang['BB_POST_AT'], $user->format_date($pubDate)) . "\n" : ((!empty($update)) ? sprintf($user->lang['BB_UPDATE_AT'], $user->format_date($update)) . "\n" : '');
	}

	// Insert the author information.
	$message .= (!empty($author)) ? sprintf($user->lang['BB_AUTHOR'], $author): '';

	// Now we add the content if provided.
	if (!empty($contents))
	{
		if ($is_html)
		{
			html2bb($contents);
		}

		$contents = strip_tags($contents);

		$item_content = utf8_tidy($contents, true);
		// Contents filter
		if (defined('FIND_STRIP'))
		{
			$item_content = str_replace(explode(',', FIND_STRIP), '', $item_content);	// Strip defined patterns
		}

		if (!empty($limits))
		{
			$item_content = truncate_string($item_content, $limits, 255, false, $user->lang['TRUNCATE']);
		}

		$message .= "\n" . $item_content . "\n";
	}

	if (!empty($link) && !empty($comments))
	{
		$message .= "\n" . sprintf($user->lang['BB_URL'], $link, $user->lang['READ_MORE']) . $user->lang['TAB'] . sprintf($user->lang['BB_URL'], $comments, $user->lang['COMMENTS']) . "\n";
	}
	else
	{
		$message .= (!empty($link)) ? "\n" . sprintf($user->lang['BB_URL'], $link, $user->lang['READ_MORE']) . "\n" : ((!empty($comments)) ? "\n" . sprintf($user->lang['BB_URL'], $comments, $user->lang['COMMENTS']) . "\n" : '');
	}

	return $message . "\n";
}



/**
*	Cleanup spacing and newlines, with aditional spacing fix-ups for CJK text
*/
function utf8_tidy($text, $newline = false)
{
	global $is_cjk;

	if (function_exists('cjk_tidy') && $is_cjk)
	{
		$text = cjk_tidy($text);
	}

	$text = ($newline) ? preg_replace("#\n{3,}#", "\n\n", $text) : str_replace("\n", '', $text);

	return trim($text);
}



/**
* Bot Submit Post
*
* $mode: post/reply, 'approve' always "true'.
* No edit, no poll, no attachment, no quote, no globalising, no indexing
*	no notifications. tracking/markread for poster.
*/
function rss_autopost($forum_id, $forum_name, $mode, $subject, $message, $topic_id = 0)
{
	global $user, $phpEx, $phpbb_root_path;

	if (!function_exists('submit_post'))
	{
		include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
	}

	// censor_text()
	$subject = censor_text($subject);
	$message = censor_text($message);

	// variables to hold the parameters
	$uid = $bitfield = $options = '';
	generate_text_for_storage($subject, $uid, $bitfield, $options, false, false, false);
	generate_text_for_storage($message, $uid, $bitfield, $options, true, true, true);

	$icon_id = (defined('FIND_ICON')) ? FIND_ICON : false;

	// prepare $data array
	$data = array(
		'forum_id'			=> $forum_id,
		'icon_id'			=> $icon_id,
		'enable_bbcode'	=> 1,
		'enable_smilies'	=> 0,
		'enable_urls'		=> 1,
		'enable_sig'		=> 0,
		'enable_indexing'	=> 0,	// do not index
		'notify_set'		=> 0, // do not notify user
		'notify'				=> 0, // do not notify user
		'bbcode_bitfield'	=> $bitfield,
		'bbcode_uid'		=> $uid,
		'poster_ip'			=> $user->ip,	// set to post bot ip
		'message'			=> $message,
		'message_md5'		=> md5($message),
		'topic_id'			=> $topic_id,	// used only for 'reply', auto set when 'post'
		'topic_title'		=> $subject,	// not used for post/reply, only for user_notification
		'forum_name'		=> $forum_name,	// not used for post/reply, only for user_notification

		'force_approved_state'	=> 1,	// set TRUE to force set approval
		'post_edit_locked'		=> 1
	);

	$not_used = submit_post($mode, $subject, '', POST_NORMAL, $poll, $data);

	return;
}

?>
