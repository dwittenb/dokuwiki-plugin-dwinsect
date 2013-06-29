<?php
/**
 * dwinsect Plugin: Inserts a button with dwinsect-syntax into the toolbar
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Dietrich Wittenberg <info.wittenberg@online.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
*/
class action_plugin_dwinsect extends DokuWiki_Action_Plugin {

/* not longer needed for DokuWiki 2009-12-25 “Lemming” and later
	function getInfo(){
    return array(
      'author' => 'Dietrich Wittenberg',
      'email'  => 'info.wittenberg@online.de',
      'date'   => '2012-07-01',
      'name'   => 'plugin dwinsect',
      'desc'   => 'Includes a button with syntax of dwinsect',
      'url'    => 'http://dokuwiki.org/plugin:dwinsect',
    );
  }
 */
	
  /* 
   * Register the eventhandlers
   * @see DokuWiki_Action_Plugin::register()
   */
  function register(&$controller) {
  	$controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array()); 
  }
  
  /**
   * Insert the toolbar button
   * @param unknown_type $event
   * @param unknown_type $param
   */
  function insert_button(& $event, $param) {
  	$event->data[] = array(
  			'type'	=>	'insert',
  			'title'	=>	'Seitenabschnitt oder enthaltenen Link hinzufügen',
  			'icon'	=>	'../../plugins/dwinsect/images/dwinsect.png',
  			'insert'=>	'[*(ns:page#anchor?aparams|lparams)]',
  			);
  }
  
}
?>