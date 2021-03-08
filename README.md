### OBJECTIVE
This template has been implemented in order to conect the Inbenta Chatbot API and the  **Intelepeer Smart Flow**, for Voice and SMS, with the minimum configuration and effort. The main library of this template is Intelepeer Connector, which extends from a base library named [Chatbot API Connector](https://github.com/inbenta-integrations/chatbot_api_connector), built to be used as a base for different external services like Facebook, Skype, Line, etc.

This template includes **/conf** and **/lang** folders, which have all the configuration and translations required by the libraries, and a small file **server.php** which creates an IntelepeersConnector’s instance in order to handle all the incoming requests.

### FUNCTIONALITIES
This bot template inherits the functionalities from the `ChatbotConnector` library. Currently, the features provided by this application are:

* Simple answers
* Multiple options.
* Polar questions.
* Chained answers.
* Send information to webhook through forms.

>**NOTE:** The variables of the forms should be only with any format (like email, dates, etc) for the use of Voice, this because the interaction through voice can't catch special chars like '@'.


### INSTALLATION
It's pretty simple to get this UI working. The mandatory configuration files are included by default in `/conf/custom` to be filled in, so you have to provide the information required in these files:

* **File 'api.php'**
    Provide the API Key and API Secret of your Chatbot Instance.


* **File 'intelepeer.php'**
    Provide the Token, defined by the customer. Any password-like value is valid.

* **File 'environments.php'**
    Here you can define regexes to detect `development` environment. If the regexes do not match the current conditions or there isn't any regex configured, `production` environment will be assumed.


### HOW TO CUSTOMIZE
**From configuration**

For a default behavior, the only requisite is to fill the basic configuration (more information in `/conf/README.md`). There are some extra configuration parameters in the configuration files that allow you to modify the basic-behavior.


**Custom Behaviors**

If you need to customize the bot flow, you need to extend the class `IntelepeerConnector`, included in the `/lib/IntelepeerConnector` folder. You can modify 'IntelepeerConnector' methods and override all the parent methods from `ChatbotConnector`.


### DEPENDENCIES
This application imports `inbenta/chatbot-api-connector` as a Composer dependency, that includes `symfony/http-foundation@^3.1` and `guzzlehttp/guzzle@~6.0` as dependencies too.
