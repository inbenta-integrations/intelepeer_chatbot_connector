# INTELEPEER CHATBOT INTEGRATION

### TABLE OF CONTENTS
* [Introduction](#introduction)
* [Features](#features)
* [Prepare your Inbenta instances](#prepare-your-inbenta-instances)
    * [Text Content](#text-content)
* [Building the Intelepeer Connector](#building-the-intelepeer-connector)
    * [Required Configuration](#required-configuration)
    * [Optional Configuration](#optional-configuration)
	* [ESCALATION (chat.php)](#escalation-chatphp)
	* [CONVERSATION (conversation.php)](#conversation-conversationphp)
	* [ENVIRONMENTS (environments.php)](#environments-environmentsphp)
	* [ESCALATION (chat.php)](#escalation-chatphp)
	* [Deployment](#deployment)
* [Intelepeer Configuration](#intelepeer-configuration)
    * [Template](#template)
    * [Flows](#flows)
    * [Initial Vars](#initial-vars)
    * [Debug (optional)](#debug-optional)
    * [Direct Calls](#direct-calls)
* [Token](#token)


## Introduction

If you want your users to use voice to chat with Inbenta’s chatbot, you could use this connector to integrate with [Intelepeer](https://www.intelepeer.com/). This connects to Intelepeer’s SmartFlows.

### Features

The following features of Inbenta’s chatbot are supported in the Intelepeer integration for voice and SMS.

- Answer Text.
- Sidebubble.
- Multiple options.
- Polar Questions.
- Dialogs.
- Forms, Actions & Variables (Keep in mind we are using voice as a channel. So, not all variable types work best with voice. Example: Email, Date).

## Prepare your Inbenta instances

### Text Content

The content from your instance should be simple: ***avoid the use of HTML tags, multimedia and URLs***. This is especially important if you are using Voice template, most of the HTML tags are not recognized by the TTS (Text-To-Speech) services and may cause malfunction.

“**Natural Language Search**” is the best **Transition type** for dialogs.

### Building the Intelepeer Connector

#### Required Configuration

In your UI directory, go to **conf**. Here, you have a README.md file with some structure and usage explanations. 

Fill the **key** and **secret** values inside the **conf/custom/api.php** file with your Inbenta Chatbot API credentials (Here is the documentation on how to find the key and secret from Inbenta’s backstage. Use the same credentials as backstage to access the article).

Additional, in this file you can define the name of a variable (**init_variable**), this applies in case you have an initial variable to be set when the conversation starts.

```php
'key' => '',
'secret' => '',
'init_variable' => '' //Set the initial variable name, if needed
```

#### Optional Configuration

There are some optional features that can be enabled from the configuration files. Every optional configuration file should be copied from **/conf/default** and store the custom version in **/conf/custom**. The bot will detect the customization and it will load the customized version. These are the optional configuration files and the configuration fields description.

### CONVERSATION (conversation.php)

- **default:** Contains the API conversation configuration. The values are described below:
	- **answers:**
		- **sideBubbleAttributes:** Dynamic settings to show side-bubble content. This value will append to the main response.
		- **answerAttributes:** Dynamic settings to show as the bot answer. The default is [ "ANSWER_TEXT" ]. Setting multiple dynamic settings generates a bot answer with concatenated values with a newline character (\n).
		- **maxOptions:** Maximum number of options returned in a multiple-choice answer.
	- **forms**
		- **allowUserToAbandonForm:** Whether or not a user is allowed to abandon the form after a number of consecutive failed answers. The default value is **true**.
		- **errorRetries:** The number of times a user can fail a form field before being asked if he wants to leave the form. The default value is 3.
	- **lang:** Language of the bot, represented by its ISO 639-1 code. Accepted values: ca, de, en, es, fr, it, ja, ko, nl, pt, zh, ru, ar, hu, eu, ro, gl, da, sv, no, tr, cs, fi, pl, el, th, id, uk
- **user_type:** Profile identifier from the Backstage knowledge base. Minimum:0. Default:0. You can find your profile list in your Chatbot Instance → Settings → User Types.
- **source:** Source identifier (Default value **intelepeer**) used to filter the logs in the dashboards.

### ENVIRONMENTS (environments.php)

This file allows configuring a rule to detect the current environment for the connector, this process is made through the URL where the application is running. It can check the current **http_host** or the **script_name** in order to detect the environment.

- **development:**
	- **type:** Detection type: check the **http_host** (e.g. www.example.com) or the **script_name** (e.g. /path/to/the/connector/server.php).
	- **regex:** Regex to match with the detection type (e.g. “/^dev.mydomain.com$/m“ will set the “development” environment when the detection type is dev.example.com).

### ESCALATION (chat.php)

This file has the option for make an escalation to a live agent:

- **chat:**
	- **enabled:** Enable or disable HyperChat (“**true**” or “**false**”).
- **triesBeforeEscalation:** Number of no-result answers in a row after the bot should escalate to an agent (if available). Numeric value, not a string. Zero means it’s disabled.
- **negativeRatingsBeforeEscalation:** Number of negative content ratings in a row after the bot should escalate to an agent (if available). Numeric value, not a string. Zero means it’s disabled.
- **transfer_options:** Here are the number or numbers to transfer an also the options when 
	- **validate_on_transfer:** Possible values: variable or directCall. If variable is set, it’ll check the list of variables_to_check before make the escalation. If directCall, it will check the directCall value from the Bot response.
	- **variables_to_check:** Array value with the list of the names of variables to check. This applies when 'validate_on_transfer' is 'variable', otherwise empty array is correct.
	- **transfer_numbers:** Array value with the list of numbers for the escalation. Example:

```php
'transfer_numbers' => [
    'default' => '1234561', //Default transfer number ("-" if no number to transfer)
    //'var1_var2' => '1234562', //If validation is made with 'variable'
    //'sales' => '1234565', //If validation is made with 'directCall'
    //'' => '' //You could have multiple transfer numbers or just the 'defaul'
]
```

> Since **variables_to_check** is a list, you can have 1 or more variables to check before the escalation. In the **transfer_numbers** array, you must divide every value with an underscore (`v1_v2`). If you have only one variable, you don’t need any additional character (`v1`).

### Deployment

The Intelepeer template must be served by a public web server in order to allow Intelepeer to send the events to it. The environment where the template has been developed and tested has the following specifications

- Apache 2.4
- PHP 7.3
- PHP Curl extension
- Non-CPU-bound
- The latest version of [Composer](https://getcomposer.org/) (Dependency Manager for PHP) to install all dependencies that Inbenta requires for the integration.
- If the client has a **distributed infrastructure**, this means that multiple servers can manage the user session, they must adapt their SessionHandler so that the entire session is shared among all its servers.


## Intelepeer Configuration

### Template

Into your Intelepeer dashboard, go to SmartFlows:

![instructions01](public/instructions01.png)

In the SmartFlows screen, click on the “+” button:

![instructions02](public/instructions02.png)

And then select “Template”:

![instructions03](public/instructions03.png)

Search the Template of “**Inbenta Voice**” or “**Inbenta SMS**” and click on “Select”:

![instructions04](public/instructions04.png)

### Initial Vars

Once the Template is selected, and the SmartFlow editor opened, you will find a different number of nodes, depending on the selected Template. In the **InitVars** node you can find the configurations for the connector.

![instructions05](public/instructions05.png)

These are the values that you should change in the **InitVars** node (notice that the variables starting with “**$Audio…**” and “**$MaxAttempts**” only applies for Voice SmartFlow):

- **$ApiUrl:** This is the public URL where the Connector is installed.
- **$Token:** This is a customer defined value (password like value), see “Token” section for more details.
- **$UrlDebug:** Url where a debugger is installed, see “Debug” section below for more details.
- **$Debug:** Switch to indicate to SmartFlow if the Debug is On (1) or Off (0), see “Debug” section below for more details.
- **$AudioTryAgain:** Text for the message to hear when there is no a voice instruction from the caller.
- **$AudioMaxAttempts:** Text that users will hear when the maximum attempts of no messages is reached
- **$AudioError:** Change this text to hear the message when an error occurs.
- **$MaxAttempts:** Number for maximum attempts of no message received.

### Debug (optional)

You can use a debugger to validate what is happening on the flow. In this case, the debugger needs to be in a web server and ready to catch REST requests (POST, GET, etc). You can use the **$UrlDebug** variable to set your own debugger URL and **$Debug** to switch off or on.

In the SmartFlow Template, click on DebugCall node, in the SmartFlow editor:

![instructions06](public/instructions06.png)

On the lateral column appears the properties of the node. In this case you can fill the values that applies for your debugger:

![instructions07](public/instructions07.png)

And also set the text of the body that is going to be sent to the debugger:

![instructions08](public/instructions08.png)

> For production environment the recommendation is to set $Debug = 0

### Direct Calls

The response from the Chatbot Connector has a variable called “**directCall**” and is used to catch an action inside the loop of the flow. This action could be speak with a live human agent or end the call.

In the SmartFlow this can be found as **ActionSwitch**:

![instructions09](public/instructions09.png)

![instructions10](public/instructions10.png)

**directCall** values can be added in the Inbenta’s backstage. You need to add a new content with the intent (Title and ANSWER_TEXT are required):

![instructions11](public/instructions11.png)

And check “Direct call” with the trigger word (that should be the same as one of the options of SmartFlow’s “**ActionSwitch**” node):

![instructions12](public/instructions12.png)

> Inbenta uses AI and NLP that will automatically detects the intent. So you could use similar words to ask for the same thing (for the escalation example: talk with a human, chat with a person, speak with somebody, etc.).

With this option, you can add multiple actions besides the Chatbot loop.

### Token

In order to create a valid connection between Intelepeer and the Inbenta Connector, a Token is needed. This is a password like value, is mandatory and should have a length less than 256 characters.

The defined value for the token must be present in the **$Token** variable, inside the **InitVars** node and in the **custom/intelepeer.php** file, on the Connector:

```php
return [
    'token' => ''
];
```

> The value of the Token is not provided by Inbenta nor Intelepeer, this is defined by the customer.