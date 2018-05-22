/*
Name: Jack Venberg
Date: 05.14.18
Section: CSE 154 AH

This is the main.js script for my Creative Project in which I have a Uno game
that connects to a custom-built Uno API. This script contains universal
functions between all pages.
*/

"use strict";
(function() {

  /** Called when pages scrolls. Changes nav color scheme. */
  window.onscroll = window.changeNavOnScroll;

  /**
   * Returns the element that has the ID attribute with the specified value.
   * @param {string} id - element ID
   * @return {object} DOM object associated with id.
   */
  window.$ = function(id) {
    return document.getElementById(id);
  };

  /**
   * Changes the color scheme of the nav bar when the nav bar crosses the card of
   * the page.
   */
  window.changeNavOnScroll = function() {
    if (window.pageYOffset > 120) {
      $('nav').classList.add("nav-change");
    } else {
      $('nav').classList.remove("nav-change");
    }
  };
})();