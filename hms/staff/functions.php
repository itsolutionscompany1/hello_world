<?php
/**
 * Reliably finds the server's private LAN IPv4 address.
 *
 * This function executes system commands to get all network interface details
 * and then searches for an IP address within the private RFC 1918 ranges.
 *
 * @return string The server's LAN IP or a default message if not found.
 */
function getServerLanIp() {
    // Determine the correct command based on the operating system
    $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'ipconfig' : 'ip addr';

    // For older Unix-like systems that might not have 'ip addr'
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && !is_executable('/sbin/ip')) {
        $command = 'ifconfig';
    }

    // Execute the command, suppressing errors if shell_exec is disabled
    $output = @shell_exec($command);

    if ($output) {
        // Use a regular expression to find all IPv4 addresses in the output
        preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $output, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $ip) {
                // Check if the found IP is a private address and not the loopback address
                // filter_var returns FALSE if the IP IS in a private/reserved range, which is what we want.
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && $ip !== '127.0.0.1') {
                    // We found the private LAN IP. Return it immediately.
                    return $ip;
                }
            }
        }
    }

    // If the command-line method fails, fall back to SERVER_ADDR as a last resort
    if (isset($_SERVER['SERVER_ADDR'])) {
        return $_SERVER['SERVER_ADDR'];
    }

    return 'LAN IP not found'; // Default message if no IP could be found
}

// Get the LAN IP by calling the function
$serverLanIp = getServerLanIp();
?>