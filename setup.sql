DROP DATABASE IF EXISTS c9;
CREATE DATABASE c9;
USE c9;

DROP TABLE IF EXISTS games;

CREATE TABLE games (
    guid CHAR(13) PRIMARY KEY,
    playerHand TEXT,
    opponentHand TEXT,
    deck TEXT,
    discard TEXT
);