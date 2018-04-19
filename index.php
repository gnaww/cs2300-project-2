<?php
include("includes/init.php");

// Connect to database
$db = new PDO('sqlite:data.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get records matching SQL query from database
function query_db($db, $sql, $params) {
  $query = $db->prepare($sql);
  if ($query && $query->execute($params)) {
    return $query;
  }
  return NULL;
}

// Swap two variables
function swap(&$a, &$b) {
  $temp = $a;
  $a = $b;
  $b = $temp;
}

// Print table row for each player
function print_player($record) {
  echo "<tr>";
  echo "<th>#" . number_format(floatval($record["rank"])) . "</th>";
  echo "<th>" . $record["name"] . "</th>";
  echo "<th>" . $record["country"] . "</th>";
  echo "<th>" . sprintf("%0.2f", $record["accuracy"]) . "% </th>";
  echo "<th>" . number_format(floatval($record["pp"])) . "pp </th>";
  echo "</tr>";
}

// Create SQL search query string
function make_search_sql($name, $country, &$params) {
  $sql = "SELECT * FROM players WHERE
          ((rank >= :min_rank) AND (rank <= :max_rank)) AND
          ((accuracy >= :min_acc) AND (accuracy <= :max_acc)) AND
          (pp >= :min_pp) AND (pp <= :max_pp)";

  if ($name != "") {
    $sql = $sql . " AND (name LIKE '%' || :name || '%')";
    $params[":name"] = $name;
  }
  if ($country != NULL) {
    $sql = $sql . " AND (country = :country)";
    $params[":country"] = $country;
  }
  $sql = $sql . " ORDER BY rank ASC";
  return $sql;
}

// Array of error/success notifications to user
$notifications = array();

// If at least one search field has a value, can do a search
if (isset($_GET["submit_search"])) {
  if (!empty($_GET["min-rank"]) || !empty($_GET["max-rank"]) || !empty($_GET["name"]) || !empty($_GET["country"]) ||
      !empty($_GET["min-acc"]) || !empty($_GET["max-acc"]) || !empty($_GET["min-pp"]) || !empty($_GET["max-pp"])) {
    $c_min_rank_search = filter_input(INPUT_GET, 'min-rank', FILTER_VALIDATE_INT); // FALSE if empty
    $c_max_rank_search = filter_input(INPUT_GET, 'max-rank', FILTER_VALIDATE_INT); // FALSE if empty
    $c_name_search = trim(filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING)); // "" if empty
    $c_country_search = filter_input(INPUT_GET, 'country', FILTER_SANITIZE_STRING); // NULL if empty
    $c_min_acc_search = filter_input(INPUT_GET, 'min-acc', FILTER_VALIDATE_FLOAT); // FALSE if empty
    $c_max_acc_search = filter_input(INPUT_GET, 'max-acc', FILTER_VALIDATE_FLOAT); // FALSE if empty
    $c_min_pp_search = filter_input(INPUT_GET, 'min-pp', FILTER_VALIDATE_INT); // FALSE if empty
    $c_max_pp_search = filter_input(INPUT_GET, 'max-pp', FILTER_VALIDATE_INT); // FALSE if empty
    $valid_search = TRUE;

    // Give search parameters default values if empty
    if ($c_min_rank_search == FALSE) {
      $c_min_rank_search = 1;
    }
    if ($c_max_rank_search == FALSE) {
      $c_max_rank_search = PHP_INT_MAX;
    }
    if ($c_min_acc_search == FALSE) {
      $c_min_acc_search = 0.0;
    }
    if ($c_max_acc_search == FALSE) {
      $c_max_acc_search = 100.0;
    }
    if ($c_min_pp_search == FALSE) {
      $c_min_pp_search = 0;
    }
    if ($c_max_pp_search == FALSE) {
      $c_max_pp_search = PHP_INT_MAX;
    }

    // Validate defined search parameters in context of osu! player statistics
    if ($c_min_rank_search < 1 || $c_max_rank_search < 1) {
      $valid_search = FALSE;
    }
    if ($c_country_search != NULL && !in_array($c_country_search, $countries)) {
      $valid_search = FALSE;
    }
    if (($c_min_acc_search < 0 || $c_min_acc_search > 100) || ($c_max_acc_search < 0 || $c_max_acc_search > 100)) {
      $valid_search = FALSE;
    }
    if ($c_min_pp_search < 0 || $c_max_pp_search < 0) {
      $valid_search = FALSE;
    }
  }
  else {
    $valid_search = FALSE;
    array_push($notifications, "<li class='error'>No search parameters provided or invalid search query.</li>");
  }
}

if (isset($_POST["submit_add"])) {
  $c_rank = filter_input(INPUT_POST, 'rank', FILTER_VALIDATE_INT);
  $c_name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
  $c_country = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING);
  $c_accuracy = filter_input(INPUT_POST, 'accuracy', FILTER_VALIDATE_FLOAT);
  $c_pp = filter_input(INPUT_POST, 'pp', FILTER_VALIDATE_INT);
  $valid_data = TRUE;

  // Validate data in context of osu! player statistics
  if ($c_rank < 1) {
    $valid_data = FALSE;
  }
  if (strlen($c_name) < 1) {
    $valid_data = FALSE;
  }
  if (!in_array($c_country, $countries)) {
    $valid_data = FALSE;
  }
  if ($c_accuracy < 0 || $c_accuracy > 100) {
    $valid_data = FALSE;
  }
  if ($c_pp < 0) {
    $valid_data = FALSE;
  }

  // Check if new player is unique in database
  $sql = "SELECT * FROM players WHERE rank = :c_rank OR name = :c_name";
  $params = array(":c_rank" => $c_rank, ":c_name" => $c_name);
  $records = query_db($db, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);
  // If return more than 0 rows, then that means there is at least 1 match
  if (count($records) > 0) {
    $valid_data = FALSE;
  }
  // If everything is good, insert into database
  if ($valid_data == TRUE) {
    $sql = "INSERT INTO players (rank, name, country, accuracy, pp) VALUES
            (:c_rank, :c_name, :c_country, :c_accuracy, :c_pp)";
    $params = array(":c_rank" => $c_rank,
                    ":c_name" => $c_name,
                    ":c_country" => $c_country,
                    ":c_accuracy" => $c_accuracy,
                    ":c_pp" => $c_pp);
    $q = query_db($db, $sql, $params);
    if ($q) {
      array_push($notifications, "<li class='success'>Successfully added player.</li>");
    }
    else {
      array_push($notifications, "<li class='error'>Failed to add player.</li>");
    }
  }
  else {
    array_push($notifications, "<li class='error'>Invalid or not unique player data. Failed to add player.</li>");
  }
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" type="text/css" href="styles/all.css" media="all" />
  <script src="scripts/site.js"></script>
  <title>osu! Players</title>
</head>

<body>
  <div id="wrapper">
    <?php include("includes/header.php") ?>
    <div id="content">

      <div id="logo">
        <!-- Game logo from osu.ppy.sh -->
        <img alt="osu!" src="/images/osu.png">
      </div>
      <?php
        // Print out success/error notifications to user
        echo "<ul>";
        foreach($notifications as $notification) {
          echo $notification;
        }
        echo "</ul>";
      ?>
      <div id="search"></div>
      <button class="search-toggle" onclick="toggleSearch()">
        Search Players
      </button>
      <div id="search-box" class="hidden">
        <form class="search-form" action="index.php" method="get">
          Rank: <input type="number" name="min-rank" min="1" class="small-num-min" placeholder="Min"> -
                <input type="number" name="max-rank" min="1" class="small-num-max" placeholder="Max">
          Name: <input type="text" name="name" class="textbox" maxlength="50">
          Country: <select name="country" class="dropdown"><?php echo $country_options ?></select>
          Accuracy: <input type="number" name="min-acc" min="0" max="100" placeholder="Min %" class="med-num-min" step=".01"> -
                    <input type="number" name="max-acc" min="0" max="100" placeholder="Max %" class="med-num-max" step=".01">
          Performance Points: <input type="number" name="min-pp" min="0" placeholder="Min" class="med-num-min"> -
                              <input type="number" name="max-pp" min="0" placeholder="Max" class="med-num-max">
          <button type="submit" name="submit_search" id="search-button">Search</button>
        </form>
      </div>

      <div class="search-results">
      <?php
        if ($valid_search) {
          echo "<h2 class='search-results-label'>Search Results</h2>";
          // Swap min/max values if out of order
          if ($c_min_rank_search > $c_max_rank_search) {
            swap($c_min_rank_search, $c_max_rank_search);
          }
          if ($c_min_acc_search > $c_max_acc_search) {
            swap($c_min_acc_search, $c_max_acc_search);
          }
          if ($c_min_pp_search > $c_max_pp_search) {
            swap($c_min_pp_search, $c_max_pp_search);
          }

          $params = array(
            ":min_rank" => $c_min_rank_search,
            ":max_rank" => $c_max_rank_search,
            ":min_acc" => $c_min_acc_search,
            ":max_acc" => $c_max_acc_search,
            ":min_pp" => $c_min_pp_search,
            ":max_pp" => $c_max_pp_search);
          $sql = make_search_sql($c_name_search, $c_country_search, $params);
          $records = query_db($db, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);
          if (isset($records) && !empty($records)) {
            echo "<table>
                    <tr class='table-header'>
                      <th>Rank</th>
                      <th>Name</th>
                      <th>Country</th>
                      <th>Accuracy</th>
                      <th>Performance Points</th>
                    </tr>";
            foreach($records as $record) {
              print_player($record);
            }
            echo "</table>";
          }
          else {
            echo "<p>No players matching search query found.</p>";
          }
        }
      ?>
      </div>

      <div id="players"></div>
      <div class="players-label">
        All Players
      </div>
      <div class="players-table">
        <?php
          $records = query_db($db, "SELECT * FROM players ORDER BY rank ASC", array())->fetchAll(PDO::FETCH_ASSOC);
          if (isset($records) and !empty($records)) {
            echo "<table>
                    <tr class='table-header'>
                      <th>Rank</th>
                      <th>Name</th>
                      <th>Country</th>
                      <th>Accuracy</th>
                      <th>Performance Points</th>
                    </tr>";
            foreach($records as $record) {
              print_player($record);
            }
            echo "</table>";
          }
          else {
            echo "<p>No players found.</p>";
          }
        ?>
      </div>

      <div id="add"></div>
      <div class="add-label">
        Add New Player
      </div>
      <div class="add-box">
        <form class="add-form" action="index.php" method="post">
          Rank: <input type="number" name="rank" min="1" class="small-num" required>
          Name: <input type="text" name="name" class="textbox" maxlength="50" required>
          Country: <select name="country" class="dropdown" required><?php echo $country_options ?></select>
          Accuracy: <input type="number" name="accuracy" min="0" max="100" placeholder="0-100%" class="med-num-max" step=".01" required>
          Performance Points: <input type="number" name="pp" min="0" class="med-num-max" required>
          <button type="submit" name="submit_add" id="add-button">Add New Player</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
