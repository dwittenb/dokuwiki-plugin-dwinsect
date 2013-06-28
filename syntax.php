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
require_once (DWTOOLS.'lib.php');

function _getFN($ns, $file){

	// check for wiki page = $ns:$file (or $file where no namespace)
	$nsFile = ($ns) ? "$ns:$file" : $file;
	if (@file_exists(wikiFN($nsFile)) && auth_quickaclcheck($nsFile)) return $nsFile;
	// no namespace left, exit with no file found
	if (!$ns) return '';
	// remove deepest namespace level and call function recursively
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
 * split a string by a special string
 *  
 * @param string $section
 * @param string $link
 * @return array(string, string) 
 */
function _split_text($section, $link) {
  $ret=explode($link, trim($section), 2);
  return $ret;
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

class syntax_plugin_dwinsect extends DokuWiki_Syntax_Plugin {

	function syntax_plugin_dwinsect() {
	}
		
/* not longer needed for DokuWiki 2009-12-25 “Lemming” and later
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
*/				
  function getType(){ return 'substition'; }
	function getAllowedTypes() { return array('disabled'); }	// 'formatting', 'substition',    
  function getPType(){ return 'normal'; }
  function getSort(){ return 199; }
  	
		
	function connectTo($mode) {
		$pattern='\[\*\(.*?(?=\)\])\)\]';	// [*(.....)]
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
      	//         >match_all                                       <
	  		// syntax:  [ * (   ns:file#anchor          |     params  )]
	      $pattern='/\[\*\('.'(?:(.*?)#)?([^|\)]*)'.'[|]?'.'(.*)'.'\)]/';
	      preg_match($pattern, $match, $subjects);
	      list($match_all, $ns_file, $anchor, $params)=$subjects;
	      
	      // load nearest pagefile to wikitext
	      $this->wikitext=_get_linkfile($ns_file, $this->getConf('linklistname'));

	      // anchor: anchor_name?param1=val1&param2=val2 ...
				// $anchor_params => array(param1 => val1, param2 => val2, ...)
				list($anchor_name, $anchor_params) = $this->_get_params($anchor); 	

				//         (            section[0]                                                    )
				//         (            section[1]                                   )(  section[2]   )
				//         (===         anchor_name                               ===)(text1 url text2)      ==== | $end
	      $pattern='#(={2,}+[ ]*'.preg_quote(trim($anchor_name)).'[ ]*={2,}+\s*)(.*?)'           .'(?=={2,}+|$)#s';
	      if (preg_match($pattern, $this->wikitext, $section)) {							

	      	// found section-name matching $anchor !!! trim spaces from anchor
	      	list($link_type, $link)		= _get_firstlink($section[2]);						// find link in section[2]:						link
	      	list($text1, $text2)			= _split_text($section[2], $link);		// split text1, text2 by link:	text1 link text2
	      	
	      	switch ($anchor_params['link']) {
						case "translate":
							// translate link depending on type:			link'
							list($link_type,  $link)	=	_check_translate($link_type, $link, $params);
							break;
						case "none":	
							$link = "";	
							break;
						case "plane":	
						default:
							break;
					}
					
					switch ($anchor_params['text']) {
						case "footnote": 
							$text1 =	($text1) ? "((".$text1."))" : "";
							$text2 =	($text2) ? "((".$text2."))" : "";
							break;
						case "none":
							$text1 = "";
							$text2 = "";
							break;
						case "plain":
						default:
							break;
     			}
					
					switch ($anchor_params['include']) {
						case "plain":	
							$wiki	=	$section[0];	
							break;
						case "link":	
							$wiki	=	$text1.$link.$text2;	
							break;
						case "none":	
						default:
							$wiki = "";
							break;
					}
					$instructions=p_get_instructions($wiki);
					array_shift($instructions);	//remove document open
					array_shift($instructions);	//remove paragraph open
					array_pop($instructions);		//remove document close
					array_pop($instructions);		//remove paragraph close
					return array($state, array($instructions, false));
	      } else {
	      	$wiki=$anchor_name.(($params) ? "|".$params : "");	// not found section-name matching: anchor_name|anchor_params
	      	return array($state, array($wiki, true));
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
			list($state, $result) = $data;
      switch ($state) {
      	case DOKU_LEXER_ENTER:
      	case DOKU_LEXER_MATCHED:
      	case DOKU_LEXER_UNMATCHED:
      	case DOKU_LEXER_EXIT:
       		//$renderer->info['cache'] = false; 
      		break;
      		
      	case DOKU_LEXER_SPECIAL:
     			list($instructions, $error) = $result;
     			if ($error === true) {																			// render the link as an syntax-error	
     				$renderer->internallink("[*(".$instructions.")]");					// instructions contains the error-text
     			} else {
     				//$renderer->doc.=p_render('xhtml', $instructions, $info);	// instruction contains an array of instructions
     				$renderer->nest($instructions);
     			}
     			break;
      }
      return true;
    }
    return false;
	}

// private class functions
	function _get_params($anchor) {
	  // anchor: "anchorname?param1=val1&param2=val2 ..."
		// init defaults to valid params
		$anchor_params['text']		= $this->getConf('insecttext'); 		// "footnote"; 
		$anchor_params['link']		= $this->getConf('insectlink'); 		// "translate"; 
		$anchor_params['include']	= $this->getConf('insectinclude');	// "link";
		// split anchor_name from params	  
		list($anchor_name, $params) = preg_split('/[\?]/', $anchor, 2);
		// split params into anchor_params
		if ($params) {
			$a=explode("&", $params);
			foreach ($a as $p) {
				list($key, $val) = explode("=", $p);
				$anchor_params[$key]=$val;
			}
		}
		return array($anchor_name, $anchor_params);
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