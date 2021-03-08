<?php

namespace Inbenta\IntelepeerConnector\HyperChatAPI;

use Inbenta\ChatbotConnector\HyperChatAPI\HyperChatClient;
//use Inbenta\IntelepeerConnector\ExternalAPI\IntelepeerAPIClient;

class IntelepeerHyperChatClient extends HyperChatClient
{
    private $eventHandlers = array();
    private $session;
    private $appConf;
    private $externalId;

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
        return null; //$externalClient;
    }

    public static function buildExternalIdFromRequest($config)
    {
        return null;
    }

}
