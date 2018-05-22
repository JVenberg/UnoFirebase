<?php
  /*
  Name: Jack Venberg
  Date: 05.14.18
  Section: CSE 154 AH
  
  This is the uno.php script for my Creative Project in which I have a Uno game
  that connects to a custom-built uno API. This script runs the Uno API on
  cloud9 servers and handles all Uno game logic through POST calls.
  */
  
  error_reporting(E_ALL);
  /* All card types, colors, and names */
  $CARD_TYPE = ["num0", "num1", "num2", "num3", "num4", "num5", "num6", "num7", "num8", "num9",
               "skip", "reverse", "draw2", "wild", "wildDraw4"];
  $CARD_NAMES = ["Zero", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine",
                "Skip", "Reverse", "Draw Two", "Wild Card", "Wild Draw Four"];
  $CARD_COLORS = ["red", "yellow", "green", "blue"];
  $skip = false; /* Whether or not currently skipping opponent */
  
  /* Database connection variables */
  $servername = "localhost";
  $username = "root";
  $password = "";
  $database = "c9";
  
  /* Make a data source string that will be used in creating the PDO object */
  $ds = "mysql:host={$servername};dbname={$database};charset=utf8";

  try {
    $db = new PDO($ds, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (PDOException $ex) {
    handle_error("Can not connect to the database. Please try again later.", $ex);
  }

  header("Content-Type: application/json");
  $animations = array(); /* Array for storing animation objects to be sent back */
  if (isset($_POST["startgame"]) and $_POST["startgame"]) {
    start_game();
  } else if (isset($_POST["guid"]) and isset($_POST["move"])) {
    if (isset($_POST["checkplayable"])) {
      check_if_playable();
    } else {
      play_move();
    }
  }
  
  $db = null;
  
  /* Starts game by setting up database and displaying initial values. */
  function start_game() {
    $GLOBALS["guid"] = uniqid();
    $GLOBALS["deck"] = create_deck();
    
    shuffle($GLOBALS["deck"]);
    list($GLOBALS["players_hand"], $GLOBALS["opponents_hand"]) = deal_hands($GLOBALS["deck"]);
    
    $game_data = array();
    $game_data["guid"] = $GLOBALS["guid"];
    $game_data["playerHand"] = $GLOBALS["players_hand"];
    $game_data["opponentHand"] = hide_cards($GLOBALS["opponents_hand"]);
    $game_data["results"] = "<p>Created new deck of cards.</p>";
    $game_data["animations"] = array();
    array_push($game_data["animations"], new Animation("new_deck", null, null));
    print(json_encode($game_data));
    
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO games (guid, playerHand, opponentHand, deck) 
                            VALUES (:guid, :player_hand, :opponent_hand, :deck)");
    $params = array("guid" => $GLOBALS["guid"],
                    "player_hand" => serialize($GLOBALS["players_hand"]),
                    "opponent_hand" => serialize($GLOBALS["opponents_hand"]),
                    "deck" => serialize($GLOBALS["deck"]));
    $stmt->execute($params);
  }
  
  /**
   * Plays a move of a card depending on what "move" is set to in POST
   * request.
   */
  function play_move() {
    read_db();
    
    $game_data = array();
    $move = $_POST["move"];
    if (is_numeric($move) and $move < count($GLOBALS["players_hand"]) and $move >= 0) {
      if (is_playable($GLOBALS["players_hand"][$move], $GLOBALS["players_hand"])) {
        $game_data["results"] = player_play($move);
        
        if (count($GLOBALS["players_hand"]) == 0) {
          $game_data["results"] .= "<p>YOU WON!</p>";
          array_push($GLOBALS["animations"], new Animation("won", null, null));
        } else {
          $game_data["results"] .= opponent_play();
          if (count($GLOBALS["opponents_hand"]) == 0) {
            $game_data["results"] .= "<p>YOU LOST!</p>";
            array_push($GLOBALS["animations"], new Animation("lost", null, null));
          }
        }
      } else {
        $game_data["results"] = "<p>Card is unplayable.</p>";
      }
    } else if ($move == "new") {
      check_deck();
      array_push($GLOBALS["players_hand"], array_pop($GLOBALS["deck"]));
      array_push($GLOBALS["animations"], new Animation("draw", null, true));
      $game_data["results"] = "<p>You drew a card.</p>";
    }
    
    $game_data["guid"] = $GLOBALS["guid"];
    $game_data["playerHand"] = $GLOBALS["players_hand"];
    $game_data["opponentHand"] = hide_cards($GLOBALS["opponents_hand"]);
    $game_data["discard"] = end($GLOBALS["discard"]);
    $game_data["animations"] = $GLOBALS["animations"];
    print(json_encode($game_data));
    
    write_db();
  }
  
  /**
   * Checks if a card specified by what "move" is set to in POST request.
   * Returns true if playable and false otherwise.
   * @return {boolean} Whether or not the move is playable.
   */
  function check_if_playable() {
    read_db();
    
    $game_data = array();
    $game_data["guid"] = $GLOBALS["guid"];
    if (is_playable($GLOBALS["players_hand"][$_POST["move"]], $GLOBALS["players_hand"])) {
      $game_data["playable"] = true;
      $game_data["results"] = "<p>Please choose a color.</p>";
    } else {
      $game_data["playable"] = false;
      $game_data["results"] = "<p>Card is unplayable.</p>";
    }
    
    print(json_encode($game_data));
    
    write_db();
  }
  
  /**
   * Plays a move by the player based on the given $move index.
   * @param {integer} $move - Index of card to play.
   */
  function player_play($move) {
    $played_card = play_card($move, $GLOBALS["players_hand"], $GLOBALS["opponents_hand"], true);
    return "<p>You played a " . $played_card->name . ".</p>";
  }
  
  /* Plays a move for the player randomly choosing a playable card. */
  function opponent_play() {
    if (!$GLOBALS["skip"]) {
      $drew_count = 0;
      while(true) {
        $playable_moves = array();
        foreach ($GLOBALS["opponents_hand"] as $move=>$card) {
          if (is_playable($card, $GLOBALS["opponents_hand"])) {
            array_push($playable_moves, $move);
          }
        }
        if (count($playable_moves) == 0) {
          check_deck();
          array_push($GLOBALS["opponents_hand"], array_pop($GLOBALS["deck"]));
          array_push($GLOBALS["animations"], new Animation("draw", null, false));
          $drew_count++;
        } else {
          $move = $playable_moves[array_rand($playable_moves)];
          $played_card = play_card($move, $GLOBALS["opponents_hand"], $GLOBALS["players_hand"], false);
          $result = "<p>Opponent ";
          if ($drew_count > 0) {
            $result .= "drew " . $drew_count . " times and " ;
          }
          $result .= "played a " . $played_card->name . ".</p>";
          if ($played_card->type == "skip" || $played_card->type == "reverse") {
            $result .= "<p>Player was skipped</p>";
            $GLOBALS["skip"] = false;
            $result .= opponent_play();
          }
          return $result;
        }
      }
    } else {
      $GLOBALS["skip"] = false;
      return "<p>Opponent was skipped</p>";
    }
  }
  
  /**
   * Plays a specific card speficied by the "$move" index. Takes card from given
   * "$hand" and will draw cards to "$otherHand".
   * @param {integer} $move - Index of card to play.
   * @param {array} $hand - Array of Card objects of hand to play from.
   * @param {array} $other_hand - Array of Card objects to draw to.
   * @param {boolean} $is_player - Whether of not move is for player.
   */
  function play_card($move, &$hand, &$other_hand, $is_player) {
    $played_card = $hand[$move];
    $played_card->index = $move;
    array_push($GLOBALS["animations"], new Animation("play", $move, $is_player));
    if ($played_card->type == "wild" or $played_card->type == "wildDraw4") {
      if ($is_player) {
        $played_card->color = $_POST["color"];
      } else {
        $played_card->color = $GLOBALS["CARD_COLORS"][array_rand($GLOBALS["CARD_COLORS"])];
      }
    }
    if ($played_card->type == "wildDraw4") {
      for ($i = 0; $i < 4; $i++) {
        check_deck();
        array_push($other_hand, array_pop($GLOBALS["deck"]));
        array_push($GLOBALS["animations"], new Animation("draw", null, !$is_player));
      }
    } else if ($played_card->type == "draw2") {
      for ($i = 0; $i < 2; $i++) {
        check_deck();
        array_push($other_hand, array_pop($GLOBALS["deck"]));
        array_push($GLOBALS["animations"], new Animation("draw", null, !$is_player));
      }
    } else if ($played_card->type == "skip" or $played_card->type == "reverse") {
      $GLOBALS["skip"] = true;
    }
    array_push($GLOBALS["discard"], $played_card);
    unset($hand[$move]);
    $hand = array_values($hand);
    return $played_card;
  }
  
  /**
   * Reads from mySQL database based on what "guid" is set to in POST request
   * and stores data into global variables.
   */
  function read_db() {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM games WHERE guid=:guid");
    $params = array("guid" => $_POST["guid"]);
    $stmt->execute($params);
    $row = $stmt->fetch();
    
    $discard = isset($row["discard"]) ? unserialize($row["discard"]) : array();
    $GLOBALS["guid"] = $row["guid"];
    $GLOBALS["deck"] = unserialize($row["deck"]);
    $GLOBALS["players_hand"] = unserialize($row["playerHand"]);
    $GLOBALS["opponents_hand"] = unserialize($row["opponentHand"]);
    $GLOBALS["discard"] = $discard;
    
  }
  
  /* Writes to mySQL database based global table variables. */
  function write_db() {
    $stmt = $GLOBALS["db"]->prepare("UPDATE games SET playerHand=:playerHand,
                                     opponentHand=:opponentHand, deck=:deck, discard=:discard
                                     WHERE guid=:guid");
    $params = array("guid" => $GLOBALS["guid"],
                    "playerHand" => serialize($GLOBALS["players_hand"]),
                    "opponentHand" => serialize($GLOBALS["opponents_hand"]),
                    "deck" => serialize($GLOBALS["deck"]),
                    "discard" => serialize($GLOBALS["discard"]));
    $stmt->execute($params);
  }
  
  /**
   * Checks to make sure deck has hards. If not, it takes discard pile and puts
   * discarded cards into deck.
   */
  function check_deck() {
    if (count($GLOBALS["deck"]) == 0) {
      $GLOBALS["deck"] = $GLOBALS["discard"];
      $GLOBALS["discard"] = array();
    }
  }
  
  /**
   * Checks if a given card is playable from a given hand. Wild draw 4 is
   * playable only if no other cards are playable.
   * @param {object} $card - Card object that's checked for playablility.
   * @param {array} $hand - Array of Card objects to check from.
   * @return {boolean} Whether or not card is playable. True if yes, no otherwise.
   */
  function is_playable($card, $hand) {
    if ($card->type == "wildDraw4") {
      foreach($hand as $card_in_hand) {
        if ($card_in_hand->type != "wildDraw4" and is_playable_helper($card_in_hand)) {
          return false;
        }
      }
      return true;
    }
    return is_playable_helper($card);
  }
  
  /**
   * Checks if a given card is playable.
   * @param {object} $card - Card object that's checked for playablility.
   * @return {boolean} Whether or not card is playable. True if yes, no otherwise.
   */
  function is_playable_helper($card) {
    if (!$GLOBALS["discard"]) {
      return true;
    }
    if ($card->type == "wild") {
      return true;
    }
    $top_discard = end($GLOBALS["discard"]);
    if ($card->type == $top_discard->type or 
        ($card->color == $top_discard->color and $card->color != null)) {
      return true;
    }
    return false;
  }
  
  /**
   * Prints the given error message with details about the error.
   * @param {string} $msg - Error message.
   * @param {object} $ex - Error object.
   */
  function handle_error($msg, $ex) {
    header("Content-Type: text/plain");
    print ("{$msg}\n");
    print ("Error details: $ex \n");
    die();
  }
  
  /**
   * Obscures given hand by returning a hand with an equal number of back-facing
   * cards.
   * @param {array} $hand - Array of Card objects for hand.
   * @return {array} Array of obscured Card objects.
   */
  function hide_cards($hand) {
    $hidden_hand = array();
    for ($i = 0; $i < count($hand); $i++) {
      array_push($hidden_hand, new Card("back", null, "Back"));
    }
    return $hidden_hand;
  }

  /**
   * Deals equally to each hand from given deck.
   * @param {array} $deck - Array of Card objects for deck.
   * @return {array} Array of two arrays of Card objects for each hand.
   */
  function deal_hands(&$deck) {
    $deal_player = true;
    $players_hand = array();
    $opponents_hand = array();
    for ($i = 0; $i < 14; $i++) {
      if ($deal_player) {
        array_push($players_hand, array_pop($deck));
      } else {
        array_push($opponents_hand, array_pop($deck));
      }
      $deal_player = !$deal_player;
    }
    return [$players_hand, $opponents_hand];
  }

  /**
   * Creates a deck of uno cards.
   * return {array} Array of Card objects representing the deck.
   */
  function create_deck() {
    $deck = array();
    for ($i = 0; $i < 15; $i++) { 
      for ($j = 0; $j < 4; $j++) {
        if ($i < 13) {
          $name = ucfirst($GLOBALS["CARD_COLORS"][$j]) . " " . $GLOBALS["CARD_NAMES"][$i];
          if ($i > 0) {
            array_push($deck, new Card($GLOBALS["CARD_TYPE"][$i], $GLOBALS["CARD_COLORS"][$j], $name));
          }
          array_push($deck, new Card($GLOBALS["CARD_TYPE"][$i], $GLOBALS["CARD_COLORS"][$j], $name));
        } else {
          array_push($deck, new Card($GLOBALS["CARD_TYPE"][$i], null, $GLOBALS["CARD_NAMES"][$i]));
        }
      }
    }
    return $deck;
  }
  
  /* Card object for storing card information. */
  class Card {
    public $type;
    public $color;
    public $name;
    public $index;
    
    /**
     * Constructs Card object.
     * @param {string} $type - Card type.
     * @param {string} $color - Card color.
     * @param {string} $name - Card name.
     */
    public function __construct($type, $color, $name) {
      $this->type = $type;
      $this->color = $color;
      $this->name = $name;
    }
  }
  
  /* Card object for storing animation information. */
  class Animation {
    public $moveType;
    public $cardIndex;
    public $isPlayer;
    
    /**
     * Constructs Card object.
     * @param {string} $move_type - Animation type.
     * @param {integer} $card_index - Index of card in hand to move.
     * @param {boolean} $is_player - Whether or not animation is for the player.
     */
    public function __construct($move_type, $card_index, $is_player) {
      $this->moveType = $move_type;
      $this->cardIndex = (int)$card_index;
      $this->isPlayer = (bool)$is_player;
    }
  }
?>