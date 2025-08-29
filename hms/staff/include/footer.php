<?php
// Make sure the function is included or defined above this footer code.

/**
 * Gets the server's internal LAN IP address.
 */
function getServerLanIp() {
    $hostname = gethostname();
    if ($hostname) {
        $ip = gethostbyname($hostname);
        if ($ip && $ip != '127.0.0.1') {
            return $ip;
        }
    }
    // Fallback methods...
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = @shell_exec('ipconfig');
        if ($output && preg_match('/IPv4 Address.*:\s*([\d\.]+)/', $output, $matches)) {
            return $matches[1];
        }
    } else {
        $output = @shell_exec('hostname -I');
        if ($output) {
            return trim(explode(' ', $output)[0]);
        }
    }
    return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
}

$serverIp = getServerLanIp();
?>

<footer>
    <div class="footer-inner">
        <div class="pull-left">
            &copy; <?php echo date("Y"); ?> <span class="text-bold text-uppercase">Medico HR(staff Portal)</span>
        </div>
        <div class="pull-right">
            <span class="go-top"><i class="ti-angle-up"></i></span>
        </div>
        <div style="text-align: center;">
            <?php
            // Determine which session variable is set for the username
            $userName = '';
            if (isset($_SESSION['user_name'])) {
                $userName = $_SESSION['user_name'];
            } elseif (isset($_SESSION['username'])) {
                $userName = $_SESSION['username'];
            }

            // If a username was found, display the user info
            if (!empty($userName)) :
            ?>
                <span>
                    Logged in as: <strong><?php echo htmlspecialchars($userName); ?></strong> | IP Address: <strong><?php echo htmlspecialchars($serverIp); ?></strong>
                </span>
            <?php endif; ?>
        </div>
    </div>
</footer>