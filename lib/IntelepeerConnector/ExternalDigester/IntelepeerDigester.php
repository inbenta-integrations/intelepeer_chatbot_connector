<?php

namespace Inbenta\IntelepeerConnector\ExternalDigester;

use \Exception;
use Inbenta\ChatbotConnector\ExternalDigester\Channels\DigesterInterface;

class IntelepeerDigester extends DigesterInterface
{
    protected $conf;
    protected $langManager;
    protected $session;
    public $typeRequest;

    /**
     * Digester contructor
     */
    public function __construct($langManager, $conf, $session)
    {
        $this->langManager = $langManager;
        $this->conf = $conf;
        $this->session = $session;
    }

    /**
     *	Overwritten, not used
     */
    public function getChannel()
    {
        return "";
    }

    /**
     **	Checks if a request belongs to the digester 
     **/
    public static function checkRequest($_request)
    {
        parse_str($_request, $request);
        if (isset($request['body'])) {
            return true;
        }
        return false;
    }

    /**
     * Get the type of request (voice, sms, etc)
     */
    private function setTypeRequest($request)
    {
        if (isset($request["type"])) {
            $this->typeRequest = $request["type"];
        }
    }

    /**
     * Formats an Intelepeer request into an Inbenta Chatbot API request
     * @param string $_request
     * @return array
     */
    public function digestToApi($_request)
    {
        $request = json_decode($_request, true);

        $this->setTypeRequest($request);

        $output = $this->checkOptions($request);
        if (count($output) == 0 && $this->session->has('askForVariable')) {
            //Catch the response if is asking for a variable from user response
            $variable = $this->processResponseWithSpaces(trim($request['body']));
            $output[0] = ['variable' => $variable];
        } else if (count($output) == 0 && isset($request['body'])) {
            $output[0] = ['message' => $request['body']];
        }
        return $output;
    }

    /**
     * Check if response has options
     * @param array $request
     */
    protected function checkOptions(array $request)
    {
        $output = [];
        if ($this->session->has('options')) {

            $lastUserQuestion = $this->session->get('lastUserQuestion');
            $options = $this->session->get('options');
            $this->session->delete('options');
            $this->session->delete('lastUserQuestion');
            $this->session->delete('hasRelatedContent');

            if (isset($request['body'])) {

                $userMessage = $request['body'];
                $selectedOption = false;
                $selectedOptionText = "";
                $selectedEscalation = "";
                $isRelatedContent = false;
                $isListValues = false;
                $isPolar = false;
                $isEscalation = false;
                $optionSelected = false;
                foreach ($options as $option) {
                    if (isset($option->list_values)) {
                        $isListValues = true;
                    } else if (isset($option->related_content)) {
                        $isRelatedContent = true;
                    } else if (isset($option->is_polar)) {
                        $isPolar = true;
                    } else if (isset($option->escalate)) {
                        $isEscalation = true;
                    }
                    if ($userMessage == $option->opt_key || strtolower($userMessage) == strtolower($option->label)) {
                        if ($isListValues || $isRelatedContent || (isset($option->attributes) && isset($option->attributes->DYNAMIC_REDIRECT) && $option->attributes->DYNAMIC_REDIRECT == 'escalationStart')) {
                            $selectedOptionText = $option->label;
                        } else if ($isEscalation) {
                            $selectedEscalation = $option->escalate;
                        } else {
                            $selectedOption = $option;
                            $lastUserQuestion = isset($option->title) && !$isPolar ? $option->title : $lastUserQuestion;
                        }
                        $optionSelected = true;
                        break;
                    }
                }

                if (!$optionSelected) {
                    if ($isListValues) { //Set again options for variable
                        $this->session->set('options', $options);
                        $this->session->set('lastUserQuestion', $lastUserQuestion);
                    } else if ($isPolar) { //For polar, on wrong answer, goes for NO
                        $request['body'] = $this->langManager->translate('no');
                    }
                }

                if ($selectedOption) {
                    $output[] = ['option' => $selectedOption->value];
                } else if ($selectedOptionText !== "") {
                    $output[] = ['message' => $selectedOptionText];
                } else if ($isEscalation && $selectedEscalation !== "") {
                    $output[] = ['escalateOption' => $selectedEscalation];
                } else {
                    $output[] = ['message' => $request['body']];
                }
            }
        }
        return $output;
    }

    /**
     * Formats an Inbenta Chatbot API response into an Intelepeer request
     * @param object $request
     * @param string $lastUserQuestion = ''
     */
    public function digestFromApi($request, $lastUserQuestion = '')
    {
        //Parse request messages
        if (isset($request->answers) && is_array($request->answers)) {
            $messages = $request->answers;
        } elseif ($this->checkApiMessageType($request) !== null) {
            $messages = array('answers' => $request);
        } else {
            throw new Exception("Unknown ChatbotAPI response: " . json_encode($request, true));
        }

        $output = [];
        foreach ($messages as $msg) {
            $msgType = $this->checkApiMessageType($msg);
            $digester = 'digestFromApi' . ucfirst($msgType);
            $output[] = $this->$digester($msg, $lastUserQuestion);
        }
        return $output;
    }

    /**
     **	Classifies the API message into one of the defined $apiMessageTypes
     **/
    protected function checkApiMessageType($message)
    {
        foreach ($this->apiMessageTypes as $type) {
            $checker = 'isApi' . ucfirst($type);

            if ($this->$checker($message)) {
                return $type;
            }
        }
        return null;
    }

    /********************** API MESSAGE TYPE CHECKERS **********************/

    protected function isApiAnswer($message)
    {
        return isset($message->type) && $message->type == 'answer';
    }

    protected function isApiPolarQuestion($message)
    {
        return isset($message->type) && $message->type == "polarQuestion";
    }

    protected function isApiMultipleChoiceQuestion($message)
    {
        return isset($message->type) && $message->type == "multipleChoiceQuestion";
    }

    protected function isApiExtendedContentsAnswer($message)
    {
        return isset($message->type) && $message->type == "extendedContentsAnswer";
    }

    protected function hasTextMessage($message)
    {
        return isset($message->message) && is_string($message->message);
    }


    /********************** CHATBOT API MESSAGE DIGESTERS **********************/

    protected function digestFromApiAnswer($message, $lastUserQuestion)
    {
        $messageTxt = $message->message;

        if (isset($message->attributes->SIDEBUBBLE_TEXT) && trim($message->attributes->SIDEBUBBLE_TEXT) !== "") {
            $messageTxt .= ($this->typeRequest == "voice" ? ". " : "\n") . $message->attributes->SIDEBUBBLE_TEXT;
        }

        $output = [];
        $this->handleMessageWithIframe($messageTxt);
        $this->handleMessageWithActionField($message, $messageTxt, $lastUserQuestion);
        $this->handleMessageWithRelatedContent($message, $messageTxt, $lastUserQuestion);
        $this->handleMessageWithLinks($messageTxt);

        // Add simple text-answer
        $output["body"] = $this->formatFinalMessage($messageTxt);
        return $output;
    }


    protected function digestFromApiMultipleChoiceQuestion($message, $lastUserQuestion, $isPolar = false)
    {
        $output = [
            "body" => $this->formatFinalMessage($message->message)
        ];

        $options = $message->options;
        foreach ($options as $i => &$option) {
            $option->opt_key = $i + 1;
            if (isset($option->attributes->title) && !$isPolar) {
                $option->title = $option->attributes->title;
            } elseif ($isPolar) {
                $option->is_polar = true;
            }
            if ($this->typeRequest === 'voice') {
                $output['body'] .= ". " . $option->label;
            } else {
                $output['body'] .= "\n" . $option->opt_key . ') ' . $option->label;
            }
        }
        $this->session->set('options', $options);
        $this->session->set('lastUserQuestion', $lastUserQuestion);

        return $output;
    }

    protected function digestFromApiPolarQuestion($message, $lastUserQuestion)
    {
        return $this->digestFromApiMultipleChoiceQuestion($message, $lastUserQuestion, true);
    }


    protected function digestFromApiExtendedContentsAnswer($message, $lastUserQuestion)
    {
        $output = [
            "body" => $this->formatFinalMessage("_" . $message->message . "_"),
        ];

        $messageTitle = [];
        $messageExtended = [];
        $hasUrl = false;

        foreach ($message->subAnswers as $index => $subAnswer) {

            $messageTitle[$index] = $subAnswer->message;

            if (!isset($messageExtended[$index])) $messageExtended[$index] = [];

            if (isset($subAnswer->parameters) && isset($subAnswer->parameters->contents)) {
                if (isset($subAnswer->parameters->contents->url)) {
                    $messageExtended[$index][] = " (" . $subAnswer->parameters->contents->url->value . ")\n";
                    $hasUrl = true;
                }
            }
        }

        $messageTmp = "";
        if ($hasUrl) {
            foreach ($messageTitle as $index => $mt) {
                $messageTmp .= "\n\n" . $mt;
                foreach ($messageExtended[$index] as $key => $me) {
                    $messageTmp .= ($key == 0 ? "\n\n" : "") . $me;
                }
            }
        } else {
            if (count($messageTitle) == 1) {
                $tmp = $this->digestFromApiAnswer($message->subAnswers[0], $lastUserQuestion);
                $messageTmp = "\n\n" . $tmp["body"];
            } else if (count($messageTitle) > 1) {
                $messageTmp = "\n";
                foreach ($messageTitle as $index => $mt) {
                    $messageTmp .= "\n" . ($index + 1) . ") " . $mt;
                }
                $this->session->set('federatedSubanswers', $message->subAnswers);
            }
        }
        $output["body"] .=  $this->formatFinalMessage($messageTmp);

        return $output;
    }


    /********************** MISC **********************/

    /**
     * Create the content for ratings
     */
    public function buildContentRatingsMessage($ratingOptions, $rateCode)
    {
        $message = $this->langManager->translate('rate_content_intro') . "\n";
        foreach ($ratingOptions as $index => $option) {
            $message .= "\n" . ($index + 1) . ") " . $this->langManager->translate($option['label']);
        }
        return $message;
    }

    /**
     * Validate if the message has action fields
     */
    private function handleMessageWithActionField($message, &$messageTxt, $lastUserQuestion)
    {
        if (isset($message->actionField) && !empty($message->actionField)) {
            if ($message->actionField->fieldType === 'list') {
                $options = $this->handleMessageWithListValues($message->actionField->listValues, $lastUserQuestion);
                if ($options !== "") {
                    $messageTxt .= $options;
                }
            } else if ($message->actionField->fieldType === 'datePicker') {
                $messageTxt .= $this->typeRequest == "voice" ? "" : " (date format: mm/dd/YYYY)";
            } else if ($message->actionField->fieldType === 'default' && isset($message->actionField->variableName)) {
                $this->session->set("expectingVar", $message->actionField->variableName);
            }
        }
    }

    /**
     * Validate if the message has related content and put like an option list
     */
    private function handleMessageWithRelatedContent($message, &$messageTxt, $lastUserQuestion)
    {
        if (isset($message->parameters->contents->related->relatedContents) && !empty($message->parameters->contents->related->relatedContents)) {
            $messageTxt .= ($this->typeRequest == "voice" ? ". " : "\r\n \r\n") . $message->parameters->contents->related->relatedTitle . " ";
            $options = [];
            $optionList = "";
            foreach ($message->parameters->contents->related->relatedContents as $key => $relatedContent) {
                $options[$key] = (object) [];
                $options[$key]->opt_key = $key + 1;
                $options[$key]->related_content = true;
                $options[$key]->label = $relatedContent->title;
                if ($this->typeRequest === 'voice') {
                    $optionList .= ". " . $relatedContent->title;
                } else {
                    $optionList .= "\n\n" . ($key + 1) . ') ' . $relatedContent->title;
                }
            }
            if ($optionList !== "") {
                $messageTxt .= $optionList;
                $this->session->set('options', (object) $options);
                $this->session->set('lastUserQuestion', $lastUserQuestion);
                $this->session->set('hasRelatedContent', true);
            }
        }
    }

    /**
     *	Splits a message that contains an <img> tag into text/image/text and displays them in Intelepeer
     */
    protected function handleMessageWithImages($message)
    {
        return [];
    }

    /**
     * Extracts the url from the iframe
     */
    private function handleMessageWithIframe(&$messageTxt)
    {
        //Remove \t \n \r and HTML tags (keeping <iframe> tags)
        $text = str_replace(["\r\n", "\r", "\n", "\t"], '', strip_tags($messageTxt, "<iframe>"));
        //Capture all IFRAME tags and return an array with [text,imageURL,text,...]
        $parts = preg_split('/<\s*iframe.*?src\s*=\s*"(.+?)".*?\s*\/?>/', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $elements = [];
        for ($i = 0; $i < count($parts); $i++) {
            if (substr($parts[$i], 0, 4) == 'http') {
                //$this->typeRequest == "voice" //TODO: VALIDATE FOR VOICE
                {
                    $pos1 = strpos($messageTxt, "<iframe");
                    $pos2 = strpos($messageTxt, "</iframe>", $pos1);
                    $iframe = substr($messageTxt, $pos1, $pos2 - $pos1 + 9);
                    //$messageTxt = str_replace($iframe, "<a href='" . $parts[$i] . "'></a>", $messageTxt);
                    $messageTxt = str_replace($iframe, $parts[$i], $messageTxt);
                }
            }
        }
        return $elements;
    }

    /**
     * Remove the common html tags from the message and set the final message
     */
    public function formatFinalMessage($message)
    {
        $message = str_replace("&nbsp;", " ", $message);
        $message = str_replace(["\t"], '', $message);
        $message = str_ireplace(["’", "&sbquo;"], "'", $message);

        $message = html_entity_decode($message, ENT_COMPAT, "UTF-8");

        $breaks = array("<br />", "<br>", "<br/>", "<p>");
        $message = str_ireplace($breaks, "\n", $message);

        $message = strip_tags($message);

        $rows = explode("\n", $message);
        $messageProcessed = "";
        $previousJump = 0;
        foreach ($rows as $row) {
            $row = trim($row);
            if ($row == "" && $previousJump == 0) {
                $previousJump++;
            } else if ($row == "" && $previousJump == 1) {
                $previousJump++;
                $messageProcessed .= ($this->typeRequest == "voice" ? ". " : "\r\n");
            }
            if ($row !== "") {
                $messageProcessed .= $row . ($this->typeRequest == "voice" ? ". " : "\r\n");
                $previousJump = 0;
            }
        }
        $messageProcessed = str_replace("  ", " ", $messageProcessed);
        $messageProcessed = ltrim($messageProcessed);
        return $messageProcessed;
    }

    /**
     * Set the options for message with list values
     */
    protected function handleMessageWithListValues($listValues, $lastUserQuestion)
    {
        $optionList = "";
        $options = $listValues->values;
        foreach ($options as $i => &$option) {
            $option->opt_key = $i + 1;
            $option->list_values = true;
            $option->label = $option->option;
            if ($this->typeRequest === 'voice') {
                $optionList .= ". " . $option->label;
            } else {
                $optionList .= "\n" . $option->opt_key . ') ' . $option->label;
            }
        }
        if ($optionList !== "") {
            $this->session->set('options', $options);
            $this->session->set('lastUserQuestion', $lastUserQuestion);
        }
        return $optionList;
    }


    /**
     * Format the link as part of the message
     */
    public function handleMessageWithLinks(&$messageTxt)
    {
        if ($messageTxt !== "") {
            $dom = new \DOMDocument();
            $dom->loadHTML($messageTxt);
            $nodes = $dom->getElementsByTagName('a');

            $urls = [];
            $value = [];
            foreach ($nodes as $node) {
                $urls[] = $node->getAttribute('href');
                $value[] = trim($node->nodeValue);
            }

            if (strpos($messageTxt, '<a ') !== false && count($urls) > 0) {
                $countLinks = substr_count($messageTxt, "<a ");
                $lastPosition = 0;
                for ($i = 0; $i < $countLinks; $i++) {
                    $firstPosition = strpos($messageTxt, "<a ", $lastPosition);
                    $lastPosition = strpos($messageTxt, "</a>", $firstPosition);

                    if (isset($urls[$i]) && $lastPosition > 0) {
                        $aTag = substr($messageTxt, $firstPosition, $lastPosition - $firstPosition + 4);
                        $textToReplace = $value[$i] !== "" ? $value[$i] . " (" . $urls[$i] . ")" : $urls[$i];
                        $messageTxt = str_replace($aTag, $textToReplace, $messageTxt);
                    }
                }
            }
        }
    }

    /**
     *	Disabled for Intelepeer
     */
    protected function buildUrlButtonMessage($message, $urlButton)
    {
        $output = [];
        return $output;
    }

    /**
     * Build the message and options to escalate
     * @return array
     */
    public function buildEscalationMessage()
    {
        $escalateOptions = [
            (object) [
                "label" => 'yes',
                "escalate" => true,
                "opt_key" => 1
            ],
            (object) [
                "label" => 'no',
                "escalate" => false,
                "opt_key" => 2
            ],
        ];

        $this->session->set('options', (object) $escalateOptions);
        $message = $this->langManager->translate('ask_to_escalate') . ($this->typeRequest == "voice" ? "" : "\n");

        $message .= ($this->typeRequest == "voice" ? ". " : "1) ") . $this->langManager->translate('yes') . ($this->typeRequest == "voice" ? "" : "\n");
        $message .= ($this->typeRequest == "voice" ? ". " : "2) ") . $this->langManager->translate('no');
        return $message;
    }

    /**
     * Clean blank spaces if the response is letter by letter
     * @param string $response
     * @return string $response
     */
    protected function processResponseWithSpaces(string $response)
    {
        $long = strlen($response);
        $spaces = substr_count($response, ' ');
        if ($long > 1 && (($long - 1) / 2) === $spaces) {
            $response = str_replace(" ", "", $response);
        }
        return $response;
    }
}
