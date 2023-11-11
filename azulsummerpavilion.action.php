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

    public function confirmAcquire() {
      self::setAjaxMode();

      $this->game->confirmAcquire();

      self::ajaxResponse();
    }

    public function undoTakeTiles() {
      self::setAjaxMode();

      $this->game->undoTakeTiles();

      self::ajaxResponse();
    }

    public function selectPlace() {
      self::setAjaxMode();

      // Retrieve arguments
      $star = self::getArg("star", AT_posint, true);
      $space = self::getArg("space", AT_posint, true);

      $this->game->selectPlace($star, $space);

      self::ajaxResponse();
    }

    public function selectColor() {
      self::setAjaxMode();

      // Retrieve arguments
      $color = self::getArg("color", AT_posint, true);

      $this->game->selectColor($color);

      self::ajaxResponse();
    }

    public function playTile() {
      self::setAjaxMode();

      // Retrieve arguments
      $wilds = self::getArg("wilds", AT_posint, true);

      $this->game->playTile($wilds);

      self::ajaxResponse();
    }

    public function confirmPlay() {
      self::setAjaxMode();

      $this->game->confirmPlay();

      self::ajaxResponse();
    }

    public function confirmPass() {
      self::setAjaxMode();

      $this->game->confirmPass();

      self::ajaxResponse();
    }

    public function undoPass() {
      self::setAjaxMode();

      $this->game->undoPass();

      self::ajaxResponse();
    }

    public function undoPlayTile() {
      self::setAjaxMode();

      $this->game->undoPlayTile();

      self::ajaxResponse();
    }

    public function pass() {
      self::setAjaxMode();

      $this->game->pass();

      self::ajaxResponse();
    }

    public function selectKeptTiles() {
      self::setAjaxMode();

      // Retrieve arguments
      $idsStr = self::getArg( "ids", AT_numberlist, true );
      $ids = strlen($idsStr) > 0 ? array_map(fn($str) => intval($str), explode(',', $idsStr)) : [];

      $this->game->selectKeptTiles($ids);

      self::ajaxResponse();
    }

    public function cancel() {
      self::setAjaxMode();

      $this->game->cancel();

      self::ajaxResponse();
    }

    public function takeBonusTiles() {
      self::setAjaxMode();

      // Retrieve arguments
      $idsStr = self::getArg( "ids", AT_numberlist, true );
      $ids = strlen($idsStr) > 0 ? array_map(fn($str) => intval($str), explode(',', $idsStr)) : [];

      $this->game->takeBonusTiles($ids);

      self::ajaxResponse();
    }

  }
  

