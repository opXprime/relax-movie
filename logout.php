<?php
session_start();
session_unset();
session_destroy();

echo "Logged out. <a href='login.html'>Login again</a>";
?>
