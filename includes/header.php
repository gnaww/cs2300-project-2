<header>
  <span id="title">osu! Players</span>

  <nav id="navbar">
    <ul>
      <?php
        foreach($pages as $page => $page_name) {
          echo("<li><a href='index.php" . $page . "'>".$page_name."</a></li>");
        }
      ?>
    </ul>
  </nav>
</header>
