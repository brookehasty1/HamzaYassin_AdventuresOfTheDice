# Adventures of the Dice

A snakes and ladders web game written in PHP, HTML and CSS. No JavaScript is used.

Team: Hamza Yassin (project leader), Brooke Hasty

## Features

- Register and log in (passwords are hashed, sessions are regenerated on login and logout)
- Three difficulty levels: Beginner, Standard, Expert
- One or two players taking turns
- Snakes and ladders on a 100 cell board
- Dynamic cell events (bonus, penalty, skip a turn, warp) chosen by the in-app AI rule
- AI Narrator box that describes each move in story language
- Adventure Recap at the end of a game listing every event that happened
- Leaderboard sorted by fewest rolls, then shortest time, with a filter by level

## Setup

Requirements: PHP 8.1 or newer.

Run locally:

```
cd PW2
php -S localhost:8000 -t public
```

Then open http://localhost:8000/index.php in a browser.

Run on the CODD server: upload the `public` and `src` folders so they stay side by side
(the pages load `../src/bootstrap.php`), then open `public/index.php` from the live URL.

## How to play

1. Register an account and log in.
2. On the New game page pick a level and 1 or 2 players (the Player 2 name box shows when "2 players" is selected).
3. Click "Roll the die" on your turn. The server rolls the die.
4. The first player to reach cell 100 wins. Rolling past 100 also counts as a win.
5. The Recap page shows the events from the game. The score is added to the leaderboard.

## File structure

```
public/                  one PHP file per page
  index.php              landing page (logged out) or redirect (logged in)
  register.php, login.php, logout.php
  setup.php              choose level and players
  game.php               board, narrator, roll button
  roll.php               handles the roll (POST only), then redirects to game.php
  recap.php              winner and Adventure Recap
  leaderboard.php
  assets/style.css,      assets/addons.css
src/                     game logic and shared code
  bootstrap.php          session start, CSRF, flash messages, login helpers
  board_config.php       boards for each level, event definitions, board validation
  engine.php             dice, setup validation, turn logic
  ai.php                 event picking and narrator text
  auth.php               registration and login
  leaderboard.php        saving and ranking scores
  view.php               shared HTML (header, footer, board)
tests/
  engine_test.php        command line tests
```

## In-app AI rule

The AI feature is a set of PHP rules. It does not use any outside service or learning.

Decision rule: after a player rolls and any snake or ladder is applied, the game checks if
the landing cell is an event cell (and the player has not already won). If it is, `pick_event()`
in `ai.php` picks one event from that cell's list. If it is not an event cell, the AI does nothing.

Data used: the game seed (a random number made when the game starts), the turn number, the
landing cell, and the list of events for that cell. The pick is `mt_rand()` seeded with
`seed + turn * 131 + cell`, so the same game and turn always give the same event. This
makes bugs easy to reproduce.

Event cells: Beginner 15, 42, 67. Standard 15, 38, 67, 83. Expert 12, 29, 52, 79.

Example: Sam lands on cell 38 (Standard) and the rule picks the fog event. The narrator
shows "Thick fog turns you around. Sam is pushed back 5 cells to cell 33." The event is
saved in `$_SESSION['last_event']` and added to `$_SESSION['events_log']`.

The AI never picks or changes the die roll. Rolls always use `random_int(1, 6)`.

Limitation: the rule only chooses at random from a fixed list for each cell. It does not
look at how well a player is doing and it does not learn. Events are also not predicted
or shown before the roll.

## Tests

Command line tests (19 checks):

```
php tests/engine_test.php
```

Result of the last run: 19 passed, 0 failed.

| Case | Type | Input | Expected | Result |
|------|------|-------|----------|--------|
| T1 | Normal | Standard level, roll 4 from the start | Lands on ladder at cell 4, ends on 25 | Pass |
| T2 | Normal | Standard level, roll 4 from cell 23 | Lands on snake at 27, ends on 5 | Pass |
| T3 | Boundary | Roll 6 from cell 98 | Game finishes, position 100, winner set | Pass |
| T4 | Boundary | Penalty event of 5 from cell 2 | Position stops at cell 1 | Pass |
| T5 | Boundary | Bonus event of 5 from cell 98 | Position stops at cell 100 | Pass |
| T6 | Normal | Same seed, turn and cell used twice | Same event both times | Pass |
| T8 | Failure | Die returns 7 | Exception, turn is not played | Pass |
| T9 | Failure | Play a turn in a finished game | Exception | Pass |
| T10 | Failure | Register "SAM" when "Sam" exists | "Username is already taken." | Pass |
| T11 | Failure | Register with a 5 character password | Password length error | Pass |
| T14 | Failure | Setup with level "insane" and 3 players | Errors for both | Pass |
| T17 | Normal | Check all three boards | No snake, ladder or event overlaps | Pass |

Manual tests on the live Codd site: register, log in, play a full 2 player game to the recap,
check the leaderboard shows the winner, log out. A form submitted with no CSRF token is
rejected and the user is sent back with a message.

## Known limitations

- Users, the current game and the leaderboard are stored in `$_SESSION`. They are lost when
  the session ends and are not shared between browsers or users. A file or database would fix this.
- The Content Security Policy blocks scripts and outside fonts, so the page uses the browser's
  default fonts instead of the "Exo 2" font named in the CSS.
- Only one game is kept per session.

## Development notes

Full sprint tasks and daily check-ins are in `Adventures of the Dice - Scrum Artifacts.docx`.

| Sprint | Dates | Work |
|--------|-------|------|
| 1 | 11/9 | Proposal, GitHub repo, wireframes for every PHP screen, board layouts |
| 2 | 11/10 | Page files, shared navigation, back end starting files (sessions, helpers) |
| 3 | 11/12 | Login, register, game engine, AI events, setup form validation |
| 4 | 11/14 | Styling, responsive layout, recap, leaderboard |
| 5 | 11/16 | Connected front end and back end, full testing, bug fixes, CODD upload |
| 6 | 11/18 | Live URL check, demo video, submission |

Decisions and problems:

- We kept game state on the server in the session so a player cannot change a roll or a
  position from the browser. The trade-off is the storage limitation above.
- Every action is a POST form that ends in a redirect, so refreshing the page does not roll again.
- Seeding the event pick from the turn number makes events repeatable for debugging.
- Sprint 3 problem: front-end error messages did not match the back end. We changed the
  setup page to show the validation messages from the PHP code.
- Sprint 4 problem: the back-end security settings blocked our styles and fonts. We changed
  the Content Security Policy to allow the stylesheet and inline style attributes.
- Sprint 5: a failing validation test was fixed after the back end was uploaded.
- Bugs found during testing: ladders did not move the token because only snakes were checked;
  the game owner check had an operator precedence mistake; several pages had PHP syntax errors.
  All were fixed and every file was checked with `php -l`.

## AI tool disclosure

We used Claude (Anthropic) as an AI assistant on this project. It was used to help debug and
fix PHP code, adapt the pages to our CSS, give the file structure and function names, and write the command line tests. We reviewed, ran and tested the code ourselves. The in-app "AI"
feature described above is our own rule-based PHP code and does not call any AI service.

Names: Hamza Yasin, Brooke Hasty

Course: Website Development 4370

Codd Link: https://codd.cs.gsu.edu/~bhasty1/web/PW/PW2/public/register.php 

Git Link: https://github.com/brookehasty1/HamzaYassin_AdventuresOfTheDice.git 
