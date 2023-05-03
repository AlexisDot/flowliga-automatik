<?php

session_start();

/** 
 * TODO: Implement session storage to be able to accept corrections 
 * and keep them for posting and saving of the report
 * ref: https://www.youtube.com/watch?v=3CS-eQdcMLU
 */

$game_hash;
$json;
$players_file;
$games_file;
$players_array;


function includeWithVariables($filePath, $variables = array(), $print = true)
{
  $output = NULL;
  if (file_exists($filePath)) {
    // Extract the variables to a local namespace
    extract($variables);

    // Start output buffering
    ob_start();

    // Include the template file
    include $filePath;

    // End buffering and return its contents
    $output = ob_get_clean();
  }
  if ($print) {
    print $output;
  }
  return $output;
}

/**
 *  Given a file, i.e. /css/base.css, replaces it with a string containing the
 *  file's mtime, i.e. /css/base.1221534296.css.
 *
 *  @param $file  The file to be loaded.  Must be an absolute path (i.e.
 *                starting with slash).
 */

// auto versioning for js and css files, works with .htaccess, currently not in use 
// explained in https://stackoverflow.com/a/118886
function auto_version($file)
{
  if (strpos($file, '/') !== 0 || !file_exists($_SERVER['DOCUMENT_ROOT'] . $file))
    return $file;

  $mtime = filemtime($_SERVER['DOCUMENT_ROOT'] . $file);
  return preg_replace('{\\.([^./]+)$}', ".$mtime.\$1", $file);
}

// utility function to remove whitespaces from csv entries
$flow_array_trim = function ($entry) {
  return [trim($entry[0]), trim($entry[1]), trim($entry[2])];
};

// search function for game pairing, returns false if non found
function get_game_pairing($games_array, $players)
{
  $pairing = false;
  foreach ($games_array as $entry => $subArray) {
    if (in_array($players[0], $subArray) && in_array($players[1], $subArray)) {
      $pairing = $subArray;
      break;
    }
  }
  return $pairing;
}

function get_game_pairing_by_number($games_array, $game_number)
{
  $pairing = false;
  foreach ($games_array as $entry => $subArray) {
    if (in_array($game_number, $subArray)) {
      $pairing = $subArray;
      break;
    }
  }
  return $pairing;
}

/**
 *  load lookup file for participants, csv format 
 *  0 is Discord name, 1 is Lidarts name, 2 is Discord ID
 *  if you don't have the files create dummy data before trying to run!
 */
$players_file = "app_data/Teilnehmer-Liga-Lidarts-DiscordID.csv";
if (file_exists($players_file)) {
  $players_csv = file_get_contents($players_file);
  $players_array = array_map("str_getcsv", explode("\n", $players_csv));
  $players_array_trimmed = array_map($flow_array_trim, $players_array);
} else {
  return includeWithVariables('app_data/report-error.php', array(
    'error_reason' => 'noPlayersFile'
  ));
}
// remove csv header entries 
array_shift($players_array_trimmed);


/** 
 * load game pairings from file, csv format
 * 0 is game number, 1 is first participant, 2 is second participant
 * if you don't have the files create dummy data before trying to run!
 */
$pairings_file = "app_data/game-pairings.csv";
if (file_exists($pairings_file)) {
  $games_csv = file_get_contents($pairings_file);
  $games_array = array_map("str_getcsv", explode("\n", $games_csv));
  $games_array_trimmed = array_map($flow_array_trim, $games_array);
} else {
  return includeWithVariables('app_data/report-error.php', array(
    'error_reason' => 'noPairingsFile'
  ));
}
// remove csv header entries 
array_shift($games_array_trimmed);

// load game data from lidarts
if (array_key_exists('game', $_GET)) {
  global $game_hash;
  $game_hash = $_GET["game"];

  include('app_data/report-data.php');
  global $game_data;
  $game_data = get_game_data($game_hash);

  // set to own variables for easier access
  $date = $game_data['date'];
  $players = $game_data['players'];
  $rest = $game_data['rest'];
  $finishes = $game_data['finishes'];
  $cancelled = false;
}

if (array_key_exists('game', $_POST)) {
  $game_hash = $_POST['game'];
  if ($game_hash != $_SESSION['game_hash']) {
    session_reset();
    echo 'session resetted';
  } else {
    echo 'same session';
  }
} else {
  if (!array_key_exists('game', $_GET)) {
    session_reset();
  }
}

if (array_key_exists('cancelled', $_POST)) {
  $cancelled = true;
  $game_number = $_POST['cancelled'];
  $game_hash = '--------';
  $pairing = get_game_pairing_by_number($games_array_trimmed, $game_number);
  $date = date("d.m.Y");
  $players = [
    1 => [
      "name" => $pairing[1],
      "one80s" =>  0,
      "one71s" => 0,
      "avg" => 0.00,
      "highestFinish" => 0,
      "winner" => $_POST['cancelledPoints'] == 1 ? true : false,
      "legsWon" => 0

    ],
    2 => [
      "name" => $pairing[2],
      "one80s" =>  0,
      "one71s" => 0,
      "avg" => 0.00,
      "highestFinish" => 0,
      "winner" => $_POST['cancelledPoints'] == 2 ? true : false,
      "legsWon" => 0
    ]
  ];
}


if (isset($players)) {

  if (!isset($cancelled)) {
    // lookup lidarts names in $players_array_trimmed
    for ($i = 1; $i < 3; $i++) {
      $player_keys[$i] = array_search(
        $players[$i]['name'],
        array_column($players_array_trimmed, 1)
      );
      if ($player_keys[$i] != false) {
        $players[$i]['name'] = $players_array_trimmed[$player_keys[$i]][0];
        $players_discord_ids[$i] = $players_array_trimmed[$player_keys[$i]][2];
      }
    }

    // check if either both or one of the players couldn't be looked up and throw error
    if ($player_keys[1] == false && $player_keys[2] == false) {
      return includeWithVariables('app_data/report-error.php', array(
        'player1_name' => $players[1]['name'],
        'player2_name' => $players[2]['name'],
        'error_reason' => 'playersNotFoundBoth'
      ));
    }
    if ($player_keys[1] == false) {
      return includeWithVariables('app_data/report-error.php', array(
        'player_name' => $players[1]['name'],
        'error_reason' => 'playerNotFound'
      ));
    }
    if ($player_keys[2] == false) {
      return includeWithVariables('app_data/report-error.php', array(
        'player_name' => $players[2]['name'],
        'error_reason' => 'playerNotFound'
      ));
    }
  }

  // searching for game pairing 
  if (isset($pairing)) {
    $game_pairing = $pairing;
  } else {
    $game_pairing = get_game_pairing(
      $games_array_trimmed,
      [$players[1]['name'], $players[2]['name']]
    );
  }

  // report error if no pairing found
  if ($game_pairing == false) {
    return includeWithVariables('app_data/report-error.php', array(
      'player1_name' => $players[1]['name'],
      'player2_name' => $players[2]['name'],
      'error_reason' => 'noPairing'
    ));
  }

  // check if first player in $players is same as first player in $game_pairing, if not: set true
  $switched = !($players[1]['name'] == $game_pairing[1]);

  // set $game_number from $game_pairing, for easier access
  $game_number = $game_pairing[0];

  if (!isset($date)) {
    $date = date("d.m.Y");
  }

  if (!isset($rest)) {
    $rest = [
      1 => [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        5 => 0,
        'sum' => 0
      ],
      2 => [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        5 => 0,
        'sum' => 0
      ],
      'diff' => $_POST['cancelledPoints'] == 0 ? 0 : 120
    ];
  }

  if (!isset($finised)) {
    $finishes = [
      1 => [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        5 => 0
      ],
      2 => [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        5 => 0
      ]
    ];
  }

  // generate and load report image
  ob_start();

  includeWithVariables(
    'app_data/report-image.php',
    array(
      'game_number' => $game_number,
      'game_hash' => $game_hash,
      'date' => $date,
      'switched' => $switched,
      'players' => $players,
      'finishes' => $finishes,
      'rest' => $rest,
    )
  );

  $image = ob_get_contents();

  ob_end_clean();


  // base64 encode for display on page
  $image_base64 = 'data:image/png;base64,' . base64_encode($image);


  // post the report to discord if postResult param is found in $_POST
  // if (true) {
  if (array_key_exists('postResult', $_POST) && array_key_exists('game', $_POST)) {
    includeWithVariables('app_data/report-post.php', array(
      'image' => $image,
      'game_number' => $game_number,
      'game_hash' => $game_hash,
      'date' => $date,
      'switched' => $switched,
      'players' => $players,
      'finishes' => $finishes,
      'rest' => $rest,
      'player_discord_ids' => $players_discord_ids,
      'cancelled' => $cancelled
    ));
    return;
  }
}
?>


<!-- display page with options to user -->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- <link rel="stylesheet" href="assets/style.css"> -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
  <link rel="stylesheet" href="assets/pico-custom.css">
  <title>Flow Liga Spielbericht Automatik</title>
</head>

<body>
  <main class="container">
    <nav>
      <ul>
        <li>
          <img src="assets/logo_300_159.png" />
        </li>
      </ul>
      <ul>
        <li>
          <h1>Spielbericht Automatik</h1>
        </li>
      </ul>
    </nav>

    <?php if (isset($image)) { ?>
      <article>
        <section>
          <report-img-area>
            <img class="report-img" src="<?php echo $image_base64; ?>" />
          </report-img-area>
        </section>
        <div class="grid">
          <button type="button" id="save-img">💾 Speichern</button>
          <button type="button" id="post-report">📮 Posten</button>
        </div>
      </article>
    <?php } ?>

    <article>
      <form>
        <label for="game-link">Lidarts-URL</label>
        <input id="game-link" name="game" type="text" placeholder="https://lidarts.org/game/ABCD1234">
        <div class="grid">
          <button type="button" id="get-game">Laden</button>
          <button type="button" id="cancelled-game" data-target="modal-cancelled-game" onClick="toggleModal(event)">Abgesagt</button>
        </div>
      </form>
    </article>
  </main>

  <footer class="container">
    <nav>
      <ul>
        <li>
          <small>Made with <a href="https://picocss.com" target="_blank">picoCSS</a></small>
        </li>
      </ul>
      <ul>
        <li>
          <small><a href="https://janfromm.de/typefaces/camingocode/" target="_blank">CamingoCode</a> by Jan Fromm</small>
        </li>
      </ul>
    </nav>
  </footer>

  <!-- Modal -->
  <dialog id="modal-cancelled-game">
    <article>
      <a href="#close" aria-label="Close" class="close" data-target="modal-cancelled-game" onClick="toggleModal(event)">
      </a>
      <hgroup>

        <h3>Bericht für abgesagtes Spiel erstellen</h3>
        <h6>Das Dropdown-Feld kann durch eintippen der Spielnummer durchsucht werden</h6>
      </hgroup>
      <form method="post">
        <label for="game-number">Spielnummer</label>
        <select id="game-number" type="test" placeholder="12345" name="cancelled" required>
          <option selected disabled value></option>
          <?php
          foreach ($games_array_trimmed as $pairing) {
            echo "<option value=\"" . $pairing[0] . "\" data-player-left=\"" . $pairing[1] . "\" data-player-right=\"" . $pairing[2] . "\">" . $pairing[0] . ": " . $pairing[1] . " <-> " . $pairing[2] . "</option>";
          }
          ?>
        </select>
        <fieldset>
        </fieldset>
      </form>
      <footer>
        <a href="#cancel" role="button" class="secondary" data-target="modal-cancelled-game" onClick="toggleModal(event)">
          Abbrechen
        </a>
        <a class="btn-submit" href="#submit" role="button">
          Erstellen
        </a>
      </footer>
    </article>
  </dialog>

  <script src="assets/scripts.js"></script>
  <script src="assets/pico-modal.js"></script>
</body>

</html>