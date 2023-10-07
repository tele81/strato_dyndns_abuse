<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pfad zur Textdatei, in der "Abuse" IP-Adressen gespeichert sind
$abuse_ip_list_file = '/var/www/html/abuse_ip_list.txt';

// Funktion zum Abrufen der aktuellen IP-Adresse
function getCurrentIP()
{
    $url = 'https://DEINEDOMAIN:DEINPWD@dyndns.strato.com/nic/update?hostname=DEINEDOMAIN';

    $options = array(
        'http' => array(
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36\r\n"
        )
    );

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    // Extrahieren Sie die IP-Adresse aus der Antwort
    preg_match('/\d+\.\d+\.\d+\.\d+/', $response, $matches);
    $ip = $matches[0];

    return $ip;
}

// Funktion Reconnect via UPnP
function reconnectFritzBoxUPnP($host = 'fritz.box', $port = 49000, $debug = false)
{
    $xml_request = <<<EOT
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <s:Body>
    <u:ForceTermination xmlns:u="urn:schemas-upnp-org:service:WANIPConnection:1"></u:ForceTermination>
  </s:Body>
</s:Envelope>
EOT;

    // Setze die URL für die UPnP-Verbindung
    $upnp_url = "http://$host:$port/igdupnp/control/WANIPConn1";

    // Erstelle eine cURL-Sitzung
    $ch = curl_init();

    // Setzen Sie die cURL-Optionen
    curl_setopt_array($ch, array(
        CURLOPT_URL => $upnp_url,
        CURLOPT_POST => 1,
        CURLOPT_HTTPHEADER => array(
            'SOAPAction: "urn:schemas-upnp-org:service:WANIPConnection:1#ForceTermination"',
            'Content-Type: text/xml; charset="utf-8"',
            'Content-Length: ' . strlen($xml_request),
        ),
        CURLOPT_POSTFIELDS => $xml_request,
        CURLOPT_RETURNTRANSFER => true,
    ));

    // Führe die cURL-Anfrage aus
    $response = curl_exec($ch);

    // Überprüfe auf cURL-Fehler
    if ($response === false) {
        echo 'CURL-Fehler: ' . curl_error($ch) . PHP_EOL;
    } else {
        // Hier können Sie mit $response arbeiten, z. B. die Antwort anzeigen
        echo 'CURL Response: ' . $response . PHP_EOL;
    }

    // Beende die cURL-Sitzung
    curl_close($ch);
}

// Holen Sie sich die aktuelle IP-Adresse
$current_ip = getCurrentIP();

// Überprüfen Sie, ob die derzeitige IP-Adresse bereits in der "Abuse" IP-Adressenliste ist oder die Webseite "abuse" zurückgibt
if (strpos($current_ip, 'abuse') !== false || in_array($current_ip, file($abuse_ip_list_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
    // Die derzeitige IP-Adresse ist in der "Abuse" IP-Adressenliste oder die Webseite gibt "abuse" zurück
    echo 'Die derzeitige IP-Adresse ' . $current_ip . ' ist in der "Abuse" IP-Adressenliste oder die Webseite gibt "abuse" zurück. Neuverbindung wird durchgeführt.' . PHP_EOL;

    // Verwenden Sie die UPnP-Funktion, um die FRITZ!Box neu zu verbinden
    reconnectFritzBoxUPnP();
} else {
    // Alles OK, keine Neuverbindung erforderlich
    echo 'Alles OK. Keine Neuverbindung erforderlich.' . PHP_EOL;
}

// Haupt-Schleife
while (true) {
    try {
        // Warten Sie 60 Minuten
        sleep(3600);

        // Holen Sie sich die aktuelle IP-Adresse erneut
        $new_ip = getCurrentIP();

        echo 'Aktuelle IP-Adresse: ' . $new_ip . PHP_EOL;

        // Überprüfen, ob sich die IP-Adresse geändert hat
        if ($new_ip !== $current_ip) {
            // Die IP-Adresse hat sich geändert

            // Wenn die Antwort "abuse" enthält, die IP-Adresse hinzufügen und neu verbinden
            if (strpos($new_ip, 'abuse') !== false) {
                $current_ip = $new_ip;
                echo 'Die IP-Adresse ' . $current_ip . ' wurde als "Abuse" erkannt. Neuverbindung mit FritzBox wird durchgeführt.' . PHP_EOL;

                // IP zur "Abuse" IP-Adressenliste hinzufügen
                file_put_contents($abuse_ip_list_file, $current_ip . PHP_EOL, FILE_APPEND);

                // Verwenden Sie die UPnP-Funktion, um die FRITZ!Box neu zu verbinden
                reconnectFritzBoxUPnP();
            }
        }
    } catch (Exception $e) {
        echo 'Fehler beim Abrufen der IP-Adresse oder Neuverbinden mit der FritzBox: ' . $e->getMessage() . PHP_EOL;
    }
}
?>
