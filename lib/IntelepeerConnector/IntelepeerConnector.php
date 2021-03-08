<?php

namespace Inbenta\IntelepeerConnector;

use Exception;
use Inbenta\ChatbotConnector\ChatbotConnector;
use Inbenta\ChatbotConnector\Utils\SessionManager;
use Inbenta\IntelepeerConnector\ExternalAPI\IntelepeerAPIClient;
use Inbenta\IntelepeerConnector\ExternalDigester\IntelepeerDigester;

## Customized Chatbot API
use Inbenta\IntelepeerConnector\APIClientCustom\ChatbotAPIClientCustom as ChatbotAPIClient;


class IntelepeerConnector extends ChatbotConnector
{
    public function __construct($appPath)
    {
        // Initialize and configure specific components for Intelepeer
        try {
            parent::__construct($appPath);
            // Initialize base components
            $request = json_decode(file_get_contents('php://input'), true);
            $conversationConf = [
                'configuration' => $this->conf->get('conversation.default'),
                'userType' => $this->conf->get('conversation.user_type'),
                'environment' => $this->environment,
                'source' => $this->conf->get('conversation.source')
            ];

            $this->createSessionId();

            $this->session   = new SessionManager($this->getExternalIdFromRequest());
            $this->botClient = new ChatbotAPIClient($this->conf->get('api.key'), $this->conf->get('api.secret'), $this->session, $conversationConf);

            // Try to get the translations from ExtraInfo and update the language manager
            $this->getTranslationsFromExtraInfo('intelepeer', 'translations');

            // Instance application components
            $externalClient    = new IntelepeerAPIClient($request); // Instance Intelepeer client
            $chatClient        = null; //new IntelepeerHyperChatClient($this->conf->get('chat.chat'), $this->lang, $this->session, $this->conf, $externalClient);  // Instance HyperchatClient for Intelepeer
            $externalDigester  = new IntelepeerDigester($this->lang, $this->conf->get('conversation.digester'), $this->session, $this); // Instance Intelepeer digester

            $this->initComponents($externalClient, $chatClient, $externalDigester);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
            die();
        }
    }


    /**
     * Return external id from request (Hyperchat of Intelepeer)
     */
    protected function getExternalIdFromRequest()
    {
        // Try to get user_id from a Intelepeer message request
        $externalId = IntelepeerAPIClient::buildExternalIdFromRequest($this->conf->get('intelepeer.token'));
        if (empty($externalId)) {
            throw new Exception("Invalid request");
            die();
        }
        return $externalId;
    }

    /**
     *	Override from parent
     */
    protected function returnOkResponse()
    {
        return true;
    }

    /**
     * Overwritten
     *	Check if it's needed to perform any action other than a standard user-bot interaction
     */
    protected function handleNonBotActions($digestedRequest)
    {
        if (isset($digestedRequest[0])) {
            $this->handleWelcomeMessage($digestedRequest[0]);
        }
        if ($this->session->get('askingForEscalation', false)) {
            $this->handleEscalation($digestedRequest);
        }
    }

    /**
     * Overwritten
     * @param [type] $externalRequest
     * @return void
     */
    public function handleBotActions($externalRequest)
    {
        $needEscalation = false;
        $directCall = "";
        foreach ($externalRequest as $message) {
            // Check if is needed to execute any preset 'command'
            $this->handleCommands($message);

            // Store the last user text message to session
            $this->saveLastTextMessage($message);
            // Send the messages received from the external service to the ChatbotAPI
            $botResponse = $this->sendMessageToBot($message);
            $directCall = $this->checkDirectCall($botResponse);

            $this->validateInitialVariable();
            $this->validateExpectingVar();

            $phoneToTransfer = $this->checkPhoneToTransfer($directCall);

            $needEscalation = $this->checkEscalation($botResponse) ? true : $needEscalation;
            if ($needEscalation) {
                $this->deleteLastMessage($botResponse);
                $this->handleEscalation($botResponse);
            }
            // Send the messages received from ChatbotApi back to the external service
            $this->sendMessagesToExternal($botResponse, $directCall, $phoneToTransfer);
        }
    }

    /**
     *	Retrieve Language translations from ExtraInfo
     */
    protected function getTranslationsFromExtraInfo($parentGroupName, $translationsObjectName)
    {
        $translations = [];
        $language = $this->conf->get('conversation.default.lang');
        if (isset($translations[$language]) && count($translations[$language]) && is_array($translations[$language][0])) {
            $this->lang->addTranslations($translations[$language][0]);
        }
    }

    /**
     * Check if the message is sys-welcome
     * @param array $message
     */
    protected function handleWelcomeMessage(array $message)
    {
        if (isset($message["message"]) && $message["message"] === "sys-welcome") {
            $welcome = [
                "directCall" => $message["message"]
            ];
            $botResponse = $this->sendMessageToBot($welcome);
            $this->sendMessagesToExternal($botResponse);
            die;
        }
    }

    /**
     * Create a new session ID
     */
    private function createSessionId()
    {
        //Return a unique value in order to create a new session for every request (only applies for voice)
        if (isset($_SERVER["HTTP_X_CREATE_SESSION"]) && $_SERVER["HTTP_X_CREATE_SESSION"] == 1) {
            header('Content-Type: application/json');
            $length = rand(1, 5);
            $seed = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
            $response = ["session" => $seed . hash('sha256', time())];
            echo json_encode($response);
            die;
        }
    }

    /**
     * Check if response has direct call
     */
    protected function checkDirectCall($botResponse)
    {
        if (isset($botResponse->answers) && is_array($botResponse->answers)) {
            $messages = $botResponse->answers;
        } else {
            $messages = array($botResponse);
        }
        foreach ($messages as $msg) {
            if (isset($msg->attributes) && isset($msg->attributes->DIRECT_CALL) && trim($msg->attributes->DIRECT_CALL) !== "") {
                return $msg->attributes->DIRECT_CALL;
            }
        }
        return "";
    }

    /**
     * Check if response has phone to make the transfer
     * @param string $directCall = ""
     * @return string $phoneToTransfer
     */
    protected function checkPhoneToTransfer(string $directCall = "")
    {
        $phoneToTransfer = "-";
        if ($this->conf->get('chat.transfer_options.validate_on_transfer') === 'variable') {
            $phoneToTransfer = $this->session->get("phoneToTransfer", "-");
        } else {
            if (trim($directCall) !== "") {
                try {
                    $phoneToTransfer = $this->conf->get('chat.transfer_options.transfer_numbers.' . $directCall);
                } catch (\Exception $e) {
                    // If there is no phone number assigned in the "transfer_numbers" with the directCall
                    $phoneToTransfer = $this->conf->get('chat.transfer_options.transfer_numbers.default');
                }
                $this->session->set("phoneToTransfer", $phoneToTransfer);
            }
        }
        return $phoneToTransfer;
    }

    /**
     * Overwritten
     * Send messages to the external service. Messages should be formatted as a ChatbotAPI response
     */
    protected function sendMessagesToExternal($messages, string $directCall = "", string $phoneToTransfer = "", string $escalationMessage = "")
    {
        // Digest the bot response into the external service format
        $digestedBotResponse = $this->digester->digestFromApi($messages, $this->session->get('lastUserQuestion'));

        $messageToSend = "";
        foreach ($digestedBotResponse as $message) {
            $messageToSend .= " " . $message["body"];
        }
        if (trim($escalationMessage) !== "") {
            $messageToSend .= ", " . $escalationMessage;
        }
        $this->externalClient->sendMessage($messageToSend, $directCall, $phoneToTransfer);
    }

    /**
     * Overwritten
     */
    protected function handleEscalation($response = null)
    {
        $expectingVars = $this->validateExpectingVar(true);
        if (count($expectingVars) == 0 || $this->session->get("variableNotFound", 0) === 2) {
            $directCall = "";
            $phoneToTransfer = "";
            $escalationMessage = "";
            $this->session->delete("askForVariable");
            $this->session->delete("variableNotFound");
            $this->session->delete("variableNotFoundMessage");

            if (!$this->session->get('askingForEscalation', false)) {
                if ($this->session->get('escalationType') == static::ESCALATION_DIRECT) {
                    $directCall = "agent";
                    $this->checkPhoneToTransfer($directCall);
                    $phoneToTransfer = $this->session->get("phoneToTransfer", "-");
                } else {
                    $this->session->set('askingForEscalation', true);
                    $escalationMessage = $this->digester->buildEscalationMessage();
                }
                $this->sendMessagesToExternal($response, $directCall, $phoneToTransfer, $escalationMessage);
                die;
            } else {
                // Handle user response to an escalation question
                $this->session->set('askingForEscalation', false);
                // Reset escalation counters
                $this->session->set('noResultsCount', 0);
                $this->session->set('negativeRatingCount', 0);

                if (count($response) && isset($response[0]['escalateOption'])) {
                    if ($response[0]['escalateOption']) {
                        $messageToSend = $this->lang->translate('creating_chat');
                        $directCall = "agent";
                        $this->checkPhoneToTransfer($directCall);
                        $phoneToTransfer = $this->session->get("phoneToTransfer", "-");
                        $this->externalClient->sendMessage($messageToSend, $directCall, $phoneToTransfer);
                    } else {
                        if ($this->session->get('escalationType') == static::ESCALATION_OFFER) {
                            $message = ["message" => "no"];
                            $botResponse = $this->sendMessageToBot($message);
                            $this->sendMessagesToExternal($botResponse, $directCall, $phoneToTransfer);
                        } else {
                            $messageToSend = $this->lang->translate('escalation_rejected');
                            $this->externalClient->sendMessage($messageToSend, $directCall, $phoneToTransfer);
                            $this->trackContactEvent("CONTACT_REJECTED");
                        }
                        $this->session->delete('escalationType');
                        $this->session->delete('escalationV2');
                    }
                    die;
                }
            }
        } else {
            $this->askForVariables($expectingVars[0], $response);
        }
    }

    /**
     * Validate if there is initial variable
     */
    private function validateInitialVariable()
    {
        if (trim($this->conf->get('api.init_variable')) !== '') {
            $initialVariable = $this->conf->get('api.init_variable');
            $variableExists = is_null($this->session->get($initialVariable, null)) ? false : true;
            if (!$variableExists) {
                $missingVar = $this->searchVariables([$initialVariable]);
                if (count($missingVar) > 0) {
                    $this->noInitialVariable();
                }
            }
        }
    }

    /**
     * When there is no initial variable (and it's needed) show the welcome message or the escalation
     */
    private function noInitialVariable()
    {
        $countErrorsInitVar = $this->session->get("countErrorsInitVar", 0);
        if ($countErrorsInitVar < 1) {
            //Launch 2 times the welcome message before the escalation
            $countErrorsInitVar++;
            $this->session->set("countErrorsInitVar", $countErrorsInitVar);
            $this->handleWelcomeMessage(["message" => "sys-welcome"]);
        } else {
            $this->session->delete("countErrorsInitVar");
            $messageToSend = $this->lang->translate('creating_chat');
            $directCall = "agent";
            $phoneToTransfer = $this->conf->get('chat.transfer_options.transfer_numbers.default');
            $this->externalClient->sendMessage($messageToSend, $directCall, $phoneToTransfer);
        }
    }

    /**
     * Validate if there are an expectig variable and if applies for the phone to transfer
     * @param bool $fromHandleEscalation = false
     * @return array $missingVars
     */
    protected function validateExpectingVar(bool $fromHandleEscalation = false)
    {
        $missingVars = [];
        if (!is_null($this->session->get("expectingVar", null)) || $fromHandleEscalation) {
            $transferOptions = $this->conf->get('chat.transfer_options');
            if ($transferOptions["validate_on_transfer"] === "variable" && count($transferOptions["variables_to_check"]) > 0) {

                $phoneToTransfer = $transferOptions["transfer_numbers"]["default"];
                $varsToSearch = $transferOptions["variables_to_check"];
                $missingVars = $this->searchVariables($varsToSearch);
                if (count($missingVars) === 0) {
                    $phoneArray = [];
                    foreach ($varsToSearch as $var) {
                        if (!is_null($this->session->get($var, null))) {
                            $phoneArray[] = $this->session->get($var);
                        }
                    }
                    if (count($phoneArray) > 0) {
                        $phoneSearch = strtoupper(implode("_", $phoneArray));
                        $phoneToTransfer = isset($transferOptions["transfer_numbers"][$phoneSearch]) ?
                            $transferOptions["transfer_numbers"][$phoneSearch] :
                            $phoneToTransfer;
                    }
                    //Blenrep = Blenrep,Belamaf
                    $this->session->delete("expectingVar");
                }
                $this->session->set("phoneToTransfer", $phoneToTransfer);
            }
        }
        return $missingVars;
    }

    /**
     * Search the given variables in the Chatbot API
     * @param string $varsToSearch
     * @return array $missingVars
     */
    protected function searchVariables(array $varsToSearch)
    {
        $missingVars = $varsToSearch;
        $conversationVariables = $this->botClient->getVariables();
        foreach ($conversationVariables as $key => $var) {
            if (in_array($key, $varsToSearch)) {
                if (!is_null($var->value)) {
                    $this->session->set($key, $var->value);
                    if (($varKey = array_search($key, $missingVars)) !== false) {
                        unset($missingVars[$varKey]);
                    }
                }
            }
        }
        return array_values($missingVars);
    }

    /**
     * If needed, ask for a the variables needed to do escalation
     * @param string $expectingVar
     * @param $messageForEscalation
     */
    protected function askForVariables(string $expectingVar, $messageForEscalation = null)
    {
        $this->session->set('messageForEscalation', $messageForEscalation);
        $this->session->set("askForVariable", $expectingVar);
        $variableAskMessage = $this->session->get("variableNotFoundMessage", "") . $this->lang->translate('name_of');
        $this->externalClient->sendMessage($variableAskMessage . " " . $expectingVar);
    }

    /**
     * Set variable from user response
     * @param string $response
     */
    public function setVarFromResponse(string $response)
    {
        $messageForEscalation = $this->session->get('messageForEscalation');
        $varSetted = $this->setVariableValue($this->session->get("askForVariable"), $response);
        if (!$varSetted) {
            $variableNotFound = $this->session->get("variableNotFound", 0) + 1;
            $this->session->set("variableNotFound", $variableNotFound);
            $variableNotFoundMessage = $this->session->get("askForVariable") . " " . $this->lang->translate('not_found') . " ";
            if (
                $variableNotFound >= 2 && isset($messageForEscalation->answers)
                && isset($messageForEscalation->answers[0])
                && isset($messageForEscalation->answers[0]->message)
            ) {
                $messageForEscalation->answers[0]->message = $variableNotFoundMessage . " " . $messageForEscalation->answers[0]->message;
            }
            $this->session->set("variableNotFoundMessage", $variableNotFoundMessage . " " . $this->lang->translate('try_again') . " ");
        }
        $this->session->delete("askForVariable");
        $this->session->delete("messageForEscalation");
        $this->deleteLastMessage($messageForEscalation);
        $this->handleEscalation($messageForEscalation);
        die;
    }
}
