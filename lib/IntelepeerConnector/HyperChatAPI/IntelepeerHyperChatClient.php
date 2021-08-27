<?php

namespace Inbenta\IntelepeerConnector\HyperChatAPI;

use Inbenta\ChatbotConnector\HyperChatAPI\HyperChatClient;
use Inbenta\IntelepeerConnector\ExternalAPI\IntelepeerAPIClient;

class IntelepeerHyperChatClient extends HyperChatClient
{
    private $eventHandlers = array();
    private $appConf;
    private $externalId;
    protected $session;

    function __construct($config, $lang, $session, $appConf, $externalClient)
    {
        // CUSTOM added session attribute to clear it
        $this->session = $session;
        $this->appConf = $appConf;
        parent::__construct($config, $lang, $session, $appConf, $externalClient);
    }

    //Instances an external client
    protected function instanceExternalClient($externalId, $appConf)
    {
        $externalFlowId = IntelepeerAPIClient::getFlowIdFromExternalId($externalId);
        if (is_null($externalFlowId)) {
            return null;
        }
        $externalDnis = IntelepeerAPIClient::getDnisFromExternalId($externalId);
        if (is_null($externalDnis)) {
            return null;
        }
        $externalAni = IntelepeerAPIClient::getAniFromExternalId($externalId);
        if (is_null($externalAni)) {
            return null;
        }
        $externalClient = new IntelepeerAPIClient(null, $appConf);
        $externalClient->setSenderFromId($externalFlowId, $externalDnis, $externalAni, "sms");
        return $externalClient;
    }

    public static function buildExternalIdFromRequest($config)
    {
        $request = json_decode(file_get_contents('php://input'), true);

        $externalId = null;
        if (isset($request['trigger'])) {
            //Obtain user external id from the chat event
            $externalId = self::getExternalIdFromEvent($config, $request);
        }
        return $externalId;
    }
}
