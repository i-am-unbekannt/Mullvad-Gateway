<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
session_start();

function get_dns_servers() {
    $dns_servers = [];
    $resolv_conf = "/etc/resolv.conf";

    if (file_exists($resolv_conf) && is_readable($resolv_conf)) {
        $lines = file($resolv_conf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, "nameserver") === 0) {
                $parts = preg_split('/\s+/', $line);
                if (isset($parts[1])) {
                    $dns_servers[] = $parts[1];
                }
            }
        }
    } else {
        return ["error" => "failed to load /etc/resolv.conf."];
    }

    return $dns_servers;
}
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="src/style.main.css">
    <link rel="icon" href="https://mullvad.net/favicon.svg" type="image/svg+xml">
	<title>Mullvad Gateway - Status</title>
</head>
<body>
    <div id="root">
        <div class="frameL">
            <div class="logo">
                <img src="https://mullvad.net/_app/immutable/assets/logo.Ba5MUFAA.svg" style="width: 200px; display: block; margin-left: auto; margin-right: auto;">
            </div>
            <div class="navbar">
                <p style="font-size: 20px;">Mullvad Gateway</p>
                <p style="font-size: 20px;">Build by <a href="https://github.com/i-am-unbekannt" style="font-size: 19px;">i_am_unbekannt</a></p>
            </div>
            <br>
            <br>
            <br>
            <div class="navbar">
                <p><a href="index.php">Status</a></p>
                <br><br>
                <p><a href="/download.php">Download</a></p>
            </div>
        </div>

        <div class="frameM">
        </div>

        <div class="frameR">
            <div class="header-container">
                <h1>Status</h1>
                <div class="button-container">
                    <form method="post">
                        <button type="submit" name="action" value="connect">Connect</button>
                        <button type="submit" name="action" value="disconnect">Disconnect</button>
                        <button type="submit" name="action" value="reload">Reconnect</button>
                    </form>
                </div>
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                    $action = $_POST['action'];
                    $output = '';
                    
                    switch ($action) {
                        case 'connect':
                            $output = shell_exec('mullvad connect 2>&1');
                            sleep(3);
                            header("Refresh:0");
                            break;
                        case 'disconnect':
                            $output = shell_exec('mullvad disconnect 2>&1');
                            sleep(3);
                            header("Refresh:0");
                            break;
                        case 'reconnect':
                            $output = shell_exec('mullvad reconnect 2>&1'); 
                            sleep(3);
                            header("Refresh:0");
                            break;
                        default:
                            $output = "Unbekannte Aktion: $action";
                    }
                }
                ?>
            </div>

            <div class="infoFrameHolder">
                <!-- STATUS INFO -->
                <div class="infoholder">
                    <?php
                    $output = shell_exec('mullvad status 2>&1');

                    $status_data = [
                        "Status" => "Unknown",
                        "Relay" => "N/A",
                        "Location" => "N/A",
                        "IPv4" => "N/A"
                    ];

                    if ($output) {
                        preg_match('/^Connected|Disconnected|Unknown/i', $output, $status_match);
                        preg_match('/Relay:\s+(.*)/i', $output, $relay_match);
                        preg_match('/Visible location:\s+(.*)/i', $output, $location_match);
                        preg_match('/IPv4:\s+([\d\.]+)/i', $output, $ip_match);

                        $status_data['Status'] = $status_match[0] ?? "Unknown";
                        $status_data['Relay'] = $relay_match[1] ?? "N/A";
                        $status_data['Location'] = $location_match[1] ?? "N/A";
                        $status_data['IPv4'] = $ip_match[1] ?? "N/A";
                    }

                    $status_class = strtolower($status_data['Status']);

                    echo "<p class='status {$status_class}'>Status: {$status_data['Status']}</p>";
                    echo "<p>Relay: {$status_data['Relay']}</p>";
                    echo "<p>Location: {$status_data['Location']}</p>";
                    echo "<p>IPv4: {$status_data['IPv4']}</p>";
                    ?>
                </div>

                <!-- CHANGE RELAY -->
                <div class="infoholder">
                    <form method="post">
                        <label for="server">Select Server:</label>
                        <select name="server" id="server" required style="margin-top: 10px;">
                            <option value="">-- Select a server --</option>

                            <?php
                            $relay_output = shell_exec('mullvad relay list 2>&1');
                            $current_country = '';

                            if ($relay_output) {
                                $lines = explode("\n", $relay_output);

                                foreach ($lines as $line) {
                                    $line = trim($line);

                                    if (preg_match('/^([A-Za-z\s]+) \(([a-z]{2})\)$/', $line, $country_match)) {
                                        if ($current_country !== '') {
                                            echo "</optgroup>";
                                        }
                                        $current_country = $country_match[1];
                                        echo "<optgroup label='{$current_country}'>";
                                    }
                                    elseif (preg_match('/^([a-z]{2}-[a-z]{3}-[a-z]{2,4}-\d+).* - (OpenVPN|WireGuard), hosted by (.+) \((.+)\)$/', $line, $server_match)) {
                                        $server_name = $server_match[1];
                                        $server_type = $server_match[2];
                                        $host_info = $server_match[3];
                                        $ownership = $server_match[4];
                                        echo "<option value='{$server_name}'>{$server_name} ({$server_type}, {$ownership})</option>";
                                    }
                                }

                                if ($current_country !== '') {
                                    echo "</optgroup>";
                                }
                            } else {
                                echo "<option value=''>No relays available</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" name="action" id="btnChangeRelay" value="change_relay">Change Relay</button>
                    </form>

                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_relay') {
                        $selected_server = $_POST['server'] ?? '';

                        if (!empty($selected_server)) {
                            $relay_output = shell_exec('mullvad relay list 2>&1');
                            $tunnel_type = '';

                            if ($relay_output) {
                                $lines = explode("\n", $relay_output);
                            
                                foreach ($lines as $line) {
                                    if (preg_match('/^(\S+)\s+\(.*\)\s+-\s+(OpenVPN|WireGuard)/i', trim($line), $match)) {
                                        $server_name = $match[1]; 
                                        $tunnel_type_found = strtolower($match[2]); 
                            
                                        if ($server_name === $selected_server) {
                                            $tunnel_type = $tunnel_type_found;
                                            break;
                                        }
                                    }
                                }
                            }

                            $commandChangeRelay = "mullvad relay set location " . escapeshellarg($selected_server) . " tunnel-protocol " . escapeshellarg($tunnel_type);

                            if (!empty($tunnel_type)) {
                                $output = shell_exec("$commandChangeRelay 2>&1");
                                
                                //echo "<p>Relay switched to: <strong>$selected_server</strong></p>";
                                //echo "<pre>$output</pre>";
                                // header("Location: " . $_SERVER['PHP_SELF']);
                            } else {
                                echo "<p>Could not determine tunnel type for the selected server.</p>";
                            }
                        } else {
                            echo "<p>Please select a server.</p>";
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="infoFrameHolder">
                <!-- STATUS DNS -->
                <div class="infoholder">
                    <?php
                    $dns_servers = get_dns_servers();
                    echo "<p>DNS Server(s):</p>";
                    if (isset($dns_servers['error'])) {
                        echo "<p>Fehler: " . htmlspecialchars($dns_servers['error']) . "</p>";
                    } else {
                        echo "<ul>";
                        foreach ($dns_servers as $server) {
                            echo "<li>" . htmlspecialchars($server) . "</li>";
                        }
                        echo "</ul>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
