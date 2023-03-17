<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * LineIt implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * lineit.action.php
 *
 * LineIt main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/lineit/lineit/myAction.html", ...)
 *
 */
  
  
  class action_lineit extends APP_GameAction
  { 
    // Constructor: please do not modify
   	public function __default()
  	{
  	    if( self::isArg( 'notifwindow') )
  	    {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    }
  	    else
  	    {
            $this->view = "lineit_lineit";
            self::trace( "Complete reinitialization of board game" );
      }
  	} 

    public function playCardFromHand() {
        self::setAjaxMode();     

        $id = self::getArg("id", AT_posint, true);
        $this->game->playCardFromHand($id);

        self::ajaxResponse();
    }

    public function chooseMarketCardLine() {
        self::setAjaxMode();     

        $id = self::getArg("id", AT_posint, true);
        $this->game->chooseMarketCardLine($id);

        self::ajaxResponse();
    }

    public function chooseMarketCardHand() {
        self::setAjaxMode();     

        $id = self::getArg("id", AT_posint, true);
        $this->game->chooseMarketCardHand($id);

        self::ajaxResponse();
    }

    public function closeLine() {
        self::setAjaxMode();     

        $this->game->closeLine();

        self::ajaxResponse();
    }

    public function pass() {
        self::setAjaxMode();     

        $this->game->pass();

        self::ajaxResponse();
    }

  }
  

