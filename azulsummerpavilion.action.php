<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * AzulSummerPavilion implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * azulsummerpavilion.action.php
 *
 * AzulSummerPavilion main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/azulsummerpavilion/azulsummerpavilion/myAction.html", ...)
 *
 */
  
  
  class action_azulsummerpavilion extends APP_GameAction { 
    // Constructor: please do not modify
   	public function __default() {
      if (self::isArg( 'notifwindow')) {
        $this->view = "common_notifwindow";
        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
      } else {
        $this->view = "azulsummerpavilion_azulsummerpavilion";
        self::trace( "Complete reinitialization of board game" );
      }
  	} 
  	
    public function takeTiles() {
      self::setAjaxMode();

      // Retrieve arguments
      $id = self::getArg("id", AT_posint, true);

      $this->game->takeTiles($id);

      self::ajaxResponse();
    }

    public function undoTakeTiles() {
      self::setAjaxMode();

      $this->game->undoTakeTiles();

      self::ajaxResponse();
    }

    public function selectLine() {
      self::setAjaxMode();

      // Retrieve arguments
      $line = self::getArg("line", AT_posint, true);

      $this->game->selectLine($line);

      self::ajaxResponse();
    }

    public function confirmLine() {
      self::setAjaxMode();

      $this->game->confirmLine();

      self::ajaxResponse();
    }

    public function undoSelectLine() {
      self::setAjaxMode();

      $this->game->undoSelectLine();

      self::ajaxResponse();
    }

  }
  

