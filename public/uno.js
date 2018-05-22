/*
Name: Jack Venberg
Date: 05.14.18
Section: CSE 154 AH

This is the main.js script for my Creative Project in which I have a Uno game
that connects to a custom-built uno API. This script runs on index.html and 
connects, makes calls to, and displays from the Uno API.
*/

"use strict";
(function() {
  const GAME_URL = "uno.php";
  
  let guid; /* Game ID */
  let animations; /* Array of animation to be played */
  let canPlay; /* Boolean signifying whether or not player can play */
  let currentWildMove; /* Currently wild card move */
  let currentGameData; /* Current game data of whole game */
  let handSnapshots = []; /* Still snapshot of each hand */
  
  /* Runs on page load. Adds onclick functionality and starts game. */
  window.onload = function() {
    $("pile").onclick = function() {
      playMove("new");
    };
    let colors = document.getElementsByClassName("color");
    for (let i = 0; i < colors.length; i++) {
      colors[i].onclick = wildMove;
    }
    $("topPile").addEventListener("transitionend", function(event) {
      if (event.propertyName === "top") {
        this.classList.add("hidden");
        this.style.top = null;
        this.style.left = null;
        $("new-card").classList.remove("blank");
        $("new-card").id = "";
        animateContinue();
      }
    });
    $("start-new").onclick = startGame;
    startGame();
  };

  /* Starts game by fetching from API and displays all cards */
  function startGame() {
    canPlay = true;
    $("start-new").classList.add("hidden");
    guid = undefined;
    let data =  new FormData();
    data.append("startgame", true);

    fetch(GAME_URL, {credentials: 'include', method: "POST", body: data})
      .then(checkStatus)
      .then(tryJSONParse)
      .then(function(gameData) {
        displayTable(gameData);
        $("discard").classList.remove("hidden");
        $("pile").classList.remove("hidden");
      })
      .catch(console.log);
  }
  
  /**
   * Attempts to parse JSON response, otherwise prints to console the error
   * @param {object} - Promise response.
   * @return {object} - Parsed JSON object.
   */
  function tryJSONParse(response) {
    try {
      return JSON.parse(response);
    } catch (e) {
      console.log(response);
      return Promise.reject(new Error(response.status +
                                      ": " + response.statusText));
    }
  }
  
  /**
   * Starts running animations from global animations array.
   * @param {gameData} - JSON object containing all game data.
   */
  function runAnimations(gameData) {
    canPlay = false;
    handSnapshots[0] = Array.from(document.querySelectorAll("#player .uno"));
    handSnapshots[1] = Array.from(document.querySelectorAll("#opponent .uno"));
    animations = gameData.animations;
    currentGameData = gameData;
    animateContinue();
  }
  
  /* Runs single animation from global animations array. */
  function animateContinue() {
    if (animations.length > 0) {
      let animation = animations.shift();
      if (animation.moveType === "play") {
        let deck = animation.isPlayer ? handSnapshots[0] : handSnapshots[1];
        playToDiscard(deck[animation.cardIndex]);
      } else if (animation.moveType === "draw") {
        let deck = animation.isPlayer ? $("player") : $("opponent");
        drawCard(deck);
      } else if (animation.moveType === "won" || animation.moveType === "lost") {
        canPlay = false;
        $("start-new").classList.remove("hidden");
        displayTable(currentGameData);
      } else {
        animateContinue();
      }
    } else {
      displayTable(currentGameData);
      canPlay = true;
    }
  }
  
  /** 
   * Animates card moving to discard pile.
   * @param {object} card - Card DOM object to animate.
   */
  function playToDiscard(card) {
    card.addEventListener("transitionend", function(event) {
      if (event.propertyName === "top") {
        card.parentNode.removeChild(card);
        $("discard").className = card.className;
        animateContinue();
      }
    });
    flyTo(card, $("discard"));
  }
  
  /**
   * Animates drawing card from deck to players hand.
   * @param {object} deck - Deck DOM object.
   */
  function drawCard(deck) {
    let newCard = document.createElement("div");
    newCard.id = "new-card";
    newCard.classList.add("blank");
    newCard.classList.add("uno");
    newCard.classList.add("back");
    deck.appendChild(newCard);
    if (deck.id === "player") {
      handSnapshots[0].push(newCard);
    } else {
      handSnapshots[1].push(newCard);
    }

    let targetOffset = getOffset($("pile"));
    $("topPile").style.right = targetOffset.right + "px";
    $("topPile").classList.remove("hidden");
    flyTo($("topPile"), newCard);
  }
  
  /**
   * Animates a card object flying to another card.
   * @param {object} card - DOM object to animate.
   * @param {object} target - DOM object to fly to.
   */
  function flyTo(card, target) {
    card.style.zIndex = 1;
    let cardOffset = getOffset(card);
    card.style.position = "absolute";
    card.style.top = cardOffset.top + "px";
    card.style.left = cardOffset.left + "px";

    let targetOffset = getOffset(target);
    card.style.position = "absolute";
    card.style.top = targetOffset.top + "px";
    card.style.left = targetOffset.left + "px";
  }

  /**
   * Returns the offset of a card from its parent container.
   * @param {object} card - DOM object to get offset.
   * @return {object} JSON object containing offsets.
   */
  function getOffset(card) {
    let childPos = card.getBoundingClientRect();
    let parentPos = $("table").getBoundingClientRect();
    return {
        top: childPos.top - parentPos.top,
        left: childPos.left - parentPos.left - 5,
        right: parentPos.right - childPos.right + 5
    };
  }

  /**
   * Displays all card hands and piles on table based on given game data.
   * @param {object} gameData - JSON object containing game data.
   */
  function displayTable(gameData) {
    if (!guid) {
      guid = gameData.guid;
    }
    $("results").innerHTML = gameData.results;
    displayCards($("player"), gameData.playerHand);
    displayCards($("opponent"), gameData.opponentHand);
    displayDiscard(gameData.discard);
  }
  
  /**
   * Displays the discard based on given discard JSON object.
   * @param {object} discard - JSON object of discard to display.
   */
  function displayDiscard(discard) {
    if (discard) {
      $("discard").className = "";
      $("discard").classList.add("uno");
      $("discard").classList.add(discard.type);
      if (discard.color) {
        $("discard").classList.add(discard.color);
      }
    } else {
      $("discard").classList.add("blank");
    }
  }

  /**
   * Displays cards in given hand.
   * @param {object} hand - hand DOM object.
   * @param {array} cards - Array of card objects.
   */
  function displayCards(hand, cards) {
    while (hand.lastChild) {
      hand.removeChild(hand.lastChild);
    }
    for (let i = 0; i < cards.length; i++) {
      createCard(hand, cards[i]);
    }
  }
  
  /**
   * Creates a given card in hand.
   * @param {object} hand - hand DOM object.
   * @param {object} cardData - JSON data of card. 
   */
  function createCard(hand, cardData) {
      let card = document.createElement("div");
      card.classList.add("uno");
      if (cardData.color) {
        card.classList.add(cardData.color);
      }
      card.classList.add(cardData.type);
      if (hand.id === "player") {
        if (cardData.type === "wild" || cardData.type === "wildDraw4") {
          card.onclick = wildCheckPlayable;
        } else {
          card.onclick = function() {
            playMove(getIndex(this));
          };
        }
      }
      hand.appendChild(card);
  }
  
  /* Checks if a wild card is playable */
  function wildCheckPlayable() {
    if (canPlay) {
      currentWildMove = getIndex(this);
      let data = new FormData();
      data.append("guid", guid);
      data.append("move", currentWildMove);
      data.append("checkplayable", true);
      
      fetch(GAME_URL, {credentials: 'include', method: "POST", body: data})
        .then(checkStatus)
        .then(tryJSONParse)
        .then(wildChooseColor)
        .catch(console.log);
    } else {
      animateContinue();
    }
  }
  
  /**
   * Shows wild card color chooser
   * @param {object} gameData - JSON object containing game data.
   */
  function wildChooseColor(gameData) {
    if (gameData.playable) {
      $("color-picker").classList.remove("hidden");
    }
    $("results").innerHTML = gameData.results;
  }
  
  /* Plays wild card. */
  function wildMove() {
    playMove(currentWildMove, this.id);
    $("color-picker").classList.add("hidden");
  }

  /**
   * Fetches given card move with given move index and color.
   * @param {integer} move - card index.
   * @param {string} color - card color.
   */
  function playMove(move, color) {
    if (canPlay) {
      let data = new FormData();
      data.append("guid", guid);
      data.append("move", move);
      if (color) {
        data.append("color", color);
      }
      fetch(GAME_URL, {credentials: 'include', method: "POST", body: data})
        .then(checkStatus)
        .then(tryJSONParse)
        .then(runAnimations)
        .catch(console.log);
    } else {
      animateContinue();
    }
  }
  
  /* Returns index of element in parent */
  function getIndex(element) {
    return Array.from(element.parentNode.children).indexOf(element);
  }

  /**
  * Checks the status response code. 
  * @param {object} response - fetch response object.
  * @return {object} Returns a succesful promise if status is successful, and
  * returns a rejected promise otherwise.
  */
  function checkStatus(response) {
    if (response.status >= 200 && response.status < 300) {
      return response.text();
    } else {
      return Promise.reject(new Error(response.status +
                                      ": " + response.statusText));
    }
  }
})();