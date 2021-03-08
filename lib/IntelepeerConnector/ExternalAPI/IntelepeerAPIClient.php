<?php

namespace Inbenta\IntelepeerConnector\ExternalAPI;


class IntelepeerAPIClient
{
    public $from;
    public $type;

    /**
     * Intelepeer SDK constructor
     *
     * @param string $auth
     * @param array $request
     */
    public function __construct($request = null)
    {
        $this->from = isset($request['idIntelepeer']) ? str_replace("+", "", $request['idIntelepeer']) : "";
        $this->type = isset($request['type']) ? $request['type'] : "";
    }


    /**
     * Build an external session Id using the following pattern:
     * @param string $token
     * @return string|null
     */
    public static function buildExternalIdFromRequest(string $token)
    {
        $request = json_decode(file_get_contents('php://input'), true);

        $sessionPrefix = "";
        if (isset($_SERVER["HTTP_X_SESSION"]) && $_SERVER["HTTP_X_SESSION"] !== "") {
            $sessionPrefix = $_SERVER["HTTP_X_SESSION"];
        }
        if (isset($request['idIntelepeer']) && isset($_SERVER["HTTP_X_INTELEPEER_TOKEN"]) && $_SERVER["HTTP_X_INTELEPEER_TOKEN"] === $token) {
            $session = 'intelepeer-' . ($sessionPrefix !== "" ? $sessionPrefix . '-' : '');
            $session .= str_replace("+", "", $request['idIntelepeer']);
            return $session;
        }
        return null;
    }

    /**
     * Send an outgoing message.
     *
     * @param array $message
     * 
     */
    private function send(string $message, string $directCall = "", string $phoneToTransfer = "")
    {
        $phoneToTransfer = trim($phoneToTransfer) === "" ? "-" : $phoneToTransfer;
        $response = [
            "message" => $message,
            "directCall" => $directCall,
            "phoneToTransfer" => $phoneToTransfer
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        die;
    }

    /**
     * Sends a message to Intelepeer. Needs a message formatted with the Intelepeer notation
     *
     * @param  Array $message
     */
    public function sendMessage(string $message, string $directCall = "", string $phoneToTransfer = "")
    {
        $messageSend = false;
        $text = trim($message);
        if ($text !== "") {
            $text = strpos($text, ".") === 0 ? substr($text, 1, strlen($text)) : $text;
            $messageSend = $this->send(trim($text), trim($directCall), trim($phoneToTransfer));
        }
        return $messageSend;
    }

    /**
     *   Method needed
     */
    public function showBotTyping($show = true)
    {
        return true;
    }


    /**
     * Sends a message to Intelepeer. Needs a message formatted with the Intelepeer notation
     */
    public function sendTextMessage($text)
    {
        return true;
    }
}
