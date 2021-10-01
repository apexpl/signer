<?php
declare(strict_types = 1);

namespace Apex\Signer\Utils;

/**
 * HTTP Client
 */
class HttpClient extends AbstractUtils
{

    // Properties
    private ?string $auth_username = null;
    private ?string $auth_signature = null;

    /**
     * Send request
     */
    public function send(string $path, array $request = [], bool $is_json = false,string $method = 'POST'):?array
    {

        // Initialize
        if ($is_json === true) {
            $request = json_encode($request);
            $content_type = 'application/json';
        } else {  
            $request = http_build_query($request);
            $content_type = 'application/x-www-form-urlencoded';
        }
        $length = strlen($request);

        // Open socket
        if (!$sock = fsockopen('ssl://api.apexpl.io', 443, $errno, $errstr, 5)) { 
            throw new \Exception("Unable to connect to host, api.apexpl.io");
        }

        // Send header
        fwrite($sock, "$method /api/$path HTTP/1.1\r\n");
        fwrite($sock, "Host: api.apexpl.io\r\n");
        fwrite($sock, "User-Agent Apex Signer/1.0\r\n");
        fwrite($sock, "Connection: close\r\n");

        // Add auth theaders, if needed
        if ($this->auth_signature !== null) { 
            fwrite($sock, "API-Username: $this->auth_username\r\n");
            fwrite($sock, "API-Signature: $this->auth_signature\r\n");
        }

        // Add POST headers and body
        if ($method == 'POST') { 
            fwrite($sock, "Content-type: $content_type\r\n");
            fwrite($sock, "Content-length: $length\r\n\r\n");
            fwrite($sock, $request);
        }
        fwrite($sock, "\r\n");

        // Get response
        $res = '';
        while (!feof($sock)) { 
            $res .= fgets($sock, 1024);
        }
        fclose($sock);

        // Parse response
        list($headers, $body) = explode("\n\n", str_replace("\r", "", $res), 2);
        $lines = explode("\n", $body);
        $body = $lines[1];

        if ($body == '') { 
            throw new \Exception("Received empty response from API server.");
        } elseif (!$json = json_decode($body, true)) {
echo "NO JSON: $res\n"; exit;
            throw new \Exception("Did not receive a JSON response from API server, instead got: $body");
        } elseif ($json['status'] != 'ok') { 
            throw new \Exception("Received error from API server sending to $path, " . $json['message']);
        }

        // Return
        return $json['data'];
    }

    /**
     * Authenticate
     */
    public function authenticate(string $username, string $key_file = '', ?string $pem_password = null):void
    {

        // Get auth challenge
        if (!$res = $this->send('enduro/get_auth_challenge', ['username' => $username])) { 
            throw new \Exception("Unable to obtain auth challenge from API server.");
        } elseif (!isset($res['challenge'])) { 
            throw new \Exception("Unable to obtain auth challenge from API server.");
        }

        // Check for blank key file
        if ($key_file == '') { 
            $key_file = $this->getConfDir() . '/keys/' . $username . '.pem';
        }

        // Get private key
        if (!file_exists($key_file)) { 
            throw new \Exception("Unable to authenticate HTTP request as key file does not exist, $key_file");
        }
        $private_key = file_get_contents($key_file);

        // Unlock private key
        $privkey = $this->unlockPrivateKey($private_key, $pem_password);

        // Sign challenge
        openssl_sign($res['challenge'], $signature, $privkey, 'sha384');
        $this->auth_signature = bin2hex($signature);
        $this->auth_username = $username;
    }

}


