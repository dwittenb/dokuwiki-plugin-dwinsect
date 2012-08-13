<?php
/**
 * This plugin allows to insert a section of a page
 * The section can be shown in different ways:
 * 1. The section is shown as it is like a simple include
 * 2. The first link in the section is shown (direct or with the parameters used)
 * 3. The text before an after the link is shown as a footnote or plain text
 * It can be used to reference one link or text on multiple pages 
 * It is usefull to reference to one links that are often updatet
 *   
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Dietrich Wittenberg <info.wittenberg@online.de>
 */
 
// must be run within Dokuwiki
//if(!defined('DOKU_INC')) die();

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

function _getFN($ns, $file){

	// check for wiki page = $ns:$file (or $file where no namespace)
	$nsFile = ($ns) ? "$ns:$file" : $file;
	if (@file_exists(wikiFN($nsFile)) && auth_quickaclcheck($nsFile)) return $nsFile;

	// remove deepest namespace level and call function recursively

	// no namespace left, exit with no file found
	if (!$ns) return '';

	$i = strrpos($ns, ":");
	$ns = ($i) ? substr($ns, 0, $i) : false;
	return _getFN($ns, $file);
}

// defaults to the root linkfile: links
function _get_linkfile($file_ns, $file) {
	global $ID;
	
	// discover file containing the links
	$file_ns = ($file_ns == "") ? getNS($ID) : $file_ns;
	$linksfile = _getFN($file_ns, $file);
	if ($linksfile != "") return (io_readFile(wikiFN($linksfile)));
	return "";
}

/**
 * find first wikilink inside a string and return the link an type of link
 * 
 * @param string $section
 * @return multitype:unknown |boolean
 */
function _check_link($section) {
	$ltrs = '\w';
	$gunk = '/\#~:.?+=&%@!\-\[\]';
	$punc = '.:?\-;,';
	$host = $ltrs.$punc;
	$any  = $ltrs.$gunk.$punc;
	
	// wikilink
	$patterns['internallink'][] 		= '#\[\[(?:(?:[^[\]]*?\[.*?\])|.*?)\]\]#s';
	// externallink
	$schemes = getSchemes();
	foreach ( $schemes as $scheme ) {
		$patterns['externallink'][] 	= '#\b(?i)'.$scheme.'(?-i)://['.$any.']+?(?=['.$punc.']*[^'.$any.'])#s';
	}
	$patterns['externallink'][]			= '#\b(?i)www?(?-i)\.['.$host.']+?\.['.$host.']+?['.$any.']+?(?=['.$punc.']*[^'.$any.'])#s';
	$patterns['externallink'][] 		= '#\b(?i)ftp?(?-i)\.['.$host.']+?\.['.$host.']+?['.$any.']+?(?=['.$punc.']*[^'.$any.'])#s';
	
	// filelink
	$patterns['filelink'][] 				= '#\b(?i)file(?-i)://['.$any.']+?['.$punc.']*[^'.$any.']#s';
	// windows sharelink
	$patterns['windowssharelink'][] = '#\\\\\\\\\w+?(?:\\\\[\w$]+)+#s';
	// wiki medialink
	//$patterns['internalmedia'][] 		= '#\{\{[^\}]+\}\}#s';
	$patterns['media'][] 						= '#\{\{[^\}]+\}\}#s';
	//$this->patterns[] = '#(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?#s?'; // externallink
	// E-Mail
	$patterns['emaillink'][] 				= '#([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})#s';
		
	foreach ($patterns as $type => $subpatterns) {
		foreach ($subpatterns as $pattern) {
			if (preg_match($pattern, $section, $result)) {
					return array($type, $result[0]);
			}
		}
	}
	return false;
}

/**
 * split a string by a speciel string
 *  
 * @param string $section
 * @param string $link
 * @return array(string, string) 
 */
function _check_text($section, $link) {
	return (explode($link, $section, 2));
/*
	$pattern='#(.*)'.preg_quote($link).'(.*)#s';
	preg_match($pattern, $section, $result);
	return array($result[1], $result[2]);
*/
}

/**
 * Strips the heading <p> and trailing </p> added by p_render xhtml to acheive inline behavior
 * 
 * @param string $data
 * @return strin $data
 */
function _stripp($data) {
	$data = preg_replace('`^\s*<p[^>]*>\s*`', '', $data);
	$data = preg_replace('`\s*</p[^>]*>\s*$`', '', $data);
	return $data;
}

// change params of link depending on type
function _check_translate($type, $link, $params) {
	if (trim($params) != "") {		// change syntax-params --> link-params
		switch ($type) {
			case 'internallink':			// rendrerer maybe: interwikilink, locallink, internallink, windowssharelink, externallink, emaillink
				// convert [[$ns|$para]] --> [[$ns|$params]]
				list($ns, $para) = preg_split('/[\|]/',substr($link, 2, -2), 2);
				$link=substr($link,0,2).$ns."|".$params.substr($link,-2,2);
				break;
			case 'media': 						// rendrerer mayabe: externalmedia, internalmedia
				// convert {{$ns?$para}} --> {{$ns?$params}}
				list($ns, $para) = preg_split('/[\?]/',substr($link, 2, -2), 2);
				$link=substr($link,0,2).$ns."?".$params.substr($link,-2,2);
				break;
			case "windowssharelink":	// rendrerer mayabe: windowssharelink
			case 'externallink':			// rendrerer mayabe: externallink
			case "emaillink":					// rendrerer mayabe: emaillink
			case 'filelink':					// rendrerer mayabe: filelink
				// convert to internallink and set new params
				$link="[[".$link."|".$params."]]";
				$type="internallink";
				break;
		}
	}
	return (array($type, $link));
}

function _get_calls(&$handler, $type, $link, $state, $pos) {
	//----- save calls-array
	$save_calls=$handler->calls;
	$handler->calls=array();
	$handler->$type($link, $state, $pos);
	$calls=$handler->calls;
	//----- restore calls-array
	$handler->calls=$save_calls;
	return $calls[0];
}

class syntax_plugin_dwinsect extends DokuWiki_Syntax_Plugin {

	function syntax_plugin_dwinsect() {
	}
		
  function getInfo(){
    return array(
      'author' => 'Dietrich Wittenberg',
      'email'  => 'info.wittenberg@online.de',
      'date'   => '2012-07-01',
      'name'   => 'plugin dwinsect',
      'desc'   => 'INcludes a SECtion or the first link in the section. The link can be interpreted',
      'url'    => 'http://dokuwiki.org/plugin:dwinsect',
    );
  }
				
  function getType(){ return 'substition'; }
	function getAllowedTypes() { return array('disabled'); }	// 'formatting', 'substition',    
  function getPType(){ return 'normal'; }
  function getSort(){ return 199; }
  	
		
	function connectTo($mode) {
		$pattern='\[\*\([^\)]*\)]';	// [[&sectionname|parameter]]
		$this->Lexer->addSpecialPattern($pattern, $mode, 'plugin_dwinsect' );
	}

	//function postConnect() 		{	$this->Lexer->addExitPattern('}', 'plugin_dwinsect');}
 
   /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
  	
	  switch ($state) {
			case DOKU_LEXER_ENTER:
      case DOKU_LEXER_MATCHED:
			case DOKU_LEXER_UNMATCHED:
      case DOKU_LEXER_EXIT:
      	break;

      case DOKU_LEXER_SPECIAL:
	  		// syntax: [*(ns:file#anchor|params)]
	      $pattern='/\[\*\((?:(.*?)#)?([^|\)]*)[|]?(.*)\)]/';
	      preg_match($pattern, $match, $subjects);
	      list($match, $ns_file, $anchor, $params)=$subjects;
	      
	      // load nearest pagefile
	      $this->wikitext=_get_linkfile($ns_file, $this->getConf('linklistname'));
	       
				list($anchor, $anchor_params) = $this->_get_params($anchor); 				// $anchor_params => array(param1 => val1, param2 => val2, ...)
	      
	      $pattern='#(={2,}+[ ]*'.preg_quote(trim($anchor)).'[ ]*={2,}+\s*)(.*?)((?=={2,}+)|.$)#s';
	      if (preg_match($pattern, $this->wikitext, $section)) {							
	      	// found section-name matching $anchor !!! trim spaces from anchor
	      	list($type,  $link)		= _check_link($section[2]);									// find link in section:       						link
					list($text1, $text2)	= _check_text($section[2], $link);					// find text in section: 						text1 link text2
					
					switch ($anchor_params['link']) {
						case 'translate':																								// translate link depending on type:			link'
							list($type,  $link)		=	_check_translate($type, $link, $params);	
							break;
						default: break;																									// do nothing, no change to link:				link
					}
					$call = _get_calls($handler, $type, $link, $state, $pos);					// get call-array from core: 		array(calltype, array(args))
					return array($state, array($anchor, $call, $text1, $text2, $anchor_params, $section[0]));
	
	      } else {
	      	// not found section-name matching $anchor
	      	return array($state, array($anchor.(($params) ? "|".$params : ""), array('noanchor')));
	      }
      	break;
		}
    return array();
	}
 

  /**
   * Create output
   */
	function render($mode, &$renderer, $data) {

		if($mode == 'xhtml'){
			list($state, $match) = $data;
      switch ($state) {
      	case DOKU_LEXER_ENTER:
      	case DOKU_LEXER_MATCHED:
      	case DOKU_LEXER_UNMATCHED:
      	case DOKU_LEXER_EXIT:
       		//$renderer->info['cache'] = false; 
      		break;
      		
      	case DOKU_LEXER_SPECIAL:
      		// defined: $anchor, $call; optional: $text1, $text2, $anchor_params, $section
     			list($anchor, $call, $text1, $text2, $anchor_params, $section) = $match;
     			list($hndl, $args, $pos) = $call;
     			
     			// render the link as an syntax-error
     			if ($hndl == "noanchor") {	
     				$renderer->internallink("*(".$anchor.")");
     				return true;
     			}

     			switch ($anchor_params['include']) {
     				case'link':
		     			$this->_render_text($renderer, $text1, $anchor_params['text']);	// render the info-text
		     			$this->_render_link($renderer, $hndl, $args);										// render the link
		     			$this->_render_text($renderer, $text2, $anchor_params['text']);	// render second text
		     			break; // 'include link
     				case 'plain':
     					$renderer->doc.=p_render('xhtml',p_get_instructions($section),$info);
		     			break;
     			}
     			break;
      }
      return true;
    }
    return false;
	}

// private functions
	function _get_params($anchor) {
		// init defaults
		$anchor_params['text']		= $this->getConf('insecttext'); 		// "footnote"; 
		$anchor_params['link']		= $this->getConf('insectlink'); 		// "translate"; 
		$anchor_params['include']	= $this->getConf('insectinclude');	// "link";	  
		list($anchor, $params) = preg_split('/[\?]/', $anchor, 2);
		if ($params) {
			$a=explode("&", $params);
			foreach ($a as $p) {
				list($key, $val) = explode("=", $p);
				$anchor_params[$key]=$val;
			}
		}
		return array($anchor, $anchor_params);
	}

	function _render_text(&$renderer, $text, $flag) {
		if ($text) {
			switch ($flag) {
				case 'footnote':
					$renderer->footnote_open();
					$renderer->doc.=_stripp(p_render('xhtml',p_get_instructions($text),$info));
					$renderer->footnote_close();
					break;
				case 'plain':
					$renderer->doc.=_stripp(p_render('xhtml',p_get_instructions($text),$info));
					break;
			}
		}
	}

	function _render_link(&$renderer, $hndl, $args) {
		// render the link with type of $hndl: use dokuwiki core-functions
		switch ($hndl) {
			case 'internallink':
			case 'locallink':
			case 'filelink':
			case "windowssharelink":
			case 'externallink':
			case "emaillink":					$renderer->$hndl($args[0], $args[1]); break;
			case 'interwikilink':			$renderer->$hndl($args[0], $args[1], $args[2], $args[3]); break;
			case 'internalmedia':
			case 'externalmedia':			$renderer->$hndl($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6]); break;
		}
	}
	
}
//Setup VIM: ex: et ts=4 enc=utf-8 :
