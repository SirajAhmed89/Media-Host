<?php
namespace PHPMailer\PHPMailer;

class PHPMailer {
    public $SMTPDebug = 0;
    public $Host = '';
    public $Port = 587;
    public $SMTPAuth = true;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = 'tls';
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $CharSet = 'UTF-8';
    public $isHTML = false;
    private $to = [];
    private $error = '';
    
    public function __construct() {
        $this->CharSet = 'UTF-8';
    }
    
    public function setFrom($address, $name = '') {
        $this->From = $address;
        $this->FromName = $name;
    }
    
    public function addAddress($address) {
        $this->to[] = $address;
    }
    
    public function send() {
        $headers = array(
            'From: ' . $this->FromName . ' <' . $this->From . '>',
            'Reply-To: ' . $this->From,
            'MIME-Version: 1.0',
            'Content-Type: ' . ($this->isHTML ? 'text/html' : 'text/plain') . '; charset=' . $this->CharSet,
            'X-Mailer: PHP/' . phpversion()
        );
        
        $smtp = fsockopen(
            $this->SMTPSecure === 'ssl' ? 'ssl://' . $this->Host : $this->Host,
            $this->Port,
            $errno,
            $errstr,
            30
        );
        
        if (!$smtp) {
            $this->error = "Connection failed: $errstr ($errno)";
            return false;
        }
        
        $this->getResponse($smtp);
        
        // Say hello
        fwrite($smtp, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $this->getResponse($smtp);
        
        // Start TLS if needed
        if ($this->SMTPSecure === 'tls') {
            fwrite($smtp, "STARTTLS\r\n");
            $this->getResponse($smtp);
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($smtp, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $this->getResponse($smtp);
        }
        
        // Authenticate
        if ($this->SMTPAuth) {
            fwrite($smtp, "AUTH LOGIN\r\n");
            $this->getResponse($smtp);
            fwrite($smtp, base64_encode($this->Username) . "\r\n");
            $this->getResponse($smtp);
            fwrite($smtp, base64_encode($this->Password) . "\r\n");
            $this->getResponse($smtp);
        }
        
        // Send From
        fwrite($smtp, "MAIL FROM:<{$this->From}>\r\n");
        $this->getResponse($smtp);
        
        // Send To
        foreach ($this->to as $address) {
            fwrite($smtp, "RCPT TO:<{$address}>\r\n");
            $this->getResponse($smtp);
        }
        
        // Send Data
        fwrite($smtp, "DATA\r\n");
        $this->getResponse($smtp);
        
        // Send headers
        foreach ($headers as $header) {
            fwrite($smtp, $header . "\r\n");
        }
        
        // Send subject
        fwrite($smtp, "Subject: {$this->Subject}\r\n");
        
        // Send body
        fwrite($smtp, "\r\n{$this->Body}\r\n.\r\n");
        $this->getResponse($smtp);
        
        // Say goodbye
        fwrite($smtp, "QUIT\r\n");
        
        // Close connection
        fclose($smtp);
        
        return true;
    }
    
    private function getResponse($smtp) {
        $response = '';
        while ($str = fgets($smtp, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        if ($this->SMTPDebug) {
            echo $response . "\n";
        }
        return $response;
    }
    
    public function getError() {
        return $this->error;
    }
} 