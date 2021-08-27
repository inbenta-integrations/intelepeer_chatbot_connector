<?php

namespace Inbenta\IntelepeerConnector\APIClientCustom;

use Inbenta\ChatbotConnector\ChatbotAPI\ChatbotAPIClient;
use \Exception;

class ChatbotAPIClientCustom extends ChatbotAPIClient
{

    /**
     * Get all the available variables of the conversation
     */
    public function getVariables()
    {
        // Update access token if needed
        $this->updateAccessToken();
        //Update sessionToken if needed
        $this->updateSessionToken();

        // Headers
        $headers = array(
            "x-inbenta-key:" . $this->key,
            "Authorization: Bearer " . $this->accessToken,
            "x-inbenta-session: Bearer " . $this->sessionToken
        );

        $response = $this->call("/v1/conversation/variables", "GET", $headers, []);

        if (isset($response->errors)) {
            throw new Exception($response->errors[0]->message, $response->errors[0]->code);
        } else {
            return $response;
        }
    }
}
