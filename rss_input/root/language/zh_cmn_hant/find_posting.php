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
* @language Traditional Chinese [zh_cmn_hant]
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'BB_AUTHOR'				=> '發表人：[color=brown]%s[/color]' . "\n" ,
	'BB_CAT'					=> '[size=125]分類：[color=darkred]%s[/color][/size]' . "\n",
	'BB_CHANNEL'			=> '[size=125]%s[/size]',
	'BB_COPYRIGHT'			=> '[size=85]版權訊息：[color=#808000]%s[/color][/size]' . "\n",
	'BB_CHANNEL_DESC'		=> '[size=85][color=indigo]%s[/color][/size]' . "\n",
	'BB_CHANNEL_DATE'		=> '消息於 [color=green]%s[/color] 發佈',
	'BB_CHANNEL_UPDATE'	=> '消息於 [color=green]%s[/color] 更新',
	'BB_POST_AT'			=> '於 [color=green]%s[/color] 發表',
	'BB_TITLE'				=> '[color=darkblue][size=150]%s[/size][/color]' . "\n",
	'BB_UPDATE_AT'			=> '於 [color=green]%s[/color] 更新',
	'BB_URL'					=> '[url=%s]%s[/url]',

	'COMMENTS'	=> '[i]發表意見[/i]',
	'HR'			=> "\n--------\n",	// Horizontal line.
	'READ_MORE'	=> '[i]閱讀全文[/i]',
	'TAB'			=> ' - ',	// Tab.
	'TRUNCATE'	=> '﹍',		// Truncate string.
));

?>
