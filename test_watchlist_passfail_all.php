<?php
define('TESTING_MODE', true); // Define testing mode to skip redirect in watchlist.php

session_start();
$_SESSION['user_id'] = 19; // Set UserID for testing

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to simulate a request and capture output without displaying it
function runTest($params, $testName) {
    $_GET = $params;
    try {
        ob_start();
        include 'watchlist.php';
        $output = ob_get_clean();
        return [$testName, $output, null];
    } catch (Throwable $e) {
        // Clean up any remaining output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        return [$testName, null, "Error: " . $e->getMessage()];
    }
}

// Test cases for Tests 1–32
$tests = [
    // Genre Filter Tests (Tests 1–11)
    ['params' => ['genre' => ''], 'name' => 'Genre Filter: Extreme Min (?genre=)', 'expected' => ['Dune', 'Barbie', 'Oppenheimer']],
    ['params' => ['genre' => 'A'], 'name' => 'Genre Filter: Min -1 (?genre=A)', 'expected' => ['No movies found']],
    ['params' => ['genre' => 'Drama'], 'name' => 'Genre Filter: Min Boundary (?genre=Drama)', 'expected' => ['The Shawshank Redemption']],
    ['params' => ['genre' => 'Sci-Fi/Adventure'], 'name' => 'Genre Filter: Min +1 (?genre=Sci-Fi/Adventure)', 'expected' => ['Dune', 'Interstellar']],
    ['params' => ['genre' => str_repeat('a', 254)], 'name' => 'Genre Filter: Max -1 (?genre=[254-char string])', 'expected' => ['No movies found']],
    ['params' => ['genre' => str_repeat('a', 255)], 'name' => 'Genre Filter: Max Boundary (?genre=[255-char string])', 'expected' => ['No movies found']],
    ['params' => ['genre' => str_repeat('a', 256)], 'name' => 'Genre Filter: Max +1 (?genre=[256-char string])', 'expected' => ['No movies found']],
    ['params' => ['genre' => 'Comedy/Fantasy'], 'name' => 'Genre Filter: Mid (?genre=Comedy/Fantasy)', 'expected' => ['Barbie']],
    ['params' => ['genre' => str_repeat('a', 500)], 'name' => 'Genre Filter: Extreme Max (?genre=[500-char string])', 'expected' => ['No movies found']],
    ['params' => ['genre' => '<script>alert(1)</script>'], 'name' => 'Genre Filter: Invalid data type (?genre=<script>alert(1)</script>)', 'expected' => ['No movies found']],
    ['params' => ['genre' => 'NonExistent'], 'name' => 'Genre Filter: Other tests (?genre=NonExistent)', 'expected' => ['No movies found']],

    // Watched Status Filter Tests (Tests 12–22)
    ['params' => ['status' => ''], 'name' => 'Watched Status Filter: Extreme Min (?status=)', 'expected' => ['Barbie', 'Dune', 'Parasite']],
    ['params' => ['status' => '-1'], 'name' => 'Watched Status Filter: Min -1 (?status=-1)', 'expected' => ['No movies found']],
    ['params' => ['status' => '0'], 'name' => 'Watched Status Filter: Min Boundary (?status=0)', 'expected' => ['Parasite', 'Chernobyl', 'Arcane']],
    ['params' => ['status' => '1'], 'name' => 'Watched Status Filter: Min +1 (?status=1)', 'expected' => ['Barbie', 'Dune', 'Oppenheimer']],
    ['params' => ['status' => '9'], 'name' => 'Watched Status Filter: Max -1 (?status=9)', 'expected' => ['No movies found']],
    ['params' => ['status' => '10'], 'name' => 'Watched Status Filter: Max Boundary (?status=10)', 'expected' => ['No movies found']],
    ['params' => ['status' => '11'], 'name' => 'Watched Status Filter: Max +1 (?status=11)', 'expected' => ['No movies found']],
    ['params' => ['status' => '0'], 'name' => 'Watched Status Filter: Mid (?status=0)', 'expected' => ['Parasite', 'Chernobyl', 'Arcane']],
    ['params' => ['status' => '999999'], 'name' => 'Watched Status Filter: Extreme Max (?status=999999)', 'expected' => ['No movies found']],
    ['params' => ['status' => 'invalid_string'], 'name' => 'Watched Status Filter: Invalid data type (?status=invalid_string)', 'expected' => ['No movies found']],
    ['params' => ['status' => 'invalid'], 'name' => 'Watched Status Filter: Other tests (?status=invalid)', 'expected' => ['No movies found']],

    // Combined Filters Tests (Tests 23–32)
    ['params' => ['genre' => '', 'status' => '', 'sort' => ''], 'name' => 'Combined Filters: Extreme Min (?genre=&status=&sort=)', 'expected' => ['Barbie', 'Dune', 'Parasite']],
    ['params' => ['genre' => 'A', 'status' => '2', 'sort' => 'invalid'], 'name' => 'Combined Filters: Min -1 (?genre=A&status=2&sort=invalid)', 'expected' => ['No movies found']],
    ['params' => ['genre' => 'Drama', 'status' => '0', 'sort' => 'asc'], 'name' => 'Combined Filters: Min Boundary (?genre=Drama&status=0&sort=asc)', 'expected' => ['The Shawshank Redemption']],
    ['params' => ['genre' => 'Sci-Fi/Adventure', 'status' => '1', 'sort' => 'desc'], 'name' => 'Combined Filters: Min +1 (?genre=Sci-Fi/Adventure&status=1&sort=desc)', 'expected' => ['Dune']],
    ['params' => ['genre' => str_repeat('a', 254), 'status' => '9', 'sort' => 'ascending'], 'name' => 'Combined Filters: Max -1 (?genre=[254-char]&status=9&sort=ascending)', 'expected' => ['No movies found']],
    ['params' => ['genre' => str_repeat('a', 255), 'status' => '10', 'sort' => 'descending'], 'name' => 'Combined Filters: Max Boundary (?genre=[255-char]&status=10&sort=descending)', 'expected' => ['No movies found']],
    ['params' => ['genre' => str_repeat('a', 256), 'status' => '11', 'sort' => 'descendingX'], 'name' => 'Combined Filters: Max +1 (?genre=[256-char]&status=11&sort=descendingX)', 'expected' => ['No movies found']],
    ['params' => ['genre' => 'Comedy/Fantasy', 'status' => '1', 'sort' => 'asc'], 'name' => 'Combined Filters: Mid (?genre=Comedy/Fantasy&status=1&sort=asc)', 'expected' => ['Barbie']],
    ['params' => ['genre' => str_repeat('a', 500), 'status' => '999999', 'sort' => str_repeat('a', 500)], 'name' => 'Combined Filters: Extreme Max (?genre=[500-char]&status=999999&sort=[500-char])', 'expected' => ['No movies found']],
    ['params' => ['genre' => 'invalid_genre', 'status' => 'invalid_status', 'sort' => 'invalid_sort'], 'name' => 'Combined Filters: Invalid data type (?genre=invalid_genre&status=invalid_status&sort=invalid_sort)', 'expected' => ['No movies found']],
];

// Initialize counters
$totalTests = count($tests);
$passedTests = 0;
$failedTests = 0;
$errors = 0;
$testOutput = '';
$failureDetails = [];
$dots = '';
$testResults = [];

// Run tests
foreach ($tests as $index => $test) {
    try {
        // Ensure output buffer is clean
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        [$testName, $output, $error] = runTest($test['params'], $test['name']);
        $testNumber = $index + 1;

        if ($error) {
            $dots .= '<span style="color: red;">E</span>';
            $errors++;
            $testResults[] = "Test $testNumber - " . htmlspecialchars($testName, ENT_QUOTES, 'UTF-8') . ": <span style=\"color: red;\">ERROR</span>";
            $failureDetails[] = "ERROR in Test $testNumber - " . htmlspecialchars($testName, ENT_QUOTES, 'UTF-8') . ": $error";
        } else {
            $passed = true;
            foreach ($test['expected'] as $expected) {
                if (strpos($output, $expected) === false) {
                    $passed = false;
                    break;
                }
            }
            if ($passed) {
                $dots .= '<span style="color: green;">.</span>';
                $passedTests++;
                $testResults[] = "Test $testNumber - " . htmlspecialchars($testName, ENT_QUOTES, 'UTF-8') . ": <span style=\"color: green;\">PASSED</span>";
            } else {
                $dots .= '<span style="color: red;">F</span>';
                $failedTests++;
                $testResults[] = "Test $testNumber - " . htmlspecialchars($testName, ENT_QUOTES, 'UTF-8') . ": <span style=\"color: red;\">FAILED</span>";
                $escapedOutput = htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
                $failureDetails[] = "FAILURE in Test $testNumber - " . htmlspecialchars($testName, ENT_QUOTES, 'UTF-8') . "\nExpected to find: " . implode(', ', $test['expected']) . "\nGot: $escapedOutput";
            }
        }
    } catch (Throwable $e) {
        $dots .= '<span style="color: red;">E</span>';
        $errors++;
        $testNumber = $index + 1;
        $testResults[] = "Test $testNumber - " . htmlspecialchars($test['name'], ENT_QUOTES, 'UTF-8') . ": <span style=\"color: red;\">ERROR</span>";
        $failureDetails[] = "ERROR in Test $testNumber - " . htmlspecialchars($test['name'], ENT_QUOTES, 'UTF-8') . ": Exception: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}

// Calculate execution time
$startTime = microtime(true);
// (Tests are already run, so we use the current time for simplicity)
$executionTime = microtime(true) - $startTime;

// Output in PHPUnit-like format with individual test results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Watchlist Test Results</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        pre {
            white-space: pre-wrap;
            font-size: 14px;
        }
        .header {
            border-bottom: 1px solid #d4d4d4;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .summary {
            margin-top: 20px;
            border-top: 1px solid #d4d4d4;
            padding-top: 10px;
        }
        .failure {
            color: #ff5555;
            margin-top: 20px;
        }
        .pass {
            color: #55ff55;
        }
        .error {
            color: #ff5555;
        }
        .test-result {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<pre>
<span class="header">Watchlist Test Suite (Tests 1–32)

Time: <?php echo number_format($executionTime, 2); ?> seconds, Memory: <?php echo number_format(memory_get_usage() / 1024 / 1024, 2); ?> MB

</span>
<span class="test-result"><?php echo implode("\n", $testResults); ?></span>

<?php echo $dots; ?>


<?php if ($failedTests > 0 || $errors > 0) { ?>
<span class="failure">Failures/Errors:
<?php
foreach ($failureDetails as $detail) {
    echo "\n$detail\n\n";
}
?>
</span><?php } ?>

<span class="summary">Tests: <?php echo $totalTests; ?>, Passed: <span class="pass"><?php echo $passedTests; ?></span>, Failures: <span class="error"><?php echo $failedTests; ?></span>, Errors: <span class="error"><?php echo $errors; ?></span>
</span>
</pre>
</body>
</html>